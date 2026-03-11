<?php

namespace WSms\Mfa\Channels;

use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\Support\EmailMasker;
use WSms\Mfa\ValueObjects\EnrollmentResult;

defined('ABSPATH') || exit;

class EmailOtpChannel extends AbstractOtpChannel
{
    public function getId(): string
    {
        return 'email_otp';
    }

    public function getName(): string
    {
        return 'Email OTP';
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
        $email = $this->getIdentifier($userId);

        if ($email === null) {
            return new EnrollmentResult(false, 'No email address found for user.');
        }

        $existing = $this->getFactor($userId);

        if ($existing && $existing->status === ChannelStatus::Active) {
            return new EnrollmentResult(false, 'Already enrolled in Email OTP.');
        }

        // Auto-enroll with Active status — WP email is already verified.
        if ($existing) {
            $this->updateFactor($existing->id, [
                'status' => ChannelStatus::Active->value,
                'meta'   => wp_json_encode(['email' => $email]),
            ]);
        } else {
            $this->createFactor($userId, ChannelStatus::Active, ['email' => $email]);
        }

        $this->auditLogger->log(EventType::MfaEnrolled, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new EnrollmentResult(true, 'Email OTP enrollment complete.', [
            'masked_email' => EmailMasker::mask($email),
        ]);
    }

    /** {@inheritDoc} */
    protected function deliver(int $userId, string $code, string $identifier): bool
    {
        $siteName = get_bloginfo('name');
        $expiry = (int) ($this->getConfigValue('expiry', 300) / 60);

        $subject = sprintf('[%s] Your verification code', $siteName);

        $body = sprintf(
            '<p>Your verification code is: <strong>%s</strong></p>'
            . '<p>This code expires in %d minutes.</p>'
            . '<p>If you did not request this code, please ignore this email.</p>',
            $code,
            $expiry,
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail($identifier, $subject, $body, $headers);
    }

    /** {@inheritDoc} */
    protected function getIdentifier(int $userId): ?string
    {
        $user = get_userdata($userId);

        if (!$user || empty($user->user_email)) {
            return null;
        }

        return $user->user_email;
    }

    /** {@inheritDoc} */
    protected function maskIdentifier(string $identifier): string
    {
        return EmailMasker::mask($identifier);
    }

    /** {@inheritDoc} */
    protected function getConfigPrefix(): string
    {
        return 'otp_email';
    }
}
