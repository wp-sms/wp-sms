<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

interface SupportsContactSync
{
    public function pushContact(array $contact, array $config): SyncResult;

    public function pushContactBatch(array $contacts, array $config): SyncResult;

    public function removeContact(string $email, array $config): SyncResult;

    public function updateContactStatus(string $email, string $status, array $config): SyncResult;

    public function getFieldMapping(): array;
}
