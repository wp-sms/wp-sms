<?php

namespace WSms\Messaging\Gateway;

defined('ABSPATH') || exit;

trait UsesGatewayApi
{
    public function getName(): string
    {
        return GatewayApiClient::get($this->getId())['name'] ?? $this->getId();
    }

    public function getMetadata(): array
    {
        $api = GatewayApiClient::get($this->getId());

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
        $api = GatewayApiClient::get($this->getId());

        if (!$api) {
            return [];
        }

        $features = $api['features'] ?? [];

        if (isset($api['test_connection'])) {
            $features['test_connection'] = $api['test_connection'];
        }

        return $features;
    }
}
