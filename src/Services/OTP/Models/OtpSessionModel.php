<?php

namespace WP_SMS\Services\OTP\Models;

use WP_SMS\Contracts\Abstracts\AbstractBaseModel;

class OtpSessionModel extends AbstractBaseModel
{
    public ?string $flow_id = null;
    public ?int $user_id = null;
    public ?string $code_hash = null;
    public ?string $expires_at = null;
    public ?string $created_at = null;

    protected static function getTableName(): string
    {
        return static::table('otp_sessions');
    }

    /**
     * Create a new OTP session.
     */
    public static function createSession(string $flowId, int $userId, string $code, int $expiresInSeconds): string
    {
        $now = current_time('mysql');
        $expires = gmdate('Y-m-d H:i:s', time() + $expiresInSeconds);
        $hash = hash('sha256', $code);

        static::insert([
            'flow_id'    => $flowId,
            'user_id'    => $userId,
            'code_hash'  => $hash,
            'expires_at' => $expires,
            'created_at' => $now,
        ]);

        return $flowId;
    }

    /**
     * Validate OTP session by flow ID and code.
     */
    public function validateSession(string $flowId, string $inputCode): bool
    {
        $record = $this->find(['flow_id' => $flowId]);

        if (!$record) {
            return false;
        }

        if (strtotime($record['expires_at']) < time()) {
            return false;
        }

        return hash_equals($record['code_hash'], hash('sha256', $inputCode));
    }

    /**
     * Destroy OTP session by flow ID.
     */
    public function destroySession(string $flowId): void
    {
        static::deleteBy(['flow_id' => $flowId]);
    }

    public static function hasUnexpiredSession(string $phone): bool
    {
        return static::exists(['phone' => $phone, 'expires_at' => DateUtils::getUnexpiredSqlCondition()]);
    }

    public static function getByFlowId(string $flowId): array
    {
        return static::find(['flow_id' => $flowId]);
    }
}
