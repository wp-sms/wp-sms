<?php

namespace WSms\Verification;

defined('ABSPATH') || exit;

class OtpService
{
    public function __construct(
        private VerificationRepository $verificationRepo,
        private OtpGenerator $otpGenerator,
    ) {
    }

    /**
     * Generate OTP, store it, fire hook, return plaintext code.
     */
    public function createOtp(
        int $userId,
        string $type,
        string $identifier,
        int $codeLength,
        int $expiry,
        int $maxAttempts,
        ?string $channelId = null,
        ?string $sessionId = null,
    ): string {
        $code = $this->otpGenerator->generate($codeLength);

        do_action('wsms_otp_generated', $userId, $code, $type);

        $hashed = $this->otpGenerator->hash($code);

        $data = [
            'user_id'      => $userId,
            'type'         => $type,
            'identifier'   => $identifier,
            'code'         => $hashed,
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + $expiry),
            'created_at'   => current_time('mysql', true),
        ];

        if ($channelId !== null) {
            $data['channel_id'] = $channelId;
        }

        if ($sessionId !== null) {
            $data['session_id'] = $sessionId;
        }

        $this->verificationRepo->insert($data);

        return $code;
    }

    /**
     * Create token-based verification (magic links, password reset, email verify).
     *
     * @return string The plaintext token (caller is responsible for building URLs).
     */
    public function createToken(
        int $userId,
        string $type,
        string $identifier,
        int $expiry,
        ?string $channelId = null,
    ): string {
        $token = $this->otpGenerator->generateToken();
        $hashedToken = $this->otpGenerator->hash($token);

        $data = [
            'user_id'      => $userId,
            'type'         => $type,
            'identifier'   => $identifier,
            'code'         => $hashedToken,
            'attempts'     => 0,
            'max_attempts' => 1,
            'expires_at'   => gmdate('Y-m-d H:i:s', time() + $expiry),
            'created_at'   => current_time('mysql', true),
        ];

        if ($channelId !== null) {
            $data['channel_id'] = $channelId;
        }

        $this->verificationRepo->insert($data);

        return $token;
    }

    /**
     * Verify an OTP code.
     *
     * @return object|null The verification row on success, null on failure.
     */
    public function verifyOtp(string $code, array $where): ?object
    {
        $result = $this->verifyOtpDetailed($code, $where);

        return $result['verification'];
    }

    /**
     * Verify an OTP code with detailed error information.
     *
     * @return array{verification: ?object, error: ?string}
     */
    public function verifyOtpDetailed(string $code, array $where): array
    {
        $verification = $this->verificationRepo->findLatestPending($where);

        if (!$verification) {
            return ['verification' => null, 'error' => 'no_verification'];
        }

        if (strtotime($verification->expires_at) < time()) {
            return ['verification' => null, 'error' => 'expired'];
        }

        if ((int) $verification->attempts >= (int) $verification->max_attempts) {
            return ['verification' => null, 'error' => 'max_attempts'];
        }

        $newAttempts = (int) $verification->attempts + 1;

        if (!$this->otpGenerator->verify($code, $verification->code)) {
            $this->verificationRepo->updateAttempts((int) $verification->id, $newAttempts);

            return ['verification' => null, 'error' => 'invalid_code'];
        }

        $this->verificationRepo->markUsedWithAttempts((int) $verification->id, $newAttempts);

        return ['verification' => $verification, 'error' => null];
    }

    /**
     * Look up a token verification record without any validation.
     */
    public function findTokenVerification(string $token, string $type): ?object
    {
        $hashedToken = $this->otpGenerator->hash($token);

        return $this->verificationRepo->findByCode($hashedToken, $type);
    }

    /**
     * Verify a token and return the verification row (does NOT consume it).
     * Returns null if not found, expired, or already used.
     */
    public function verifyToken(string $token, string $type): ?object
    {
        $verification = $this->findTokenVerification($token, $type);

        if (!$verification) {
            return null;
        }

        if (strtotime($verification->expires_at) < time()) {
            return null;
        }

        if ($verification->used_at !== null) {
            return null;
        }

        return $verification;
    }

    /**
     * Verify a token and mark it as used in one operation.
     *
     * @return object|null The verification row on success, null if invalid/expired/used.
     */
    public function consumeToken(string $token, string $type): ?object
    {
        $result = $this->consumeTokenDetailed($token, $type);

        return $result['verification'];
    }

    /**
     * Verify and consume a token with detailed error information.
     * Single DB lookup — no double-query on failure.
     *
     * @return array{verification: ?object, error: ?string}
     */
    public function consumeTokenDetailed(string $token, string $type): array
    {
        $verification = $this->findTokenVerification($token, $type);

        if ($verification === null) {
            return ['verification' => null, 'error' => 'invalid_token'];
        }

        if (strtotime($verification->expires_at) < time()) {
            return ['verification' => null, 'error' => 'expired_token'];
        }

        if ($verification->used_at !== null) {
            return ['verification' => null, 'error' => 'used_token'];
        }

        $this->verificationRepo->markUsed((int) $verification->id);

        return ['verification' => $verification, 'error' => null];
    }

    public function isOnCooldown(array $where, int $cooldownSeconds): bool
    {
        return $this->verificationRepo->hasPendingWithinCooldown($where, $cooldownSeconds);
    }

    public function invalidatePending(array $where): void
    {
        $this->verificationRepo->invalidatePending($where);
    }
}
