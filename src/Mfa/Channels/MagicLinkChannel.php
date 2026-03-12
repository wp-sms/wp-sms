<?php

namespace WSms\Mfa\Channels;

use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\OtpGenerator;
use WSms\Mfa\Support\EmailMasker;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;

defined('ABSPATH') || exit;

/**
 * Internal magic link implementation — used as a delegate by EmailChannel and PhoneChannel.
 * Not registered as a standalone channel in MfaManager.
 */
class MagicLinkChannel implements ChannelInterface
{
    use HasUserFactor;

    public function __construct(
        private OtpGenerator $otpGenerator,
        private AuditLogger $auditLogger,
    ) {
    }

    public function getId(): string
    {
        return 'magic_link';
    }

    public function getName(): string
    {
        return 'Magic Link';
    }

    public function supportsPrimaryAuth(): bool
    {
        return true;
    }

    public function supportsMfa(): bool
    {
        return false;
    }

    protected function getConfigPrefix(): string
    {
        return 'magic_link';
    }

    /** {@inheritDoc} */
    public function enroll(int $userId, array $data): EnrollmentResult
    {
        $user = get_userdata($userId);

        if (!$user || empty($user->user_email)) {
            return new EnrollmentResult(false, 'No email address found for user.');
        }

        $existing = $this->getFactor($userId);

        if ($existing && $existing->status === ChannelStatus::Active) {
            return new EnrollmentResult(false, 'Already enrolled in Magic Link.');
        }

        if ($existing) {
            $this->updateFactor($existing->id, [
                'status' => ChannelStatus::Active->value,
                'meta'   => wp_json_encode(['email' => $user->user_email]),
            ]);
        } else {
            $this->createFactor($userId, ChannelStatus::Active, ['email' => $user->user_email]);
        }

        $this->auditLogger->log(EventType::MfaEnrolled, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new EnrollmentResult(true, 'Magic Link enrollment complete.', [
            'masked_email' => EmailMasker::mask($user->user_email),
        ]);
    }

    /** {@inheritDoc} */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult
    {
        global $wpdb;

        if (!$this->isEnrolled($userId)) {
            return new ChallengeResult(false, 'User is not enrolled in Magic Link.');
        }

        $factor = $this->getFactor($userId);
        $email = $factor->meta['email'] ?? null;

        if ($email === null) {
            return new ChallengeResult(false, 'No email address found for user.');
        }

        $table = $wpdb->prefix . 'wsms_verifications';

        // Check cooldown.
        $cooldown = (int) ($this->getConfigValue('cooldown', 60) ?: 60);

        $recent = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE user_id = %d AND channel_id = %s AND used_at IS NULL
               AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)
             ORDER BY created_at DESC LIMIT 1",
            $userId,
            $this->getId(),
            $cooldown,
        ));

        if ($recent) {
            return new ChallengeResult(false, 'Please wait before requesting a new magic link.');
        }

        // Invalidate existing pending.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET used_at = NOW()
             WHERE user_id = %d AND channel_id = %s AND used_at IS NULL",
            $userId,
            $this->getId(),
        ));

        // Generate token.
        $token = $this->otpGenerator->generateToken(32);
        $hashedToken = $this->otpGenerator->hash($token);
        $expiry = (int) ($this->getConfigValue('expiry', 600) ?: 600);

        $wpdb->insert($table, [
            'user_id'      => $userId,
            'type'         => 'magic_link',
            'channel_id'   => $this->getId(),
            'identifier'   => $email,
            'code'         => $hashedToken,
            'attempts'     => 0,
            'max_attempts' => 1,
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + $expiry),
            'created_at'   => current_time('mysql', true),
        ]);

        $url = get_site_url() . '/account/verify-magic-link?token=' . $token;

        $siteName = get_bloginfo('name');
        $expiryMinutes = (int) ($expiry / 60);

        $subject = sprintf('[%s] Your login link', $siteName);

        $body = sprintf(
            '<p>Click the link below to log in:</p>'
            . '<p><a href="%s">Log in to %s</a></p>'
            . '<p>This link expires in %d minutes.</p>'
            . '<p>If you did not request this, please ignore this email.</p>',
            esc_url($url),
            esc_html($siteName),
            $expiryMinutes,
        );

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($email, $subject, $body, $headers);

        if (!$sent) {
            $this->auditLogger->log(EventType::MagicLinkSent, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return new ChallengeResult(false, 'Failed to send magic link email.');
        }

        $this->auditLogger->log(EventType::MagicLinkSent, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new ChallengeResult(true, 'Magic link sent to your email.', [
            'masked_email' => EmailMasker::mask($email),
            'expires_in'   => $expiry,
        ]);
    }

    /**
     * Generate a magic link token and store it, returning the full URL.
     * Used by parent channels (EmailChannel, PhoneChannel) for combined messages.
     */
    public function generateToken(int $userId, string $identifier, int $expiry): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';

        $token = $this->otpGenerator->generateToken(32);
        $hashedToken = $this->otpGenerator->hash($token);

        $wpdb->insert($table, [
            'user_id'      => $userId,
            'type'         => 'magic_link',
            'channel_id'   => $this->getId(),
            'identifier'   => $identifier,
            'code'         => $hashedToken,
            'attempts'     => 0,
            'max_attempts' => 1,
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + $expiry),
            'created_at'   => current_time('mysql', true),
        ]);

        $this->auditLogger->log(EventType::MagicLinkSent, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return get_site_url() . '/account/verify-magic-link?token=' . $token;
    }

    /**
     * Verify a magic link token and resolve the user ID.
     */
    public function verifyTokenAndResolveUser(string $token): ?int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_verifications';
        $hashedToken = $this->otpGenerator->hash($token);

        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE channel_id = %s AND code = %s AND used_at IS NULL
             LIMIT 1",
            $this->getId(),
            $hashedToken,
        ));

        if (!$verification) {
            return null;
        }

        if (strtotime($verification->expires_at) < time()) {
            return null;
        }

        // Mark as used.
        $wpdb->update(
            $table,
            ['used_at' => current_time('mysql', true)],
            ['id' => $verification->id],
        );

        $userId = (int) $verification->user_id;

        $this->auditLogger->log(EventType::MagicLinkVerified, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return $userId;
    }

    /** {@inheritDoc} */
    public function verify(int $userId, string $code, array $context = []): bool
    {
        return $this->verifyTokenAndResolveUser($code) === $userId;
    }

    /** {@inheritDoc} */
    public function getEnrollmentInfo(int $userId): array
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return ['enrolled' => false];
        }

        $email = $factor->meta['email'] ?? null;

        return [
            'enrolled'   => $factor->status === ChannelStatus::Active,
            'status'     => $factor->status->value,
            'channel'    => $this->getId(),
            'identifier' => $email ? EmailMasker::mask($email) : null,
            'created_at' => $factor->createdAt,
        ];
    }
}
