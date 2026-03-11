<?php

namespace WSms\Mfa\Channels;

use WSms\Audit\AuditLogger;
use WSms\Enums\EventType;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\OtpGenerator;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;

defined('ABSPATH') || exit;

abstract class AbstractOtpChannel implements ChannelInterface
{
    use HasUserFactor;

    public function __construct(
        protected OtpGenerator $otpGenerator,
        protected AuditLogger $auditLogger,
    ) {
    }

    abstract public function getId(): string;

    abstract public function getName(): string;

    abstract public function supportsPrimaryAuth(): bool;

    abstract public function supportsMfa(): bool;

    /**
     * Deliver the OTP to the user via the channel-specific transport.
     */
    abstract protected function deliver(int $userId, string $code, string $identifier): bool;

    /**
     * Get the destination identifier (phone number or email) for the user.
     */
    abstract protected function getIdentifier(int $userId): ?string;

    /**
     * Mask the identifier for display.
     */
    abstract protected function maskIdentifier(string $identifier): string;

    /** {@inheritDoc} */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult
    {
        if (!$this->isEnrolled($userId)) {
            return new ChallengeResult(false, 'User is not enrolled in this channel.');
        }

        $identifier = $this->getIdentifier($userId);

        if ($identifier === null) {
            return new ChallengeResult(false, 'No identifier found for user.');
        }

        // Check cooldown.
        $cooldown = (int) $this->getConfigValue('cooldown', 60);

        if ($this->hasCooldownActive($userId, $cooldown)) {
            return new ChallengeResult(false, 'Please wait before requesting a new code.');
        }

        $expiry = (int) $this->getConfigValue('expiry', 300);

        $delivered = $this->createAndDeliverOtp($userId, $identifier, $expiry);

        if (!$delivered) {
            $this->auditLogger->log(EventType::OtpSent, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return new ChallengeResult(false, 'Failed to deliver the verification code.');
        }

        $this->auditLogger->log(EventType::OtpSent, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new ChallengeResult(true, 'Verification code sent.', [
            'masked_identifier' => $this->maskIdentifier($identifier),
            'expires_in'        => $expiry,
        ]);
    }

    /** {@inheritDoc} */
    public function verify(int $userId, string $code, array $context = []): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d AND channel_id = %s AND used_at IS NULL
             ORDER BY created_at DESC LIMIT 1",
            $userId,
            $this->getId(),
        ));

        if (!$verification) {
            return false;
        }

        if (strtotime($verification->expires_at) < time()) {
            $this->auditLogger->log(EventType::OtpExpired, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return false;
        }

        // Atomic attempt increment — fails if max attempts reached.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET attempts = attempts + 1
             WHERE id = %d AND attempts < max_attempts",
            $verification->id,
        ));

        if ($wpdb->rows_affected === 0) {
            $this->auditLogger->log(EventType::OtpFailed, 'failure', $userId, [
                'channel' => $this->getId(),
                'reason'  => 'max_attempts_exceeded',
            ]);

            return false;
        }

        if (!$this->otpGenerator->verify($code, $verification->code)) {
            $this->auditLogger->log(EventType::OtpFailed, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return false;
        }

        // Mark as used.
        $wpdb->update(
            $table,
            ['used_at' => current_time('mysql', true)],
            ['id' => $verification->id],
        );

        $this->auditLogger->log(EventType::OtpVerified, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return true;
    }

    /** {@inheritDoc} */
    public function getEnrollmentInfo(int $userId): array
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return ['enrolled' => false];
        }

        $identifier = $this->getIdentifier($userId);

        return [
            'enrolled'   => $factor->status === \WSms\Enums\ChannelStatus::Active,
            'status'     => $factor->status->value,
            'channel'    => $this->getId(),
            'identifier' => $identifier ? $this->maskIdentifier($identifier) : null,
            'created_at' => $factor->createdAt,
        ];
    }

    /**
     * Generate, store, and deliver an OTP. Used by both sendChallenge() and
     * enrollment flows.
     */
    protected function createAndDeliverOtp(int $userId, string $identifier, int $expiry): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        // Invalidate existing pending verifications.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET used_at = NOW()
             WHERE user_id = %d AND channel_id = %s AND used_at IS NULL",
            $userId,
            $this->getId(),
        ));

        $codeLength = (int) $this->getConfigValue('code_length', 6);
        $code = $this->otpGenerator->generate($codeLength);
        $hashedCode = $this->otpGenerator->hash($code);
        $maxAttempts = (int) $this->getConfigValue('max_attempts', 5);

        $wpdb->insert($table, [
            'user_id'      => $userId,
            'type'         => 'otp',
            'channel_id'   => $this->getId(),
            'identifier'   => $identifier,
            'code'         => $hashedCode,
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + $expiry),
            'created_at'   => current_time('mysql', true),
        ]);

        return $this->deliver($userId, $code, $identifier);
    }

    /**
     * Check if a cooldown is active for this user/channel.
     */
    private function hasCooldownActive(int $userId, int $cooldown): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        return (bool) $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE user_id = %d AND channel_id = %s AND used_at IS NULL
               AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)
             ORDER BY created_at DESC LIMIT 1",
            $userId,
            $this->getId(),
            $cooldown,
        ));
    }
}
