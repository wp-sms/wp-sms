<?php

namespace WSms\Social;

use WSms\Social\Contracts\SocialProviderInterface;

defined('ABSPATH') || exit;

class SocialAuthManager
{
    /** @var array<string, SocialProviderInterface> */
    private array $providers = [];

    public function registerProvider(SocialProviderInterface $provider): void
    {
        $this->providers[$provider->getId()] = $provider;
    }

    public function getProvider(string $id): ?SocialProviderInterface
    {
        return $this->providers[$id] ?? null;
    }

    /**
     * Get all registered providers.
     *
     * @return SocialProviderInterface[]
     */
    public function getAllProviders(): array
    {
        return array_values($this->providers);
    }

    /**
     * Get providers that are enabled (have credentials configured).
     *
     * @return SocialProviderInterface[]
     */
    public function getEnabledProviders(): array
    {
        $settings = get_option('wsms_auth_settings', []);
        $socialSettings = $settings['social'] ?? [];

        return array_values(array_filter(
            $this->providers,
            function (SocialProviderInterface $provider) use ($socialSettings) {
                $providerSettings = $socialSettings[$provider->getId()] ?? [];

                return !empty($providerSettings['enabled'])
                    && !empty($providerSettings['client_id'])
                    && !empty($providerSettings['client_secret']);
            },
        ));
    }
}
