<?php

namespace WP_SMS\Services\LocalizedData;

/**
 * Data Provider Interface
 *
 * Interface for all localized data providers.
 * Each provider is responsible for generating a specific section of localized data.
 *
 * @package WP_SMS\Services\LocalizedData
 * @since   7.2
 */
interface DataProviderInterface
{
    /**
     * Get the provider's unique key
     *
     * This key is used to identify the provider and organize data structure.
     * For example: 'globals', 'layout', 'sidebar', 'header'
     *
     * @return string The provider's unique key
     */
    public function getKey(): string;

    /**
     * Get the provider's data
     *
     * @return array The data to be localized
     */
    public function getData(): array;
}
