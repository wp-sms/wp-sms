<?php

namespace WSms\MessagingButton;

use WSms\Auth\SettingsRepository;
use WSms\Contact\ContactRepository;
use WSms\Contact\ListRepository;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\Message\Message;
use WSms\Support\PhoneValidator;

defined('ABSPATH') || exit;

class MessageHandler
{
    public function __construct(
        private readonly MessagingButtonSettings $settings,
        private readonly ContactRepository $contactRepository,
        private readonly ListRepository $listRepository,
        private readonly MessageDispatcher $messageDispatcher,
    ) {
    }

    /**
     * Process an incoming message from the widget.
     *
     * @return array{success: bool, contact_id?: string, error?: string}
     */
    public function handle(array $data): array
    {
        $formSettings = $this->settings->get('pages')['contact_form'] ?? [];

        // Validate required fields
        $requiredFields = $formSettings['required_fields'] ?? ['email', 'message'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'error' => sprintf(__('The %s field is required.', 'wp-sms'), $field),
                ];
            }
        }

        // Create or update contact
        $contactId = $this->upsertContact($data, $formSettings);

        // Dispatch notification to recipients
        $this->dispatchNotification($data, $formSettings);

        // Fire action for flow triggers
        do_action('wsms_messaging_button_message', [
            'contact_id' => $contactId,
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'message' => $data['message'] ?? '',
            'page_url' => $data['page_url'] ?? '',
        ]);

        return [
            'success' => true,
            'contact_id' => $contactId,
        ];
    }

    private function upsertContact(array $data, array $formSettings): ?string
    {
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;

        // Drop non-E.164 phones silently — don't block message submission.
        if ($phone && !PhoneValidator::isE164($phone)) {
            $phone = null;
        }

        if (!$email && !$phone) {
            return null;
        }

        // Try to find existing contact
        $contact = null;
        if ($email) {
            $contact = $this->contactRepository->findByEmail($email);
        }
        if (!$contact && $phone) {
            $contact = $this->contactRepository->findByPhone($phone);
        }

        $nameParts = $this->parseName($data['name'] ?? '');

        if ($contact) {
            // Update existing contact with new data
            $updateData = array_filter([
                'first_name' => $nameParts['first_name'] ?: null,
                'last_name' => $nameParts['last_name'] ?: null,
                'email' => $email,
                'phone' => $phone,
            ], fn($v) => $v !== null);

            if (!empty($updateData)) {
                $this->contactRepository->update($contact['id'], $updateData);
            }

            $contactId = $contact['id'];
        } else {
            $contactId = $this->contactRepository->create([
                'first_name' => $nameParts['first_name'],
                'last_name' => $nameParts['last_name'],
                'email' => $email,
                'phone' => $phone,
                'source' => 'messaging_button',
                'source_ref' => !empty($data['page_url']) ? mb_substr($data['page_url'], 0, 100) : null,
                'status' => 'subscribed',
            ]);
        }

        // Auto-tag
        if (!empty($formSettings['auto_tag'])) {
            $this->contactRepository->addTag($contactId, $formSettings['auto_tag']);
        }

        // Auto-list (add tag that represents the list)
        if (!empty($formSettings['auto_list'])) {
            $list = $this->listRepository->find($formSettings['auto_list']);
            if ($list && !empty($list['tag_id'])) {
                $this->contactRepository->addTag($contactId, $list['tag_id']);
            }
        }

        return $contactId;
    }

    private function dispatchNotification(array $data, array $formSettings): void
    {
        $recipients = $formSettings['notification_recipients'] ?? [];
        $channel = $formSettings['channel'] ?? 'email';
        $gatewayId = $formSettings['gateway_id'] ?? null;

        if (empty($recipients)) {
            if ($channel !== 'email') {
                $authSettings = get_option(SettingsRepository::OPTION_KEY, []);
                $sitePhone = $authSettings['site_phone'] ?? '';
                $sitePhoneChannel = $authSettings['site_phone_channel'] ?? 'sms';
                if ($sitePhone) {
                    $recipients = [$sitePhone];
                    $channel = $sitePhoneChannel;
                }
            }
            if (empty($recipients)) {
                $recipients = [get_option('admin_email')];
                $channel = 'email';
            }
        }

        $senderName = $data['name'] ?? __('Anonymous', 'wp-sms');
        $senderEmail = $data['email'] ?? '';
        $senderPhone = $data['phone'] ?? '';
        $messageBody = $data['message'] ?? '';
        $pageUrl = $data['page_url'] ?? '';

        // Build body/subject once — they are the same for every recipient
        if ($channel === 'email') {
            $subject = sprintf(
                __('[%s] New message from %s', 'wp-sms'),
                get_bloginfo('name'),
                $senderName,
            );

            $body = sprintf(
                "%s\n\n---\n%s: %s\n%s: %s\n%s: %s\n%s: %s",
                $messageBody,
                __('Name', 'wp-sms'),
                $senderName,
                __('Email', 'wp-sms'),
                $senderEmail,
                __('Phone', 'wp-sms'),
                $senderPhone,
                __('Page', 'wp-sms'),
                $pageUrl,
            );
        } else {
            $subject = null;

            $body = sprintf(
                "%s: %s\n%s: %s\n%s\n---\n%s",
                __('From', 'wp-sms'),
                $senderName,
                __('Contact', 'wp-sms'),
                $senderEmail ?: $senderPhone,
                $pageUrl ? sprintf("%s: %s", __('Page', 'wp-sms'), $pageUrl) : '',
                $messageBody,
            );
        }

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (empty($recipient)) {
                continue;
            }

            $message = $channel === 'email'
                ? new EmailMessage($recipient, $body, $subject)
                : new Message($channel, $recipient, $body);

            $this->messageDispatcher->sendImmediate($message, $gatewayId);
        }
    }

    private function parseName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
        ];
    }
}
