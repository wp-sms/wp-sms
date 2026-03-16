<?php

namespace WSms\Contact\Contracts;

defined('ABSPATH') || exit;

interface ContactRepositoryInterface
{
    public function create(array $data): string;

    public function update(string $id, array $data): bool;

    public function find(string $id): ?array;

    public function findByEmail(string $email): ?array;

    public function findByPhone(string $phone): ?array;

    public function findByWpUser(int $userId): ?array;

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function count(array $filters = []): int;

    public function delete(string $id): bool;

    public function addTag(string $contactId, string $tagId): void;

    public function removeTag(string $contactId, string $tagId): void;

    /** @return array Tag rows for this contact */
    public function getTags(string $contactId): array;
}
