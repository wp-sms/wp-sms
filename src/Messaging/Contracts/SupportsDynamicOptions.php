<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

/**
 * Gateways that can fetch config field options from their provider API.
 *
 * When a gateway implements this interface and marks fields with `'dynamic' => true`
 * in its config schema, the admin UI will fetch available options (e.g., phone numbers,
 * sender IDs) from the provider at configuration time instead of requiring manual entry.
 */
interface SupportsDynamicOptions
{
    /**
     * Fetch options for a config field from the provider API.
     *
     * @param string $fieldKey The field key (e.g., 'from_number', 'sender')
     * @param string $section  'shared' or a channel name (e.g., 'sms', 'whatsapp')
     * @param array  $config   Current config (draft or saved) with 'shared' and 'channels' keys
     * @param array  $context  Dependency values from other fields (for fields with 'depends_on')
     *
     * @return array<array{value: string, label: string}>
     *
     * @throws \RuntimeException When credentials are invalid or the API call fails
     */
    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array;
}
