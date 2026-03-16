<?php

namespace WSms\Messaging\Gateway;

use WSms\Messaging\Contracts\GatewayInterface;

defined('ABSPATH') || exit;

class GatewayRegistry
{
    /** @var array<string, GatewayInterface> */
    private array $gateways = [];

    public function register(GatewayInterface $gateway): void
    {
        $this->gateways[$gateway->getId()] = $gateway;
    }

    public function get(string $id): ?GatewayInterface
    {
        return $this->gateways[$id] ?? null;
    }

    /** @return GatewayInterface[] */
    public function getByChannel(string $channel): array
    {
        return array_filter($this->gateways, fn($g) => in_array($channel, $g->getSupportedChannels()));
    }

    public function getDefault(string $channel): ?GatewayInterface
    {
        $configs = get_option('wsms_gateway_configs', []);

        foreach ($this->gateways as $id => $gateway) {
            if (!in_array($channel, $gateway->getSupportedChannels())) {
                continue;
            }
            $config = $configs[$id] ?? [];
            if (!empty($config['is_default'][$channel])) {
                return $gateway;
            }
        }

        // Fallback: return first configured gateway for this channel
        foreach ($this->getByChannel($channel) as $gateway) {
            if ($gateway->isConfigured()) {
                return $gateway;
            }
        }

        return null;
    }

    /** @return GatewayInterface[] */
    public function getConfigured(): array
    {
        return array_filter($this->gateways, fn($g) => $g->isConfigured());
    }

    /** @return string[] */
    public function getAvailableChannels(): array
    {
        $channels = [];
        foreach ($this->gateways as $gateway) {
            foreach ($gateway->getSupportedChannels() as $channel) {
                $channels[$channel] = true;
            }
        }
        return array_keys($channels);
    }

    /** @return GatewayInterface[] */
    public function all(): array
    {
        return $this->gateways;
    }
}
