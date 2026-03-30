<?php

namespace WSms\SubscriptionForm;

use WSms\Contact\ContactRepository;
use WSms\Contact\ListRepository;
use WSms\Support\PhoneValidator;
use WSms\Verification\VerificationService;

defined('ABSPATH') || exit;

class SubscriptionHandler
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly ListRepository $listRepository,
        private readonly VerificationService $verificationService,
    ) {
    }

    public function submit(SubscriptionForm $form, array $data): SubmissionResult
    {
        foreach ($form->getFields() as $field) {
            if (!empty($field['required']) && empty($data[$field['key']])) {
                return SubmissionResult::failed(
                    'validation_error',
                    sprintf(__('The %s field is required.', 'wp-sms'), $field['label'] ?? $field['key']),
                );
            }
        }

        // Fake success to confuse bots.
        if (!empty($data['_hp'])) {
            return SubmissionResult::success($form->getSuccessMessage());
        }

        // Validate consent if required.
        if ($this->isConsentRequired($form) && empty($data['consent'])) {
            return SubmissionResult::failed(
                'consent_required',
                __('You must agree to the terms before subscribing.', 'wp-sms'),
            );
        }

        $email = !empty($data['email']) ? strtolower(sanitize_email($data['email'])) : null;
        $phone = !empty($data['phone']) ? sanitize_text_field($data['phone']) : null;

        if ($email !== null && !is_email($email)) {
            return SubmissionResult::failed('validation_error', __('Please provide a valid email address.', 'wp-sms'));
        }

        if ($phone !== null && !PhoneValidator::isE164($phone)) {
            return SubmissionResult::failed('validation_error', __('Please enter a valid phone number with country code (e.g. +12025551234).', 'wp-sms'));
        }

        $contact = null;
        if ($email) {
            $contact = $this->contactRepository->findByEmail($email);
        }
        if (!$contact && $phone) {
            $contact = $this->contactRepository->findByPhone($phone);
        }

        $tagIds = $this->resolveTagIds($form);

        if ($contact) {
            return $this->handleExistingContact($form, $contact, $data, $email, $phone, $tagIds);
        }

        return $this->handleNewContact($form, $data, $email, $phone, $tagIds);
    }

    public function verify(SubscriptionForm $form, string $sessionToken, string $code): SubmissionResult
    {
        $channel = $form->getOptInChannel();

        $transientKey = 'wsms_sub_' . $this->sessionTokenToKey($sessionToken);
        $pending = get_transient($transientKey);

        if (!$pending || !is_array($pending)) {
            return SubmissionResult::failed('invalid_session', __('Session expired or invalid. Please submit the form again.', 'wp-sms'));
        }

        $identifier = $pending['identifier'] ?? '';
        $contactId = $pending['contact_id'] ?? '';

        if (!$identifier || !$contactId) {
            return SubmissionResult::failed('invalid_session', __('Session expired or invalid. Please submit the form again.', 'wp-sms'));
        }

        $result = $this->verificationService->verifyCode($channel, $identifier, $code, $sessionToken);

        if (!$result->success) {
            return SubmissionResult::failed($result->error ?? 'verification_failed', $result->message);
        }

        $verifiedField = $channel === 'email' ? 'email_verified' : 'phone_verified';
        $this->contactRepository->update($contactId, [
            'status'       => 'subscribed',
            $verifiedField => 1,
        ]);
        delete_transient($transientKey);

        do_action('wsms_subscription_confirmed', $contactId, $form->getId());

        return SubmissionResult::verified();
    }

    private function handleExistingContact(SubscriptionForm $form, array $contact, array $data, ?string $email, ?string $phone, array $tagIds): SubmissionResult
    {
        $contactId = $contact['id'];
        $status = $contact['status'] ?? 'subscribed';

        // Complained contacts are always rejected.
        if ($status === 'complained') {
            return SubmissionResult::failed('blocked', __('This address has been blocked.', 'wp-sms'));
        }

        // Check if already subscribed with all required tags and no channel opt-out.
        $channel = $form->getOptInChannel();
        $isOptedOut = $this->contactRepository->isChannelOptedOut($contactId, $channel);

        if ($status === 'subscribed' && !$isOptedOut && $this->hasAllTags($contactId, $tagIds)) {
            return SubmissionResult::success($form->getSuccessMessage());
        }

        // Update contact data.
        $updateData = array_filter([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $email,
            'phone' => $phone,
        ], fn($v) => $v !== null);

        // Apply tags.
        $this->applyTags($contactId, $tagIds);

        // Clear channel opt-out on re-subscribe
        if ($isOptedOut) {
            $this->contactRepository->clearChannelOptOut($contactId, $channel);
        }

        if ($status === 'subscribed') {
            // Already subscribed (or was opted-out but now cleared), just added missing tags.
            if (!empty($updateData)) {
                $this->contactRepository->update($contactId, $updateData);
            }
            return SubmissionResult::success($form->getSuccessMessage());
        }

        // For pending status: re-send verification if double opt-in.
        // For bounced: re-subscribe (with verification if needed).
        if ($form->requiresDoubleOptIn()) {
            $updateData['status'] = 'pending';
            if (!empty($updateData)) {
                $this->contactRepository->update($contactId, $updateData);
            }
            return $this->sendVerification($form, $contactId, $email, $phone);
        }

        $updateData['status'] = 'subscribed';
        $this->contactRepository->update($contactId, $updateData);

        do_action('wsms_subscription_form_submitted', $contactId, $form->getId(), $data);

        return SubmissionResult::success($form->getSuccessMessage());
    }

    private function handleNewContact(SubscriptionForm $form, array $data, ?string $email, ?string $phone, array $tagIds): SubmissionResult
    {
        $status = $form->requiresDoubleOptIn() ? 'pending' : 'subscribed';

        $contactId = $this->contactRepository->create([
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $email,
            'phone' => $phone,
            'source' => 'subscription_form',
            'source_ref' => $form->getSlug(),
            'status' => $status,
        ]);

        $this->applyTags($contactId, $tagIds);

        if ($form->requiresDoubleOptIn()) {
            return $this->sendVerification($form, $contactId, $email, $phone);
        }

        do_action('wsms_subscription_form_submitted', $contactId, $form->getId(), $data);

        return SubmissionResult::success($form->getSuccessMessage());
    }

    private function sendVerification(SubscriptionForm $form, string $contactId, ?string $email, ?string $phone): SubmissionResult
    {
        $channel = $form->getOptInChannel();
        $identifier = $channel === 'email' ? $email : $phone;

        if (!$identifier) {
            return SubmissionResult::failed('missing_identifier', sprintf(__('No %s provided for verification.', 'wp-sms'), $channel));
        }

        $result = $this->verificationService->sendCode($channel, $identifier);

        if (!$result->success) {
            return SubmissionResult::failed($result->error ?? 'delivery_failed', $result->message);
        }

        // Store contact ID in transient keyed by session token.
        $transientKey = 'wsms_sub_' . $this->sessionTokenToKey($result->sessionToken);
        set_transient($transientKey, [
            'contact_id' => $contactId,
            'identifier' => $identifier,
            'form_id' => $form->getId(),
        ], 3600);

        return SubmissionResult::pendingVerification(
            $result->sessionToken,
            $result->maskedIdentifier,
            $result->expiresIn ?? 300,
        );
    }

    /**
     * @return string[]
     */
    private function resolveTagIds(SubscriptionForm $form): array
    {
        $tagIds = [];

        // List's tag.
        if ($form->getListId()) {
            $list = $this->listRepository->find($form->getListId());
            if ($list && !empty($list['tag_id'])) {
                $tagIds[] = $list['tag_id'];
            }
        }

        // Form's direct tag.
        if ($form->getTagId()) {
            $tagIds[] = $form->getTagId();
        }

        return array_unique($tagIds);
    }

    private function applyTags(string $contactId, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            $this->contactRepository->addTag($contactId, $tagId);
        }
    }

    private function hasAllTags(string $contactId, array $tagIds): bool
    {
        if (empty($tagIds)) {
            return true;
        }

        $existingTags = $this->contactRepository->getTags($contactId);
        $existingTagIds = array_column($existingTags, 'id');

        foreach ($tagIds as $tagId) {
            if (!in_array($tagId, $existingTagIds, true)) {
                return false;
            }
        }

        return true;
    }

    private function isConsentRequired(SubscriptionForm $form): bool
    {
        $formRequired = $form->isConsentRequired();
        if ($formRequired !== null) {
            return $formRequired;
        }

        $authSettings = get_option('wsms_auth_settings', []);

        return !empty($authSettings['subscription_consent_required']);
    }

    private function sessionTokenToKey(string $sessionToken): string
    {
        return substr(hash('sha256', $sessionToken), 0, 32);
    }
}
