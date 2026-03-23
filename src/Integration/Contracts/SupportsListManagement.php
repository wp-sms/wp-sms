<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

interface SupportsListManagement
{
    public function getLists(array $config): array;

    public function addToList(string $listId, string $email, array $fields, array $config): SyncResult;

    public function removeFromList(string $listId, string $email, array $config): SyncResult;
}
