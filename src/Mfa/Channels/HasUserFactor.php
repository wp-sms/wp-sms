<?php

namespace WSms\Mfa\Channels;

use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\ValueObjects\UserFactor;

/**
 * Shared factor-management methods for all channel implementations.
 *
 * Requires the using class to implement getId() and have $auditLogger property.
 */
trait HasUserFactor
{
    private ?array $settings = null;

    /** @var array<int, ?UserFactor> Per-userId cache for the current request. */
    private array $factorCache = [];

    /**
     * Get the user's factor for this channel (cached per request).
     */
    protected function getFactor(int $userId): ?UserFactor
    {
        if (array_key_exists($userId, $this->factorCache)) {
            return $this->factorCache[$userId];
        }

        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND channel_id = %s
             ORDER BY created_at DESC LIMIT 1",
            $userId,
            $this->getId(),
        ));

        $factor = $row ? UserFactor::fromRow($row) : null;
        $this->factorCache[$userId] = $factor;

        return $factor;
    }

    /**
     * Create a new factor record for this channel.
     */
    protected function createFactor(int $userId, ChannelStatus $status, array $meta = []): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';
        $now = current_time('mysql', true);

        $wpdb->insert($table, [
            'user_id'    => $userId,
            'channel_id' => $this->getId(),
            'status'     => $status->value,
            'meta'       => wp_json_encode($meta),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        unset($this->factorCache[$userId]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing factor record.
     */
    protected function updateFactor(int $factorId, array $data): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';
        $data['updated_at'] = current_time('mysql', true);

        $wpdb->update($table, $data, ['id' => $factorId]);

        // Invalidate cache since we don't know the userId from just the factorId.
        $this->factorCache = [];
    }

    /**
     * Get a config value for this channel from auth settings.
     *
     * Settings are stored nested by channel prefix, e.g. settings['phone']['code_length'].
     */
    protected function getConfigValue(string $key, mixed $default = null): mixed
    {
        if ($this->settings === null) {
            $this->settings = get_option('wsms_auth_settings', []);
        }

        $prefix = $this->getConfigPrefix();

        return $this->settings[$prefix][$key] ?? $default;
    }

    /**
     * Config prefix for this channel's settings.
     */
    abstract protected function getConfigPrefix(): string;

    public function isEnrolled(int $userId): bool
    {
        $factor = $this->getFactor($userId);

        return $factor !== null && $factor->status === ChannelStatus::Active;
    }

    public function unenroll(int $userId): bool
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return false;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $wpdb->update(
            $table,
            [
                'status'     => ChannelStatus::Disabled->value,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $factor->id],
        );

        $this->factorCache = [];

        $this->auditLogger->log(EventType::MfaUnenrolled, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return true;
    }

    public function getEnrollmentInfo(int $userId): array
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return ['enrolled' => false];
        }

        return [
            'enrolled'   => $factor->status === ChannelStatus::Active,
            'status'     => $factor->status->value,
            'channel'    => $this->getId(),
            'created_at' => $factor->createdAt,
        ];
    }
}
