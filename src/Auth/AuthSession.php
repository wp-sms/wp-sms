<?php

namespace WSms\Auth;

use WSms\Enums\SessionStage;
use WSms\Mfa\OtpGenerator;

defined('ABSPATH') || exit;

class AuthSession
{
    private const TRANSIENT_PREFIX = 'wsms_auth_session_';
    private const DEFAULT_TTL = 600; // 10 minutes

    public function __construct(
        private OtpGenerator $otpGenerator,
    ) {
    }

    /**
     * Create a new auth session after primary auth succeeds.
     *
     * @return string HMAC-signed session token for the client.
     */
    public function create(int $userId, string $method, SessionStage $stage, array $context = []): string
    {
        $sessionKey = $this->otpGenerator->generateToken(16);

        $data = [
            'user_id'    => $userId,
            'method'     => $method,
            'stage'      => $stage->value,
            'ip'         => $context['ip'] ?? '',
            'channel_id' => $context['channel_id'] ?? null,
            'created_at' => time(),
        ];

        set_transient(self::TRANSIENT_PREFIX . $sessionKey, $data, self::DEFAULT_TTL);

        $expiry = time() + self::DEFAULT_TTL;
        $payload = $userId . '|' . $sessionKey . '|' . $expiry;
        $signature = hash_hmac('sha256', $payload, $this->getSigningKey());

        return base64_encode($payload . '|' . $signature);
    }

    /**
     * Validate a session token and return the session data.
     *
     * @return array|null Session data with 'session_key' appended, or null if invalid.
     */
    public function validate(string $token): ?array
    {
        $decoded = base64_decode($token, true);

        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);

        if (count($parts) !== 4) {
            return null;
        }

        [$userId, $sessionKey, $expiry, $signature] = $parts;

        $expectedPayload = $userId . '|' . $sessionKey . '|' . $expiry;
        $expectedSignature = hash_hmac('sha256', $expectedPayload, $this->getSigningKey());

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        if ((int) $expiry < time()) {
            return null;
        }

        $data = get_transient(self::TRANSIENT_PREFIX . $sessionKey);

        if ($data === false) {
            return null;
        }

        if ($data['user_id'] !== (int) $userId) {
            return null;
        }

        $data['session_key'] = $sessionKey;

        return $data;
    }

    /**
     * Update fields in an existing session, preserving the original TTL.
     *
     * Validates stage transitions when a 'stage' update is included.
     *
     * @throws \InvalidArgumentException If the stage transition is invalid.
     */
    public function update(string $sessionKey, array $updates): void
    {
        $key = self::TRANSIENT_PREFIX . $sessionKey;
        $data = get_transient($key);

        if ($data === false) {
            return;
        }

        if (isset($updates['stage'])) {
            $currentStage = SessionStage::tryFrom($data['stage'] ?? '');
            $newStage = SessionStage::tryFrom($updates['stage']);

            if ($currentStage !== null && $newStage !== null && !$currentStage->canTransitionTo($newStage)) {
                throw new \InvalidArgumentException(
                    "Invalid stage transition: {$currentStage->value} → {$newStage->value}",
                );
            }
        }

        $remainingTtl = self::DEFAULT_TTL - (time() - ($data['created_at'] ?? time()));
        $data = array_merge($data, $updates);

        set_transient($key, $data, max(1, $remainingTtl));
    }

    /**
     * Destroy a session.
     */
    public function destroy(string $sessionKey): void
    {
        delete_transient(self::TRANSIENT_PREFIX . $sessionKey);
    }

    private function getSigningKey(): string
    {
        return defined('AUTH_KEY') ? AUTH_KEY : 'wsms-fallback-key-' . ABSPATH;
    }
}
