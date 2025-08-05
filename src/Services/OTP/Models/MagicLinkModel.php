<?php

namespace WP_SMS\Services\Authentication\Channels\MagicLink;

use WP_SMS\Contracts\Abstracts\AbstractBaseModel;

class MagicLinkModel extends AbstractBaseModel
{
    public ?string $flow_id = null;
    public ?int $user_id = null;
    public ?string $token_hash = null;
    public ?string $expires_at = null;
    public ?string $used_at = null;
    public ?string $created_at = null;

    protected static function getTableName(): string
    {
        return static::table('magic_links');
    }

    /**
     * Create a new magic link session.
     */
    public static function createSession(string $flowId, int $userId, string $token, int $expiresInSeconds): string
    {
        $now = current_time('mysql');
        $expires = gmdate('Y-m-d H:i:s', time() + $expiresInSeconds);
        $hash = hash('sha256', $token);

        static::insert([
            'flow_id'     => $flowId,
            'user_id'     => $userId,
            'token_hash'  => $hash,
            'expires_at'  => $expires,
            'created_at'  => $now,
        ]);

        return $flowId;
    }

    /**
     * Validate a magic link token.
     */
    public static function validateToken(string $flowId, string $inputToken): bool
    {
        $record = static::find(['flow_id' => $flowId]);

        if (! $record) {
            return false;
        }

        if (! empty($record['used_at'])) {
            return false;
        }

        if (strtotime($record['expires_at']) < time()) {
            return false;
        }

        $expectedHash = hash('sha256', $inputToken);
        return hash_equals($record['token_hash'], $expectedHash);
    }

    /**
     * Mark magic link as used.
     */
    public static function markAsUsed(string $flowId): void
    {
        static::updateBy(
            ['used_at' => current_time('mysql')],
            ['flow_id' => $flowId]
        );
    }

    /**
     * Delete a magic link record.
     */
    public static function deleteByFlowId(string $flowId): void
    {
        static::deleteBy(['flow_id' => $flowId]);
    }
}
