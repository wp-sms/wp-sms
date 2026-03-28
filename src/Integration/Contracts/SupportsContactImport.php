<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

interface SupportsContactImport
{
    /** Fields available on the external source (e.g., 'email_address' => ['label' => 'Email', 'type' => 'core']). */
    public function getAvailableImportFields(): array;

    /** Default field mapping: contact_field => source_field. */
    public function getDefaultImportFieldMapping(): array;

    /** Config schema for import-specific settings (role selection, list picker, etc.). */
    public function getImportConfigSchema(): array;

    /** Import a single external entity. Returns contact ID on success, null if skipped. */
    public function importOne(mixed $externalId, array $config, bool $suppressEvents = false): ?string;

    /** Get a batch of external IDs for import. Returns array of external IDs. */
    public function getImportBatch(array $config, int $batchSize, mixed $afterCursor = null): array;

    /** Count entities available to import with given config. */
    public function countImportable(array $config): int;

    /** Handle deletion of an external entity (e.g., unsubscribe the linked contact). */
    public function handleImportDeletion(mixed $externalId): void;
}
