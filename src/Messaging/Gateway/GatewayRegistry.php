<?php

namespace WSms\Messaging\Gateway;

use WSms\Messaging\Contracts\GatewayInterface;

defined('ABSPATH') || exit;

class GatewayRegistry
{
    /** @var array<string, GatewayInterface> */
    private array $gateways = [];

    /** @var array<string, string|callable> Class name or factory closure, not yet instantiated */
    private array $deferred = [];

    public function register(GatewayInterface $gateway): void
    {
        $this->gateways[$gateway->getId()] = $gateway;
        unset($this->deferred[$gateway->getId()]);
    }

    /**
     * Register a gateway lazily — it won't be instantiated until first accessed.
     *
     * @param string $id Gateway identifier
     * @param string|callable $resolver Class name (new $class()) or factory closure
     */
    public function registerDeferred(string $id, string|callable $resolver): void
    {
        if (!isset($this->gateways[$id])) {
            $this->deferred[$id] = $resolver;
        }
    }

    public function get(string $id): ?GatewayInterface
    {
        if (isset($this->gateways[$id])) {
            return $this->gateways[$id];
        }

        if (isset($this->deferred[$id])) {
            $resolver = $this->deferred[$id];
            $this->gateways[$id] = is_string($resolver) ? new $resolver() : $resolver();
            unset($this->deferred[$id]);
            return $this->gateways[$id];
        }

        return null;
    }

    /** @return GatewayInterface[] */
    public function getByChannel(string $channel): array
    {
        $this->resolveAll();
        return array_filter($this->gateways, fn($g) => in_array($channel, $g->getSupportedChannels()));
    }

    public function getDefault(string $channel): ?GatewayInterface
    {
        // Optimized: check configs first, only instantiate the default
        $configs = get_option('wsms_gateway_configs', []);
        foreach ($configs as $id => $config) {
            if (!empty($config['is_default'][$channel])) {
                $gateway = $this->get($id);
                if ($gateway && in_array($channel, $gateway->getSupportedChannels())) {
                    return $gateway;
                }
            }
        }

        // Fallback: first configured gateway for this channel (resolves all — rare path)
        foreach ($this->getByChannel($channel) as $gateway) {
            if ($gateway->isConfiguredForChannel($channel)) {
                return $gateway;
            }
        }

        return null;
    }

    /** @return GatewayInterface[] */
    public function getConfigured(): array
    {
        $this->resolveAll();
        return array_filter($this->gateways, fn($g) => $g->isConfigured());
    }

    /** @return string[] Only channels that have at least one configured gateway */
    public function getConfiguredChannels(): array
    {
        $channels = [];
        foreach ($this->getConfigured() as $gateway) {
            foreach ($gateway->getSupportedChannels() as $channel) {
                if ($gateway->isConfiguredForChannel($channel)) {
                    $channels[$channel] = true;
                }
            }
        }
        return array_keys($channels);
    }

    /** @return string[] */
    public function getAvailableChannels(): array
    {
        $this->resolveAll();
        $channels = [];
        foreach ($this->gateways as $gateway) {
            foreach ($gateway->getSupportedChannels() as $channel) {
                $channels[$channel] = true;
            }
        }
        return array_keys($channels);
    }

    /** @return GatewayInterface[] All registered gateways (resolves all deferred) */
    public function all(): array
    {
        $this->resolveAll();
        return $this->gateways;
    }

    /** @return string[] All registered gateway IDs (without resolving deferred) */
    public function allIds(): array
    {
        return array_unique(array_merge(array_keys($this->gateways), array_keys($this->deferred)));
    }

    private function resolveAll(): void
    {
        foreach (array_keys($this->deferred) as $id) {
            $this->get($id);
        }
    }
}
