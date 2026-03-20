<?php

namespace WSms\Contact\Source\Contracts;

defined('ABSPATH') || exit;

interface ContactSourceInterface
{
    public function getType(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function getIcon(): string;

    public function isAvailable(): bool;

    public function getDefaultFieldMapping(): array;

    public function getAvailableFields(): array;

    public function getConfigSchema(): array;

    /**
     * Sync a single external entity by its ID.
     * Returns the contact ID on success, null if skipped.
     */
    public function syncOne(mixed $externalId, array $config, bool $suppressEvents = false): ?string;

    /**
     * Get a batch of external IDs for syncing.
     * Returns array of external IDs.
     */
    public function getBatch(array $config, int $batchSize, ?int $afterId): array;

    /**
     * Count available entities matching the config.
     */
    public function countAvailable(array $config): int;

    /**
     * Handle deletion of an external entity.
     */
    public function handleDeletion(mixed $externalId): void;
}
