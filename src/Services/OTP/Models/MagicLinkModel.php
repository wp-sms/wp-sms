<?php

namespace WP_SMS\Services\OTP\Models;

use WP_SMS\Contracts\Abstracts\AbstractBaseModel;

class MagicLinkModel extends AbstractBaseModel
{
    public ?string $flow_id = null;
    public ?string $token_hash = null;
    public ?string $identifier = null;
    public ?string $identifier_type = null;
    public ?string $expires_at = null;
    public ?string $used_at = null;
    public ?string $created_at = null;

    protected static function getTableName(): string
    {
        return static::table('sms_magic_links');
    }

    /**
     * Create a new magic link session.
     */
    public static function createSession(string $flowId, string $token, string $identifier = null, string $identifierType = null, int $expiresInSeconds = 600): string
    {
        $now = current_time('mysql');
        $expires = gmdate('Y-m-d H:i:s', time() + $expiresInSeconds);
        $hash = hash('sha256', $token);

        $data = [
            'flow_id'     => $flowId,
            'token_hash'  => $hash,
            'identifier'  => $identifier,
            'identifier_type'  => $identifierType,
            'expires_at'  => $expires,
            'created_at'  => $now,
        ];

        if ($identifier && $identifierType) {
            $data['identifier'] = $identifier;
            $data['identifier_type'] = $identifierType;
        }

        static::insert($data);

        return $flowId;
    }

    /**
     * Validate a magic link token.
     */
    public static function validateToken(string $flowId, string $inputToken, string $identifier, string $identifierType): bool
    {
        $record = static::find(['flow_id' => $flowId, 'identifier' => $identifier, 'identifier_type' => $identifierType]);

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
    public static function markAsUsed(string $flowId, string $identifier = null, string $identifierType = null): void
    {
        $conditions = ['flow_id' => $flowId];
        
        if ($identifier && $identifierType) {
            $conditions['identifier'] = $identifier;
            $conditions['identifier_type'] = $identifierType;
        }

        static::updateBy(
            ['used_at' => current_time('mysql')],
            $conditions
        );
    }

    /**
     * Delete a magic link record.
     */
    public static function deleteByFlowId(string $flowId, string $identifier = null, string $identifierType = null): void
    {
        $conditions = ['flow_id' => $flowId];
        
        if ($identifier && $identifierType) {
            $conditions['identifier'] = $identifier;
            $conditions['identifier_type'] = $identifierType;
        }

        static::deleteBy($conditions);
    }
}
