<?php

namespace WP_SMS\Services\OTP\Models;

use WP_SMS\Contracts\Abstracts\AbstractBaseModel;
class IdentifierModel extends AbstractBaseModel
{
    public ?int $user_id = null;
    public ?string $factor_type = null;
    public ?string $factor_value = null;
    public ?string $value_hash = null;
    public bool $verified = false;
    public ?string $created_at = null;
    public ?string $verified_at = null;

    protected static function getTableName(): string
    {
        return static::table('sms_identifiers');
    }

    /**
     * Get all factors for a user.
     */
    public function getAllByUserId(int $userId): array
    {
        return $this->findAll(['user_id' => $userId]);
    }

    /**
     * Find a specific factor by hash.
     */
    public function getByHash(string $hash): ?array
    {
        return $this->find(['value_hash' => $hash]);
    }

    /**
     * Find a factor for a user by type.
     */
    public function getByUserAndType(int $userId, string $type): ?array
    {
        return $this->find([
            'user_id'     => $userId,
            'factor_type' => $type,
        ]);
    }
}
