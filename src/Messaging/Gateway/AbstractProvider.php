<?php

namespace WSms\Messaging\Gateway;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;

defined('ABSPATH') || exit;

abstract class AbstractProvider implements GatewayInterface
{
    private ?array $configCache = null;

    abstract public function getId(): string;

    abstract public function getName(): string;

    /** @return string[] */
    abstract public function getSupportedChannels(): array;

    /**
     * @return array{shared: array<string, array>, channels?: array<string, array<string, array>>}
     */
    abstract public function getConfigSchema(): array;

    abstract protected function doSend(MessageInterface $message): DeliveryResult;

    public function send(MessageInterface $message): DeliveryResult
    {
        try {
            return $this->doSend($message);
        } catch (\Throwable $e) {
            return DeliveryResult::failed($e->getMessage());
        }
    }

    public function validateConfig(array $config): bool
    {
        $schema = $this->getConfigSchema();
        $shared = $config['shared'] ?? [];

        foreach ($schema['shared'] ?? [] as $key => $field) {
            if (!empty($field['required']) && empty($shared[$key])) {
                return false;
            }
        }

        return true;
    }

    public function isConfigured(): bool
    {
        $config = $this->getConfig();
        if (empty($config)) {
            return false;
        }

        $schema = $this->getConfigSchema();

        // Check all required shared fields
        foreach ($schema['shared'] ?? [] as $key => $field) {
            if (!empty($field['required']) && empty($config['shared'][$key])) {
                return false;
            }
        }

        // At least one channel must be configured (or no channel-specific fields required)
        $channelSchemas = $schema['channels'] ?? [];
        if (empty($channelSchemas)) {
            return true;
        }

        foreach ($this->getSupportedChannels() as $channel) {
            if ($this->isChannelConfigComplete($config, $channelSchemas[$channel] ?? [], $channel)) {
                return true;
            }
        }

        return false;
    }

    public function isConfiguredForChannel(string $channel): bool
    {
        if (!in_array($channel, $this->getSupportedChannels())) {
            return false;
        }

        $config = $this->getConfig();
        if (empty($config)) {
            return false;
        }

        $schema = $this->getConfigSchema();

        // Check shared required fields
        foreach ($schema['shared'] ?? [] as $key => $field) {
            if (!empty($field['required']) && empty($config['shared'][$key])) {
                return false;
            }
        }

        // Check channel-specific required fields
        $channelSchema = $schema['channels'][$channel] ?? [];
        return $this->isChannelConfigComplete($config, $channelSchema, $channel);
    }

    public function getMetadata(): array
    {
        return [];
    }

    public function getFeatures(): array
    {
        return [
            'mms'              => false,
            'flash_sms'        => false,
            'delivery_receipt' => false,
            'incoming'         => false,
            'unicode'          => true,
        ];
    }

    public function getCredit(): ?string
    {
        return null;
    }

    protected function getConfig(): array
    {
        if ($this->configCache === null) {
            $configs = get_option('wsms_gateway_configs', []);
            $this->configCache = $configs[$this->getId()] ?? [];
        }
        return $this->configCache;
    }

    protected function getSharedConfig(string $key, mixed $default = null): mixed
    {
        $config = $this->getConfig();
        return $config['shared'][$key] ?? $default;
    }

    protected function getChannelConfig(string $channel, string $key, mixed $default = null): mixed
    {
        $config = $this->getConfig();
        return $config['channels'][$channel][$key] ?? $default;
    }

    /**
     * @return array{response: array, body: string, code: int}|DeliveryResult
     */
    protected function httpPost(string $url, array $args = []): array|DeliveryResult
    {
        $defaults = ['timeout' => 30];
        $response = wp_remote_post($url, array_merge($defaults, $args));

        if (is_wp_error($response)) {
            return DeliveryResult::failed($response->get_error_message());
        }

        return [
            'response' => $response,
            'body'     => wp_remote_retrieve_body($response),
            'code'     => (int) wp_remote_retrieve_response_code($response),
        ];
    }

    /**
     * @return array{response: array, body: string, code: int}|DeliveryResult
     */
    protected function httpGet(string $url, array $args = []): array|DeliveryResult
    {
        $defaults = ['timeout' => 30];
        $response = wp_remote_get($url, array_merge($defaults, $args));

        if (is_wp_error($response)) {
            return DeliveryResult::failed($response->get_error_message());
        }

        return [
            'response' => $response,
            'body'     => wp_remote_retrieve_body($response),
            'code'     => (int) wp_remote_retrieve_response_code($response),
        ];
    }

    private function isChannelConfigComplete(array $config, array $channelSchema, ?string $channel = null): bool
    {
        if (empty($channelSchema)) {
            return true;
        }

        $channelConfig = $channel ? ($config['channels'][$channel] ?? []) : [];

        foreach ($channelSchema as $key => $field) {
            if (!empty($field['required']) && empty($channelConfig[$key])) {
                return false;
            }
        }

        return true;
    }
}
