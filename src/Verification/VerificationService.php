<?php

namespace WSms\Verification;

use WSms\Audit\AuditLogger;
use WSms\Enums\EventType;
use WSms\Enums\VerificationType;
use WSms\Mfa\OtpGenerator;
use WSms\Mfa\Support\EmailMasker;
use WSms\Mfa\Support\PhoneMasker;

defined('ABSPATH') || exit;

class VerificationService
{
    private const IDENTIFIER_RATE_LIMIT_PREFIX = 'wsms_vlimit_';
    private const IDENTIFIER_RATE_LIMIT_MAX = 3;
    private const IDENTIFIER_RATE_LIMIT_WINDOW = 900; // 15 minutes

    public function __construct(
        private OtpGenerator $otpGenerator,
        private VerificationSession $session,
        private AuditLogger $auditLogger,
        private VerificationConfig $config,
    ) {
    }

    /**
     * Send a verification code to the given channel+identifier.
     */
    public function sendCode(string $channel, string $identifier, ?string $sessionToken = null, ?int $userId = null): VerificationResult
    {
        if (!$this->config->isChannelEnabled($channel)) {
            return VerificationResult::failed('channel_disabled', ucfirst($channel) . ' verification is not enabled.');
        }

        $identifier = $this->normalizeIdentifier($channel, $identifier);

        if ($identifier === null) {
            return VerificationResult::failed('invalid_identifier', 'Invalid ' . $channel . ' address.');
        }

        // Check SMS gateway for phone channel.
        if ($channel === 'phone' && !has_action('wsms_send_sms')) {
            return VerificationResult::failed('no_sms_gateway', 'SMS sending is not configured.');
        }

        // Resolve or create session.
        $sessionData = null;
        if ($sessionToken !== null) {
            $sessionData = $this->session->validate($sessionToken);
        }

        $isNewSession = ($sessionData === null);

        if ($isNewSession) {
            $created = $this->session->create();
            $sessionToken = $created['token'];
            $sessionId = $created['session_id'];
        } else {
            $sessionId = $sessionData['session_id'];
        }

        // Per-identifier rate limiting (anti-bombing).
        $rateLimitKey = $this->identifierRateLimitKey($channel, $identifier);
        $identifierSendCount = (int) get_transient($rateLimitKey);
        if ($identifierSendCount >= self::IDENTIFIER_RATE_LIMIT_MAX) {
            return VerificationResult::rateLimited(self::IDENTIFIER_RATE_LIMIT_WINDOW);
        }

        $verifyType = VerificationType::forStandaloneChannel($channel)->value;
        $channelConfig = $this->config->getChannelConfig($channel);
        $cooldown = (int) ($channelConfig['cooldown'] ?? 60);

        // Skip cooldown/invalidation for new sessions — they have no prior records.
        if (!$isNewSession) {
            if ($this->isOnCooldown($sessionId, $verifyType, $cooldown)) {
                return VerificationResult::cooldown($cooldown);
            }

            $this->invalidatePending($sessionId, $verifyType);
        }

        // Generate and store OTP.
        $codeLength = (int) ($channelConfig['code_length'] ?? 6);
        $expiry = (int) ($channelConfig['expiry'] ?? 300);
        $maxAttempts = (int) ($channelConfig['max_attempts'] ?? 3);

        $otp = $this->otpGenerator->generate($codeLength);

        do_action('wsms_otp_generated', $userId, $otp, $verifyType);

        $hashed = $this->otpGenerator->hash($otp);

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'wsms_verifications', [
            'user_id'      => $userId,
            'session_id'   => $sessionId,
            'type'         => $verifyType,
            'identifier'   => $identifier,
            'code'         => $hashed,
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + $expiry),
            'created_at'   => current_time('mysql', true),
        ]);

        // Track per-identifier sends.
        set_transient($rateLimitKey, $identifierSendCount + 1, self::IDENTIFIER_RATE_LIMIT_WINDOW);

        // Deliver.
        if ($channel === 'phone') {
            do_action('wsms_send_sms', $identifier, sprintf('Your verification code is: %s', $otp));
        } elseif ($channel === 'email') {
            if (!VerificationMailer::sendOtp($identifier, $otp, $expiry)) {
                $this->auditLogger->log(EventType::StandaloneVerificationFailed, 'failure', $userId, [
                    'channel' => 'email',
                    'reason'  => 'mail_delivery_failed',
                ]);

                return VerificationResult::failed('mail_failed', 'Failed to send verification email. Please try again.');
            }
        }

        $masked = self::maskIdentifier($channel, $identifier);

        $this->auditLogger->log(EventType::StandaloneVerificationSent, 'success', $userId, [
            'channel'    => $channel,
            'identifier' => $masked,
            'session_id' => $sessionId,
        ]);

        return VerificationResult::codeSent($sessionToken, $masked, $expiry);
    }

    /**
     * Verify a code for the given channel+identifier.
     */
    public function verifyCode(string $channel, string $identifier, string $code, string $sessionToken, ?int $userId = null): VerificationResult
    {
        $sessionData = $this->session->validate($sessionToken);

        if ($sessionData === null) {
            return VerificationResult::failed('invalid_session', 'Session expired or invalid. Please request a new code.');
        }

        $sessionId = $sessionData['session_id'];
        $identifier = $this->normalizeIdentifier($channel, $identifier);

        if ($identifier === null) {
            return VerificationResult::failed('invalid_identifier', 'Invalid ' . $channel . ' address.');
        }

        $verifyType = VerificationType::forStandaloneChannel($channel)->value;

        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';

        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE session_id = %s AND type = %s AND identifier = %s AND used_at IS NULL ORDER BY created_at DESC LIMIT 1",
            $sessionId,
            $verifyType,
            $identifier,
        ));

        if (!$verification) {
            return VerificationResult::failed('no_verification', 'No pending verification found. Please request a new code.');
        }

        if (strtotime($verification->expires_at) < time()) {
            $this->auditLogger->log(EventType::StandaloneVerificationFailed, 'failure', $userId, [
                'channel' => $channel,
                'reason'  => 'expired',
            ]);

            return VerificationResult::failed('expired', 'Verification code has expired. Please request a new one.');
        }

        if ((int) $verification->attempts >= (int) $verification->max_attempts) {
            $this->auditLogger->log(EventType::StandaloneVerificationFailed, 'failure', $userId, [
                'channel' => $channel,
                'reason'  => 'max_attempts',
            ]);

            return VerificationResult::failed('max_attempts', 'Too many failed attempts. Please request a new code.');
        }

        $newAttempts = (int) $verification->attempts + 1;

        if (!$this->otpGenerator->verify($code, $verification->code)) {
            $wpdb->update($table, ['attempts' => $newAttempts], ['id' => $verification->id]);

            $this->auditLogger->log(EventType::StandaloneVerificationFailed, 'failure', $userId, [
                'channel'  => $channel,
                'attempts' => $newAttempts,
            ]);

            return VerificationResult::failed('invalid_code', 'Invalid verification code.');
        }

        // Atomic mark as used.
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET attempts = %d, used_at = %s WHERE id = %d AND used_at IS NULL",
            $newAttempts,
            gmdate('Y-m-d H:i:s'),
            $verification->id,
        ));

        if ($affected === 0) {
            return VerificationResult::failed('already_used', 'This code has already been used.');
        }

        // Mark verified in session (pass preloaded data to avoid re-reading transient).
        $this->session->markVerified($sessionId, $channel, $identifier, $sessionData);

        // If user provided, also update user meta.
        if ($userId !== null) {
            update_user_meta($userId, 'wsms_' . $channel . '_verified', '1');
        }

        do_action('wsms_identifier_verified', $channel, $identifier, $userId, $sessionId);

        $this->auditLogger->log(EventType::StandaloneVerificationSuccess, 'success', $userId, [
            'channel'    => $channel,
            'identifier' => self::maskIdentifier($channel, $identifier),
        ]);

        return VerificationResult::verified($sessionToken);
    }

    /**
     * Check if an identifier is verified in the given session.
     */
    public function isVerified(string $channel, string $identifier, string $sessionToken): bool
    {
        $sessionData = $this->session->validate($sessionToken);

        if ($sessionData === null) {
            return false;
        }

        $identifier = $this->normalizeIdentifier($channel, $identifier);

        if ($identifier === null) {
            return false;
        }

        return isset($sessionData['verified'][$channel][$identifier]);
    }

    /**
     * Normalize an identifier for consistent storage and lookup.
     */
    private function normalizeIdentifier(string $channel, string $identifier): ?string
    {
        if ($channel === 'email') {
            $email = strtolower(sanitize_email($identifier));

            return !empty($email) ? $email : null;
        }

        if ($channel === 'phone') {
            $phone = preg_replace('/[^\d+]/', '', sanitize_text_field($identifier));

            if (preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
                return $phone;
            }

            // Accept digits-only as local format.
            if (preg_match('/^\d{4,15}$/', $phone)) {
                return $phone;
            }

            return null;
        }

        return sanitize_text_field($identifier);
    }

    /**
     * Mask an identifier for display using canonical masking utilities.
     */
    private static function maskIdentifier(string $channel, string $identifier): string
    {
        return match ($channel) {
            'email' => EmailMasker::mask($identifier),
            'phone' => PhoneMasker::mask($identifier),
            default => '***',
        };
    }

    private function isOnCooldown(string $sessionId, string $verifyType, int $cooldownSeconds): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';
        $cutoff = gmdate('Y-m-d H:i:s', time() - $cooldownSeconds);

        return (bool) $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE session_id = %s AND type = %s AND used_at IS NULL AND created_at > %s LIMIT 1",
            $sessionId,
            $verifyType,
            $cutoff,
        ));
    }

    private function invalidatePending(string $sessionId, string $verifyType): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_verifications';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET used_at = %s WHERE session_id = %s AND type = %s AND used_at IS NULL",
            gmdate('Y-m-d H:i:s'),
            $sessionId,
            $verifyType,
        ));
    }

    private function identifierRateLimitKey(string $channel, string $identifier): string
    {
        return self::IDENTIFIER_RATE_LIMIT_PREFIX . hash('sha256', $channel . ':' . $identifier);
    }
}
