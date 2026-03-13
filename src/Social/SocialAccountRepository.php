<?php

namespace WSms\Social;

use WSms\Enums\ChannelStatus;

defined('ABSPATH') || exit;

class SocialAccountRepository
{
    public const SOCIAL_PROVIDERS = ['google', 'apple', 'facebook', 'microsoft', 'github', 'linkedin', 'twitter', 'telegram'];

    /**
     * Find a WordPress user by their social provider account ID.
     *
     * @return object|null Row from wsms_user_factors with user_id.
     */
    public function findByProviderAccount(string $providerId, string $providerAccountId): ?object
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE channel_id = %s AND identifier = %s AND status = %s LIMIT 1",
            $providerId,
            $providerAccountId,
            ChannelStatus::Active->value,
        ));

        return $row ?: null;
    }

    /**
     * Get all social accounts linked to a user.
     *
     * @return object[]
     */
    public function findByUserId(int $userId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';
        $placeholders = implode(',', array_fill(0, count(self::SOCIAL_PROVIDERS), '%s'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND channel_id IN ({$placeholders})",
            $userId,
            ...self::SOCIAL_PROVIDERS,
        ));

        return $rows ?: [];
    }

    /**
     * Find a specific social link for a user and provider.
     */
    public function findByUserAndProvider(int $userId, string $providerId): ?object
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND channel_id = %s LIMIT 1",
            $userId,
            $providerId,
        ));

        return $row ?: null;
    }

    /**
     * Link a social account to a WordPress user.
     */
    public function linkAccount(int $userId, string $providerId, string $providerAccountId, array $meta = []): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $metaJson = wp_json_encode($this->encryptTokensInMeta($meta));

        $wpdb->insert($table, [
            'user_id'    => $userId,
            'channel_id' => $providerId,
            'identifier' => $providerAccountId,
            'status'     => ChannelStatus::Active->value,
            'meta'       => $metaJson,
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Unlink a social account from a user.
     */
    public function unlinkAccount(int $userId, string $providerId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $deleted = $wpdb->delete($table, [
            'user_id'    => $userId,
            'channel_id' => $providerId,
        ]);

        return $deleted > 0;
    }

    /**
     * Update tokens in the meta JSON for a social account.
     */
    public function updateTokens(int $factorId, array $tokens): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT meta FROM {$table} WHERE id = %d",
            $factorId,
        ));

        if (!$row) {
            return;
        }

        $meta = json_decode($row->meta, true) ?: [];
        $meta['tokens'] = $this->encryptValue(wp_json_encode($tokens));

        $wpdb->update(
            $table,
            ['meta' => wp_json_encode($meta), 'updated_at' => current_time('mysql', true)],
            ['id' => $factorId],
        );
    }

    /**
     * Encrypt sensitive token data before storage.
     */
    private function encryptTokensInMeta(array $meta): array
    {
        if (isset($meta['tokens'])) {
            $meta['tokens'] = $this->encryptValue(wp_json_encode($meta['tokens']));
        }

        return $meta;
    }

    /**
     * Encrypt a value using AES-256-CBC with AUTH_KEY.
     */
    private function encryptValue(string $plaintext): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value encrypted with encryptValue().
     */
    public function decryptValue(string $ciphertext): string
    {
        $key = $this->getEncryptionKey();
        $raw = base64_decode($ciphertext, true);

        if ($raw === false || strlen($raw) < 17) {
            return '';
        }

        $iv = substr($raw, 0, 16);
        $encrypted = substr($raw, 16);

        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : '';
    }

    private function getEncryptionKey(): string
    {
        $authKey = defined('AUTH_KEY') ? AUTH_KEY : 'wsms-fallback-key';

        return hash('sha256', $authKey, true);
    }
}
