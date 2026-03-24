<?php

namespace WSms\Contact\Contracts;

use WSms\Exception\NotFoundException;

defined('ABSPATH') || exit;

interface ContactRepositoryInterface
{
    public function create(array $data, bool $suppressEvents = false): string;

    public function update(string $id, array $data): bool;

    public function find(string $id): ?array;

    /**
     * @throws NotFoundException if the contact does not exist.
     */
    public function findOrFail(string $id): array;

    public function findByEmail(string $email): ?array;

    public function findByPhone(string $phone): ?array;

    /** @return array[] All contacts matching the phone number */
    public function findAllByPhone(string $phone): array;

    public function findByWpUser(int $userId): ?array;

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function count(array $filters = []): int;

    public function delete(string $id): bool;

    public function addTag(string $contactId, string $tagId): void;

    public function removeTag(string $contactId, string $tagId): void;

    /** @return array Tag rows for this contact */
    public function getTags(string $contactId): array;

    public function findByTag(string $tagId, int $limit = 50, int $offset = 0): array;

    public function countByTag(string $tagId): int;

    /** @return array Contact rows matching the given IDs */
    public function findByIds(array $ids): array;

    /** @return int Number of contacts deleted */
    public function bulkDelete(array $ids): int;

    /** @return int Number of contacts updated */
    public function bulkUpdateStatus(array $ids, string $status): int;

    /** @return int Number of tag associations created */
    public function bulkAddTag(array $contactIds, string $tagId): int;

    /** @return int Number of tag associations removed */
    public function bulkRemoveTag(array $contactIds, string $tagId): int;

    /**
     * Iterate subscribed contacts with a non-empty email in batches.
     *
     * The callback receives each batch (array of contact rows).
     * Useful for large-scale operations like marketing sync.
     *
     * @param callable(list<array<string, mixed>>): void $callback
     */
    public function eachSubscribedWithEmail(callable $callback, int $batchSize = 500): void;
}
