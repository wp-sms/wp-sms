<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

interface SupportsAutomations
{
    /** Empty array if provider doesn't support listing. UI falls back to manual ID input. */
    public function getAutomations(array $config): array;

    public function queueIntoAutomation(string $automationId, string $email, array $fields, array $config): SyncResult;
}
