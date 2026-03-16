<?php

namespace WSms\Verification;

use WSms\Audit\AuditLogger;
use WSms\Enums\EventType;
use WSms\Enums\VerificationType;
use WSms\Messaging\Contracts\TemplateEngineInterface;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\SmsMessage;
use WSms\Messaging\OtpEmailBuilder;
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
        private MessageDispatcher $messageDispatcher,
        private TemplateEngineInterface $templateEngine,
        private VerificationRepository $verificationRepo,
    ) {
    }

    /**
     * Send a verification code to the given channel+identifier.
     */
    public function sendCode(string $channel, string $identifier, ?string $sessionToken = null, ?int $userId = null): VerificationResult
    {
        if (!$this->config->isChannelEnabled($channel)) {
            return VerificationResult::failed('channel_disabled', sprintf(__('%s verification is not enabled.', 'wp-sms'), ucfirst($channel)));
        }

        $identifier = $this->normalizeIdentifier($channel, $identifier);

        if ($identifier === null) {
            return VerificationResult::failed('invalid_identifier', sprintf(__('Invalid %s address.', 'wp-sms'), $channel));
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

        $this->verificationRepo->insert([
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
            $smsBody = $this->templateEngine->render(
                __('Your code: {{code}}. Expires in {{minutes}} min.', 'wp-sms'),
                ['code' => $otp, 'minutes' => (int) ceil($expiry / 60)],
            );
            $this->messageDispatcher->sendImmediate(new SmsMessage($identifier, $smsBody));
        } elseif ($channel === 'email') {
            $result = $this->messageDispatcher->sendImmediate(
                OtpEmailBuilder::build($identifier, $otp, $expiry)
            );

            if (!$result->success) {
                $this->auditLogger->log(EventType::StandaloneVerificationFailed, 'failure', $userId, [
                    'channel' => 'email',
                    'reason'  => 'mail_delivery_failed',
                ]);

                return VerificationResult::failed('mail_failed', __('Failed to send verification email. Please try again.', 'wp-sms'));
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
            return VerificationResult::failed('invalid_session', __('Session expired or invalid. Please request a new code.', 'wp-sms'));
        }

        $sessionId = $sessionData['session_id'];
        $identifier = $this->normalizeIdentifier($channel, $identifier);

        if ($identifier === null) {
            return VerificationResult::failed('invalid_identifier', sprintf(__('Invalid %s address.', 'wp-sms'), $channel));
        }

        $verifyType = VerificationType::forStandaloneChannel($channel)->value;

        $verification = $this->verificationRepo->findLatestPending([
            'session_id' => $sessionId,
            'type'       => $verifyType,
            'identifier' => $identifier,
        ]);

        if (!$verification) {
            return VerificationResult::failed('no_verification', __('No pending verification found. Please request a new code.', 'wp-sms'));
        }

        if (strtotime($verification->expires_at) < time()) {
            $this->auditLogger->log(EventType::StandaloneVerificationFailed, 'failure', $userId, [
                'channel' => $channel,
                'reason'  => 'expired',
            ]);

            return VerificationResult::failed('expired', __('Verification code has expired. Please request a new one.', 'wp-sms'));
        }

        if ((int) $verification->attempts >= (int) $verification->max_attempts) {
            $this->auditLogger->log(EventType::StandaloneVerificationFailed, 'failure', $userId, [
                'channel' => $channel,
                'reason'  => 'max_attempts',
            ]);

            return VerificationResult::failed('max_attempts', __('Too many failed attempts. Please request a new code.', 'wp-sms'));
        }

        $newAttempts = (int) $verification->attempts + 1;

        if (!$this->otpGenerator->verify($code, $verification->code)) {
            $this->verificationRepo->updateAttempts((int) $verification->id, $newAttempts);

            $this->auditLogger->log(EventType::StandaloneVerificationFailed, 'failure', $userId, [
                'channel'  => $channel,
                'attempts' => $newAttempts,
            ]);

            return VerificationResult::failed('invalid_code', __('Invalid verification code.', 'wp-sms'));
        }

        // Atomic mark as used.
        if (!$this->verificationRepo->markUsedWithAttempts((int) $verification->id, $newAttempts)) {
            return VerificationResult::failed('already_used', __('This code has already been used.', 'wp-sms'));
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
        return $this->verificationRepo->hasPendingWithinCooldown([
            'session_id' => $sessionId,
            'type'       => $verifyType,
        ], $cooldownSeconds);
    }

    private function invalidatePending(string $sessionId, string $verifyType): void
    {
        $this->verificationRepo->invalidatePending([
            'session_id' => $sessionId,
            'type'       => $verifyType,
        ]);
    }

    private function identifierRateLimitKey(string $channel, string $identifier): string
    {
        return self::IDENTIFIER_RATE_LIMIT_PREFIX . hash('sha256', $channel . ':' . $identifier);
    }
}
