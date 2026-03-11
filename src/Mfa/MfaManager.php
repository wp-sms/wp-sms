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
     * @return ChannelInterface[]
     */
    public function getEnabledChannels(): array
    {
        $settings = get_option('wsms_auth_settings', []);
        $enabledPrimary = $settings['primary_methods'] ?? [];
        $enabledMfa = $settings['mfa_factors'] ?? [];
        $enabledIds = array_unique(array_merge($enabledPrimary, $enabledMfa));

        return array_values(array_filter(
            $this->channels,
            fn(ChannelInterface $ch) => in_array($ch->getId(), $enabledIds, true),
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
}
