<?php

namespace WSms\Mfa\Channels;

use WSms\Auth\SettingsRepository;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\UserFactorRepository;
use WSms\Mfa\ValueObjects\UserFactor;

/**
 * Shared factor-management methods for all channel implementations.
 *
 * Requires the using class to implement getId() and have $auditLogger property.
 */
trait HasUserFactor
{
    private ?SettingsRepository $channelSettingsRepo = null;
    private ?UserFactorRepository $factorRepo = null;

    /** @var array<int, ?UserFactor> Per-userId cache for the current request. */
    private array $factorCache = [];

    public function setUserFactorRepository(UserFactorRepository $factorRepo): void
    {
        $this->factorRepo = $factorRepo;
    }

    /**
     * Get the user's factor for this channel (cached per request).
     */
    protected function getFactor(int $userId): ?UserFactor
    {
        if (array_key_exists($userId, $this->factorCache)) {
            return $this->factorCache[$userId];
        }

        $factor = $this->getFactorRepo()->findLatest($userId, $this->getId());
        $this->factorCache[$userId] = $factor;

        return $factor;
    }

    /**
     * Create a new factor record for this channel.
     */
    protected function createFactor(int $userId, ChannelStatus $status, array $meta = []): int
    {
        $id = $this->getFactorRepo()->create($userId, $this->getId(), $status, $meta);
        unset($this->factorCache[$userId]);

        return $id;
    }

    /**
     * Update an existing factor record.
     */
    protected function updateFactor(int $factorId, array $data): void
    {
        $this->getFactorRepo()->update($factorId, $data);

        // Invalidate cache since we don't know the userId from just the factorId.
        $this->factorCache = [];
    }

    /**
     * Get a config value for this channel from auth settings.
     *
     * Settings are stored nested by channel prefix, e.g. settings['phone']['code_length'].
     */
    public function setSettingsRepository(SettingsRepository $settingsRepo): void
    {
        $this->channelSettingsRepo = $settingsRepo;
    }

    protected function getConfigValue(string $key, mixed $default = null): mixed
    {
        if ($this->channelSettingsRepo) {
            return $this->channelSettingsRepo->channel($this->getConfigPrefix())[$key] ?? $default;
        }

        return $default;
    }

    /**
     * Config prefix for this channel's settings.
     */
    abstract protected function getConfigPrefix(): string;

    public function getEnabledSettingKey(): string
    {
        return 'enabled';
    }

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

        $this->getFactorRepo()->updateStatus($factor->id, ChannelStatus::Disabled);

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

    private function getFactorRepo(): UserFactorRepository
    {
        if ($this->factorRepo === null) {
            throw new \LogicException('UserFactorRepository was not injected. Call setUserFactorRepository() before using factor methods.');
        }

        return $this->factorRepo;
    }
}
