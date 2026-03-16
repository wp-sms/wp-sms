<?php

namespace WSms\Flow\Contracts;

defined('ABSPATH') || exit;

interface FlowRepositoryInterface
{
    public function save(Flow $flow): string;

    public function find(string $id): ?Flow;

    /** @return Flow[] */
    public function findByTrigger(string $triggerType): array;

    /** @return Flow[] */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function delete(string $id): bool;

    public function updateStatus(string $id, string $status): bool;

    public function publish(string $id): bool;
}
