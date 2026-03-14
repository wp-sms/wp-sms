<?php

namespace WSms\Verification;

use WSms\Support\SigningKey;

defined('ABSPATH') || exit;

class VerificationSession
{
    private const TRANSIENT_PREFIX = 'wsms_vstok_';

    public function __construct(
        private VerificationConfig $config,
    ) {
    }

    /**
     * Create a new verification session.
     *
     * @return array{session_id: string, token: string}
     */
    public function create(): array
    {
        $sessionId = bin2hex(random_bytes(16));
        $ttl = (int) $this->config->get('session_ttl', 1800);
        $expiry = time() + $ttl;

        $data = [
            'session_id'  => $sessionId,
            'verified'    => ['email' => [], 'phone' => []],
            'created_at'  => time(),
            'expires_at'  => $expiry,
        ];

        set_transient(self::TRANSIENT_PREFIX . $sessionId, $data, $ttl);

        $payload = $sessionId . '|' . $expiry;
        $signature = hash_hmac('sha256', $payload, SigningKey::get());
        $token = base64_encode($payload . '|' . $signature);

        return ['session_id' => $sessionId, 'token' => $token];
    }

    /**
     * Validate a session token and return session data.
     *
     * @return array|null Session data or null if invalid/expired.
     */
    public function validate(string $token): ?array
    {
        $decoded = base64_decode($token, true);

        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);

        if (count($parts) !== 3) {
            return null;
        }

        [$sessionId, $expiry, $signature] = $parts;

        $expectedPayload = $sessionId . '|' . $expiry;
        $expectedSignature = hash_hmac('sha256', $expectedPayload, SigningKey::get());

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        if ((int) $expiry < time()) {
            return null;
        }

        $data = get_transient(self::TRANSIENT_PREFIX . $sessionId);

        if ($data === false) {
            return null;
        }

        return $data;
    }

    /**
     * Mark a channel+identifier as verified in the session.
     *
     * @param array|null $preloaded Pre-fetched session data to avoid re-reading transient.
     */
    public function markVerified(string $sessionId, string $channel, string $identifier, ?array $preloaded = null): void
    {
        $key = self::TRANSIENT_PREFIX . $sessionId;
        $data = $preloaded ?? get_transient($key);

        if ($data === false || $data === null) {
            return;
        }

        $remainingTtl = ($data['expires_at'] ?? time()) - time();

        if ($remainingTtl <= 0) {
            return;
        }

        $data['verified'][$channel][$identifier] = time();

        set_transient($key, $data, $remainingTtl);
    }

    /**
     * Destroy a session.
     */
    public function destroy(string $sessionId): void
    {
        delete_transient(self::TRANSIENT_PREFIX . $sessionId);
    }
}
