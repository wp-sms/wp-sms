<?php

namespace WSms\Messaging\Gateway;

defined('ABSPATH') || exit;

trait UsesGatewayApi
{
    private ?GatewayApiClient $apiClient = null;

    public function setApiClient(GatewayApiClient $apiClient): void
    {
        $this->apiClient = $apiClient;
    }

    public function getName(): string
    {
        return $this->apiData()['name'] ?? $this->getId();
    }

    public function getMetadata(): array
    {
        $api = $this->apiData();

        if (!$api) {
            return [];
        }

        return [
            'description' => $api['description'] ?? '',
            'website'     => $api['website'] ?? '',
            'icon'        => $api['branding']['logo_square'] ?? '',
            'regions'     => $api['coverage']['regions'] ?? [],
            'setup_url'   => $api['setup']['dashboard'] ?? '',
            'setup_notes' => $api['setup']['notes'] ?? [],
            'status'      => $api['status'] ?? 'active',
            'tier'        => $api['tier'] ?? 'free',
            'recommended' => $api['recommended'] ?? false,
            'branding'    => $api['branding'] ?? [],
            'coverage'    => $api['coverage'] ?? [],
        ];
    }

    public function getFeatures(): array
    {
        $api = $this->apiData();

        if (!$api) {
            return [];
        }

        $features = $api['features'] ?? [];

        if (isset($api['test_connection'])) {
            $features['test_connection'] = $api['test_connection'];
        }

        return $features;
    }

    private function apiData(): ?array
    {
        return $this->apiClient?->get($this->getId());
    }
}
