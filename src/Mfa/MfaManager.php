<?php

namespace WSms\Mfa;

use WSms\Enums\ChannelStatus;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\ValueObjects\UserFactor;

defined('ABSPATH') || exit;

class MfaManager
{
    /** @var array<string, ChannelInterface> */
    private array $channels = [];

    /**
     * Register a channel implementation.
     */
    public function registerChannel(ChannelInterface $channel): void
    {
        $this->channels[$channel->getId()] = $channel;
    }

    /**
     * Get a registered channel by ID.
     */
    public function getChannel(string $id): ?ChannelInterface
    {
        return $this->channels[$id] ?? null;
    }

    /**
     * Get all registered channels.
     *
     * @return ChannelInterface[]
     */
    public function getAvailableChannels(): array
    {
        return array_values($this->channels);
    }

    /**
     * Get channels that are enabled in admin settings.
     *
     * Dynamically checks each registered channel's ID against settings.
     *
     * @return ChannelInterface[]
     */
    public function getEnabledChannels(): array
    {
        $settings = get_option('wsms_auth_settings', []);

        return array_values(array_filter(
            $this->channels,
            function (ChannelInterface $ch) use ($settings) {
                $channelSettings = $settings[$ch->getId()] ?? [];

                return !empty($channelSettings['enabled']);
            },
        ));
    }

    /**
     * Check if a user has any active MFA factors.
     */
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
     * Get all factors for a user.
     *
     * @return UserFactor[]
     */
    public function getUserFactors(int $userId): array
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

        return array_map(
            fn(object $row) => UserFactor::fromRow($row),
            $rows,
        );
    }

    /**
     * Disable all MFA factors for a user.
     */
    public function disableAllFactors(int $userId): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $wpdb->update(
            $table,
            ['status' => ChannelStatus::Disabled->value],
            ['user_id' => $userId],
        );

        update_user_meta($userId, 'wsms_mfa_enabled', '0');
    }

    /**
     * Update the meta for an active factor.
     */
    public function updateFactorMeta(int $userId, string $channelId, array $meta): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wsms_user_factors';

        $wpdb->update(
            $table,
            ['meta' => wp_json_encode($meta), 'updated_at' => current_time('mysql', true)],
            ['user_id' => $userId, 'channel_id' => $channelId, 'status' => ChannelStatus::Active->value],
        );
    }
}
