<?php

namespace WP_SMS\Services\LocalizedData;

/**
 * Localized Data Service
 *
 * Central service for managing localized data that needs to be passed to JavaScript.
 * Uses provider pattern to separate concerns and make data management more maintainable.
 *
 * @package WP_SMS\Services\LocalizedData
 * @since   7.2
 */
class LocalizedDataService
{
    /**
     * Registered data providers
     *
     * @var array<string, DataProviderInterface>
     */
    private $providers = [];

    /**
     * Add a data provider (uses the provider's getKey() method)
     *
     * @param DataProviderInterface $provider Provider instance
     *
     * @return void
     */
    public function addProvider(DataProviderInterface $provider)
    {
        $this->providers[$provider->getKey()] = $provider;
    }

    /**
     * Register a data provider with a custom key
     *
     * @param string                $key      Provider key (e.g., 'globals', 'layout')
     * @param DataProviderInterface $provider Provider instance
     *
     * @return void
     * @deprecated Use addProvider() instead. This method will be removed in future versions.
     */
    public function registerProvider(string $key, DataProviderInterface $provider)
    {
        $this->providers[$key] = $provider;
    }

    /**
     * Unregister a data provider
     *
     * @param string $key Provider key
     *
     * @return void
     */
    public function unregisterProvider(string $key)
    {
        unset($this->providers[$key]);
    }

    /**
     * Get data from a specific provider
     *
     * @param string $key Provider key
     *
     * @return array
     */
    public function getData(string $key): array
    {
        if (!isset($this->providers[$key])) {
            return [];
        }

        return $this->providers[$key]->getData();
    }

    /**
     * Get all localized data from all registered providers
     *
     * @return array
     */
    public function getAllData(): array
    {
        $data = [];

        foreach ($this->providers as $key => $provider) {
            $data[$key] = $provider->getData();
        }

        return apply_filters('wp_sms_localized_data', $data);
    }

    /**
     * Check if a provider is registered
     *
     * @param string $key Provider key
     *
     * @return bool
     */
    public function hasProvider(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * Get all registered provider keys
     *
     * @return array
     */
    public function getProviderKeys(): array
    {
        return array_keys($this->providers);
    }
}
