<?php

namespace WSms\Auth;

use WSms\Support\SigningKey;

defined('ABSPATH') || exit;

class TrustedDeviceManager
{
    private const META_KEY = 'wsms_trusted_devices';
    private const COOKIE_NAME = 'wsms_td';
    private const MAX_DEVICES = 5;
    private const DEFAULT_TTL = 2592000; // 30 days

    public function __construct(
        private SettingsRepository $settings,
    ) {
    }

    public function trust(int $userId): void
    {
        $config = $this->getConfig();

        if (empty($config['enabled'])) {
            return;
        }

        $ttl = (int) ($config['ttl'] ?? self::DEFAULT_TTL);
        $token = bin2hex(random_bytes(32));
        $expiresAt = time() + $ttl;

        $payload = $this->buildPayload($userId, $token, $expiresAt);
        $signature = $this->sign($payload);
        $cookieValue = base64_encode($payload . '|' . $signature);

        $this->writeCookie($cookieValue, time() + $ttl);
        $this->storeTokenHash($userId, $token, $expiresAt);
    }

    public function isTrusted(int $userId): bool
    {
        $config = $this->getConfig();

        if (empty($config['enabled'])) {
            return false;
        }

        $cookieData = $this->readCookie();

        if ($cookieData === null) {
            return false;
        }

        if ($cookieData['user_id'] !== $userId) {
            return false;
        }

        if ($cookieData['expires_at'] < time()) {
            return false;
        }

        $tokenHash = $this->hashToken($cookieData['token']);
        $devices = $this->getDevices($userId);

        $found = false;
        foreach ($devices as $device) {
            if (hash_equals($device['token_hash'], $tokenHash)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }

        // Token rotation: remove old hash, add new, single write.
        $this->rotateToken($userId, $tokenHash, $devices, $config);

        return true;
    }

    public function revokeAll(int $userId): void
    {
        delete_user_meta($userId, self::META_KEY);
        $this->writeCookie('', time() - 3600);
    }

    private function rotateToken(int $userId, string $oldTokenHash, array $devices, array $config): void
    {
        // Remove old token hash.
        $devices = array_values(array_filter($devices, fn(array $d) => !hash_equals($d['token_hash'], $oldTokenHash)));
        $devices = $this->pruneExpired($devices);

        // Generate new token.
        $ttl = (int) ($config['ttl'] ?? self::DEFAULT_TTL);
        $token = bin2hex(random_bytes(32));
        $expiresAt = time() + $ttl;

        $devices[] = [
            'token_hash' => $this->hashToken($token),
            'expires_at' => $expiresAt,
            'created_at' => time(),
        ];

        while (count($devices) > self::MAX_DEVICES) {
            array_shift($devices);
        }

        update_user_meta($userId, self::META_KEY, wp_json_encode($devices));

        // Set new cookie.
        $payload = $this->buildPayload($userId, $token, $expiresAt);
        $signature = $this->sign($payload);
        $this->writeCookie(base64_encode($payload . '|' . $signature), $expiresAt);
    }

    private function getConfig(): array
    {
        return $this->settings->get('trusted_devices', []);
    }

    private function buildPayload(int $userId, string $token, int $expiresAt): string
    {
        return $userId . '|' . $token . '|' . $expiresAt;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, SigningKey::get());
    }

    private function writeCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function readCookie(): ?array
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = base64_decode($raw, true);

        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);

        if (count($parts) !== 4) {
            return null;
        }

        [$userIdStr, $token, $expiresAtStr, $signature] = $parts;

        $payload = $userIdStr . '|' . $token . '|' . $expiresAtStr;

        if (!hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        return [
            'user_id'    => (int) $userIdStr,
            'token'      => $token,
            'expires_at' => (int) $expiresAtStr,
        ];
    }

    private function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, SigningKey::get());
    }

    private function storeTokenHash(int $userId, string $token, int $expiresAt): void
    {
        $devices = $this->getDevices($userId);
        $devices = $this->pruneExpired($devices);

        $devices[] = [
            'token_hash' => $this->hashToken($token),
            'expires_at' => $expiresAt,
            'created_at' => time(),
        ];

        while (count($devices) > self::MAX_DEVICES) {
            array_shift($devices);
        }

        update_user_meta($userId, self::META_KEY, wp_json_encode($devices));
    }

    private function getDevices(int $userId): array
    {
        $raw = get_user_meta($userId, self::META_KEY, true);

        if (empty($raw)) {
            return [];
        }

        $devices = json_decode($raw, true);

        return is_array($devices) ? $devices : [];
    }

    private function pruneExpired(array $devices): array
    {
        $now = time();

        return array_values(array_filter($devices, fn(array $d) => ($d['expires_at'] ?? 0) > $now));
    }
}
