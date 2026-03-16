<?php

namespace WSms\Mfa\Channels;

use WSms\Auth\AccountManager;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Audit\AuditLogger;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\SmsMessage;
use WSms\Mfa\Contracts\SupportsEnrollmentConfirmation;
use WSms\Mfa\Contracts\SupportsTokenVerification;
use WSms\Mfa\OtpGenerator;
use WSms\Mfa\Support\PhoneMasker;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

class PhoneChannel extends AbstractOtpChannel implements SupportsTokenVerification, SupportsEnrollmentConfirmation
{
    private MagicLinkChannel $magicLink;

    public function __construct(
        OtpGenerator $otpGenerator,
        AuditLogger $auditLogger,
        MessageDispatcher $messageDispatcher,
        MagicLinkChannel $magicLink,
        VerificationRepository $verificationRepo,
        ?OtpService $otpService = null,
    ) {
        parent::__construct($otpGenerator, $auditLogger, $messageDispatcher, $verificationRepo, $otpService);
        $this->magicLink = $magicLink;
    }

    public function getId(): string
    {
        return 'phone';
    }

    public function getName(): string
    {
        return 'Phone';
    }

    public function supportsPrimaryAuth(): bool
    {
        return true;
    }

    public function supportsMfa(): bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function enroll(int $userId, array $data): EnrollmentResult
    {
        $phone = $data['phone'] ?? '';

        if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            return new EnrollmentResult(false, __('Invalid phone number. Use E.164 format (e.g. +12025551234).', 'wp-sms'));
        }

        if (AccountManager::isPhoneTaken($phone, $userId)) {
            return new EnrollmentResult(false, __('This phone number is already associated with another account.', 'wp-sms'));
        }

        $existing = $this->getFactor($userId);

        if ($existing && $existing->status === ChannelStatus::Active) {
            return new EnrollmentResult(false, __('Already enrolled in Phone.', 'wp-sms'));
        }

        if ($existing) {
            $this->updateFactor($existing->id, [
                'status' => ChannelStatus::Pending->value,
                'meta'   => wp_json_encode(['phone' => $phone]),
            ]);
        } else {
            $this->createFactor($userId, ChannelStatus::Pending, ['phone' => $phone]);
        }

        // Send verification OTP via shared helper.
        $expiry = (int) $this->getConfigValue('expiry', 300);
        $delivered = $this->createAndDeliverOtp($userId, $phone, $expiry);

        if (!$delivered) {
            return new EnrollmentResult(false, __('Failed to send verification code.', 'wp-sms'));
        }

