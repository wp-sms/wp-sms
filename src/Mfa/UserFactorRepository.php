<?php

namespace WSms\Mfa;

use WSms\Enums\ChannelStatus;
use WSms\Mfa\ValueObjects\UserFactor;

defined('ABSPATH') || exit;

class UserFactorRepository
{
    public function findLatest(int $userId, string $channelId): ?UserFactor
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND channel_id = %s ORDER BY created_at DESC LIMIT 1",
            $userId,
            $channelId,
        ));

        return $row ? UserFactor::fromRow($row) : null;
    }

    public function create(int $userId, string $channelId, ChannelStatus $status, array $meta = []): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';
        $now = current_time('mysql', true);

        $wpdb->insert($table, [
            'user_id'    => $userId,
            'channel_id' => $channelId,
            'status'     => $status->value,
            'meta'       => wp_json_encode($meta),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public function update(int $factorId, array $data): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';
        $data['updated_at'] = current_time('mysql', true);

        $wpdb->update($table, $data, ['id' => $factorId]);
    }

    public function updateStatus(int $factorId, ChannelStatus $status): void
    {
        $this->update($factorId, ['status' => $status->value]);
    }

    public function updateMeta(int $userId, string $channelId, array $meta): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $wpdb->update(
            $table,
            ['meta' => wp_json_encode($meta), 'updated_at' => current_time('mysql', true)],
            ['user_id' => $userId, 'channel_id' => $channelId, 'status' => ChannelStatus::Active->value],
        );
    }

    public function hasActiveFactors(int $userId): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = %s",
            $userId,
            ChannelStatus::Active->value,
        ));
    }

    /**
     * @return UserFactor[]
     */
    public function getAllForUser(int $userId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $userId,
        ));

        if (!$rows) {
            return [];
        }

        return array_map(fn(object $row) => UserFactor::fromRow($row), $rows);
    }

    public function disableAllForUser(int $userId): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $wpdb->update(
            $table,
            ['status' => ChannelStatus::Disabled->value],
            ['user_id' => $userId],
        );
    }
}
