<?php

namespace WSms\Contact\Contracts;

defined('ABSPATH') || exit;

interface TagRepositoryInterface
{
    public function create(array $data): string;

    public function update(string $id, array $data): bool;

    public function find(string $id): ?array;

    public function findBySlug(string $slug): ?array;

    public function findAll(): array;

    public function delete(string $id): bool;

    public function getContactCount(string $id): int;

    /**
     * Get contact counts for all tags in a single query.
     * @return array<string, int> Tag ID => count
     */
    public function getContactCounts(): array;
}
