<?php

namespace WSms\Mfa;

use WSms\Auth\SettingsRepository;
use WSms\Enums\ChannelStatus;
use WSms\Support\UserMeta;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\ValueObjects\UserFactor;

defined('ABSPATH') || exit;

class MfaManager
{
    /** @var array<string, ChannelInterface> */
    private array $channels = [];

    private ?SettingsRepository $settingsRepo = null;

    public function __construct(private readonly UserFactorRepository $factorRepo)
    {
    }

    public function setSettingsRepository(SettingsRepository $settingsRepo): void
    {
        $this->settingsRepo = $settingsRepo;
    }

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
        $settings = $this->settingsRepo ? $this->settingsRepo->all() : [];

        return array_values(array_filter(
            $this->channels,
            function (ChannelInterface $ch) use ($settings) {
                $channelSettings = $settings[$ch->getId()] ?? [];

                return !empty($channelSettings[$ch->getEnabledSettingKey()]);
            },
        ));
    }

    /**
     * Check if a user has any active MFA factors.
     */
    public function hasActiveFactors(int $userId): bool
    {
        return $this->getFactorRepo()->hasActiveFactors($userId);
    }

    /**
     * Get all factors for a user.
     *
     * @return UserFactor[]
     */
    public function getUserFactors(int $userId): array
    {
        return $this->getFactorRepo()->getAllForUser($userId);
    }

    /**
     * Get a user's active factors whose channels support MFA.
     *
     * @return array<array{channel_id: string, name: string}>
     */
    public function getActiveMfaFactors(int $userId): array
    {
        $factors = $this->getUserFactors($userId);
        $active = [];

        foreach ($factors as $factor) {
            if ($factor->status !== ChannelStatus::Active) {
                continue;
            }

            $channel = $this->getChannel($factor->channelId);

            if (!$channel || !$channel->supportsMfa()) {
                continue;
            }

            $active[] = [
                'channel_id' => $factor->channelId,
                'name'       => $channel->getName(),
                'icon_svg'   => $channel->getIconSvg(),
            ];
        }

        return $active;
    }

    /**
     * Disable all MFA factors for a user.
     */
    public function disableAllFactors(int $userId): void
    {
        $this->getFactorRepo()->disableAllForUser($userId);
        update_user_meta($userId, UserMeta::MFA_ENABLED, '0');
    }

    /**
     * Update the meta for an active factor.
     */
    public function updateFactorMeta(int $userId, string $channelId, array $meta): void
    {
        $this->getFactorRepo()->updateMeta($userId, $channelId, $meta);
    }

    private function getFactorRepo(): UserFactorRepository
    {
        return $this->factorRepo;
    }
}
