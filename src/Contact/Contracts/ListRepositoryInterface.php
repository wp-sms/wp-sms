<?php

namespace WSms\Contact\Contracts;

defined('ABSPATH') || exit;

interface ListRepositoryInterface
{
    public function create(array $data): string;

    public function update(string $id, array $data): bool;

    public function find(string $id): ?array;

    public function findAll(?string $type = null): array;

    public function delete(string $id): bool;

    public function updateContactCount(string $id, int $count): bool;
}