        return new EnrollmentResult(true, __('Verification code sent to your phone.', 'wp-sms'), [
            'requires_confirmation' => true,
            'masked_phone'          => PhoneMasker::mask($phone),
        ]);
    }

    /**
     * Confirm enrollment by verifying the OTP sent during enroll().
     */
    public function confirmEnrollment(int $userId, string $code): EnrollmentResult
    {
        $factor = $this->getFactor($userId);

        if (!$factor || $factor->status !== ChannelStatus::Pending) {
            return new EnrollmentResult(false, __('No pending enrollment found.', 'wp-sms'));
        }

        $verified = $this->verify($userId, $code);

        if (!$verified) {
            return new EnrollmentResult(false, __('Invalid or expired verification code.', 'wp-sms'));
        }

        $phone = $factor->meta['phone'] ?? '';

        // Activate factor.
        $this->updateFactor($factor->id, [
            'status' => ChannelStatus::Active->value,
        ]);

        // Sync phone meta.
        update_user_meta($userId, 'wsms_phone', $phone);
        update_user_meta($userId, 'wsms_phone_verified', '1');

        $this->auditLogger->log(EventType::MfaEnrolled, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new EnrollmentResult(true, __('Phone enrollment confirmed.', 'wp-sms'));
    }

    /** {@inheritDoc} */
    public function unenroll(int $userId): bool
    {
        $result = parent::unenroll($userId);

        if ($result) {
            delete_user_meta($userId, 'wsms_phone');
            delete_user_meta($userId, 'wsms_phone_verified');
            delete_user_meta($userId, 'wsms_pending_phone');
        }

        return $result;
    }

    /**
     * Send challenge with OTP, magic link, or both based on verification_methods setting.
     */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult
    {
        $verificationMethods = (array) $this->getConfigValue('verification_methods', ['otp']);
        $hasOtp = in_array('otp', $verificationMethods, true);
        $hasMagicLink = in_array('magic_link', $verificationMethods, true);

        if (!$hasOtp && !$hasMagicLink) {
            return new ChallengeResult(false, __('No verification methods enabled for phone channel.', 'wp-sms'));
        }

        $prereq = $this->validateChallengePrerequisites($userId);

        if (!$prereq->success) {
            return $prereq;
        }

        $identifier = $prereq->meta['identifier'];
        $expiry = (int) $this->getConfigValue('expiry', 300);
        $otpCode = null;
        $magicLinkUrl = null;

        // Generate OTP if enabled.
        if ($hasOtp) {
            $otpCode = $this->generateAndStoreOtp($userId, $identifier, $expiry);
        }

        // Generate magic link if enabled.
        if ($hasMagicLink) {
            $magicLinkUrl = $this->magicLink->generateToken($userId, $identifier, $expiry);
        }

        // Send combined SMS.
        $sent = $this->deliverCombined($identifier, $otpCode, $magicLinkUrl, $expiry);

        if (!$sent) {
            $this->auditLogger->log(EventType::OtpSent, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return new ChallengeResult(false, __('Failed to deliver the verification SMS.', 'wp-sms'));
        }

        $this->auditLogger->log(EventType::OtpSent, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new ChallengeResult(true, __('Verification code sent.', 'wp-sms'), [
            'masked_identifier' => $this->maskIdentifier($identifier),
            'expires_in'        => $expiry,
            'has_otp'           => $hasOtp,
            'has_magic_link'    => $hasMagicLink,
        ]);
    }

    /**
     * Verify a magic link token and resolve the user.
     */
    public function verifyTokenAndResolveUser(string $token): ?int
    {
        return $this->magicLink->verifyTokenAndResolveUser($token);
    }

    /** {@inheritDoc} */
    protected function deliver(int $userId, string $code, string $identifier): bool
    {
        $message = sprintf(
            __('Your verification code is: %s. It expires in %d minutes.', 'wp-sms'),
            $code,
            (int) ($this->getConfigValue('expiry', 300) / 60),
        );

        $result = $this->messageDispatcher->sendImmediate(new SmsMessage($identifier, $message));

        return $result->success;
    }

    /** {@inheritDoc} */
    protected function getIdentifier(int $userId): ?string
    {
        $factor = $this->getFactor($userId);

        if ($factor && !empty($factor->meta['phone'])) {
            return $factor->meta['phone'];
        }

        $phone = get_user_meta($userId, 'wsms_phone', true);

        return $phone ?: null;
    }

    /** {@inheritDoc} */
    protected function maskIdentifier(string $identifier): string
    {
        return PhoneMasker::mask($identifier);
    }

    /** {@inheritDoc} */
    protected function getConfigPrefix(): string
    {
        return 'phone';
    }

    /**
     * Send a single SMS containing OTP code, magic link, or both.
     */
    private function deliverCombined(string $identifier, ?string $otpCode, ?string $magicLinkUrl, int $expiry): bool
    {
        $parts = [];

        if ($otpCode !== null) {
            $parts[] = sprintf(__('Your verification code is: %s.', 'wp-sms'), $otpCode);
        }

        if ($magicLinkUrl !== null) {
            $parts[] = sprintf(__('Or log in: %s', 'wp-sms'), $magicLinkUrl);
        }

        $parts[] = sprintf(__('Expires in %d minutes.', 'wp-sms'), (int) ($expiry / 60));

        $body = implode(' ', $parts);

        $result = $this->messageDispatcher->sendImmediate(new SmsMessage($identifier, $body));

        return $result->success;
    }
}
