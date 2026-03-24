<?php

namespace WSms\Mfa;

use WSms\Database\Connection;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\ValueObjects\UserFactor;

defined('ABSPATH') || exit;

class UserFactorRepository
{
    public function __construct(private readonly Connection $db) {}

    public function findLatest(int $userId, string $channelId): ?UserFactor
    {
        $table = $this->db->table(Connection::TABLE_USER_FACTORS);

        $row = $this->db->getRow(
            "SELECT * FROM {$table} WHERE user_id = %d AND channel_id = %s ORDER BY created_at DESC LIMIT 1",
            $userId,
            $channelId,
        );

        return $row ? UserFactor::fromRow($row) : null;
    }

    public function create(int $userId, string $channelId, ChannelStatus $status, array $meta = []): int
    {
        $now = current_time('mysql', true);

        $this->db->insert(Connection::TABLE_USER_FACTORS, [
            'user_id'    => $userId,
            'channel_id' => $channelId,
            'status'     => $status->value,
            'meta'       => wp_json_encode($meta),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->insertId();
    }

    public function update(int $factorId, array $data): void
    {
        $data['updated_at'] = current_time('mysql', true);

        $this->db->update(Connection::TABLE_USER_FACTORS, $data, ['id' => $factorId]);
    }

    public function updateStatus(int $factorId, ChannelStatus $status): void
    {
        $this->update($factorId, ['status' => $status->value]);
    }

    public function updateMeta(int $userId, string $channelId, array $meta): void
    {
        $this->db->update(
            Connection::TABLE_USER_FACTORS,
            ['meta' => wp_json_encode($meta), 'updated_at' => current_time('mysql', true)],
            ['user_id' => $userId, 'channel_id' => $channelId, 'status' => ChannelStatus::Active->value],
        );
    }

    public function hasActiveFactors(int $userId): bool
    {
        $table = $this->db->table(Connection::TABLE_USER_FACTORS);

        return (bool) $this->db->getVar(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = %s",
            $userId,
            ChannelStatus::Active->value,
        );
    }

    /**
     * @return UserFactor[]
     */
    public function getAllForUser(int $userId): array
    {
        $table = $this->db->table(Connection::TABLE_USER_FACTORS);
        $rows = $this->db->getResults(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $userId,
        );

        if (!$rows) {
            return [];
        }

        return array_map(fn(array $row) => UserFactor::fromRow($row), $rows);
    }

    public function disableAllForUser(int $userId): void
    {
        $this->db->update(
            Connection::TABLE_USER_FACTORS,
            ['status' => ChannelStatus::Disabled->value],
            ['user_id' => $userId],
        );
    }
}
