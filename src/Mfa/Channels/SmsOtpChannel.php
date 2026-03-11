<?php

namespace WSms\Mfa\Channels;

use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\Support\PhoneMasker;
use WSms\Mfa\ValueObjects\EnrollmentResult;

defined('ABSPATH') || exit;

class SmsOtpChannel extends AbstractOtpChannel
{
    public function getId(): string
    {
        return 'sms';
    }

    public function getName(): string
    {
        return 'SMS OTP';
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
            return new EnrollmentResult(false, 'Invalid phone number. Use E.164 format (e.g. +12025551234).');
        }

        // Create pending factor.
        $existing = $this->getFactor($userId);

        if ($existing && $existing->status === ChannelStatus::Active) {
            return new EnrollmentResult(false, 'Already enrolled in SMS OTP.');
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
            return new EnrollmentResult(false, 'Failed to send verification code.');
        }

        return new EnrollmentResult(true, 'Verification code sent to your phone.', [
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
            return new EnrollmentResult(false, 'No pending enrollment found.');
        }

        $verified = $this->verify($userId, $code);

        if (!$verified) {
            return new EnrollmentResult(false, 'Invalid or expired verification code.');
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

        return new EnrollmentResult(true, 'SMS OTP enrollment confirmed.');
    }

    /** {@inheritDoc} */
    public function unenroll(int $userId): bool
    {
        $result = parent::unenroll($userId);

        if ($result) {
            delete_user_meta($userId, 'wsms_phone');
            delete_user_meta($userId, 'wsms_phone_verified');
        }

        return $result;
    }

    /** {@inheritDoc} */
    protected function deliver(int $userId, string $code, string $identifier): bool
    {
        $message = sprintf(
            'Your verification code is: %s. It expires in %d minutes.',
            $code,
            (int) ($this->getConfigValue('expiry', 300) / 60),
        );

        do_action('wsms_send_sms', $identifier, $message);

        return true;
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
        return 'otp_sms';
    }
}
