<?php

namespace WSms\Messaging\Gateway;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;

defined('ABSPATH') || exit;

abstract class AbstractProvider implements GatewayInterface
{
    use UsesGatewayApi;

    private ?array $configCache = null;

    abstract public function getId(): string;

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
        $sharedSchema = $schema['shared'] ?? [];

        foreach ($sharedSchema as $key => $field) {
            if (!empty($field['required'])
                && $this->isFieldVisible($field, $shared, $sharedSchema)
                && empty($shared[$key])
            ) {
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
        $shared = $config['shared'] ?? [];
        $sharedSchema = $schema['shared'] ?? [];

        // Check all required shared fields (skip those hidden by an unmet show_if)
        foreach ($sharedSchema as $key => $field) {
            if (!empty($field['required'])
                && $this->isFieldVisible($field, $shared, $sharedSchema)
                && empty($shared[$key])
            ) {
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
        $shared = $config['shared'] ?? [];
        $sharedSchema = $schema['shared'] ?? [];

        // Check shared required fields (skip those hidden by an unmet show_if)
        foreach ($sharedSchema as $key => $field) {
            if (!empty($field['required'])
                && $this->isFieldVisible($field, $shared, $sharedSchema)
                && empty($shared[$key])
            ) {
                return false;
            }
        }

        // Check channel-specific required fields
        $channelSchema = $schema['channels'][$channel] ?? [];
        return $this->isChannelConfigComplete($config, $channelSchema, $channel);
    }

    public function getCredit(): ?string
    {
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        return TestConnectionResult::error(__('Connection testing is not supported for this gateway', 'wp-sms'));
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

    /**
     * Validate an HTTP response for test-connection, handling common error patterns.
     *
     * Handles network failures, rate-limiting, and malformed JSON.
     * For non-2xx responses, returns a generic error — providers should check
     * provider-specific status codes (401, 403, 404) BEFORE calling this.
     *
     * @param array|DeliveryResult $result   Return value from httpGet/httpPost
     * @param string               $provider Human-readable provider name for error messages
     *
     * @return array|TestConnectionResult Decoded JSON array on 2xx success, or error result
     */
    protected function validateTestResponse(array|DeliveryResult $result, string $provider): array|TestConnectionResult
    {
        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                sprintf(__('Could not reach the %s API. Check your server\'s internet connection.', 'wp-sms'), $provider)
            );
        }

        if ($result['code'] === 429) {
            return TestConnectionResult::error(__('Rate limited — please wait a moment and try again', 'wp-sms'));
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from %s (HTTP %d)', 'wp-sms'), $provider, $result['code'])
            );
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return TestConnectionResult::error(
                sprintf(__('Invalid response from %s', 'wp-sms'), $provider)
            );
        }

        return $data;
    }

    /**
     * Temporarily use the given config (e.g., unsaved draft from the admin form)
     * so that getSharedConfig(), getChannelConfig(), and provider-specific auth
     * helpers work against it instead of the DB-stored config.
     *
     * @template T
     * @param array    $config Gateway config array with 'shared' and 'channels' keys
     * @param callable(): T $fn Callback to run with the temporary config
     * @return T
     */
    protected function withConfig(array $config, callable $fn): mixed
    {
        $previous = $this->configCache;
        $this->configCache = $config;
        try {
            return $fn();
        } finally {
            $this->configCache = $previous;
        }
    }

    /**
     * HTTP GET that decodes JSON and throws on any error.
     *
     * Handles network failures, 401/403, 429 rate-limiting, non-2xx, and
     * malformed JSON — the common boilerplate every provider repeats.
     *
     * @throws \RuntimeException with a user-facing error message
     */
    protected function fetchJsonOrFail(string $url, array $args = []): array
    {
        $result = $this->httpGet($url, $args);

        if ($result instanceof DeliveryResult) {
            throw new \RuntimeException(
                sprintf(__('Could not reach the %s API. Check your server\'s internet connection.', 'wp-sms'), $this->getName()),
            );
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            throw new \RuntimeException(__('Invalid credentials', 'wp-sms'));
        }

        if ($result['code'] === 429) {
            throw new \RuntimeException(__('Rate limited — please wait a moment and try again', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            throw new \RuntimeException(
                $data['message'] ?? $data['error-text'] ?? sprintf("HTTP %d", $result['code']),
            );
        }

        if (!is_array($data)) {
            throw new \RuntimeException(
                sprintf(__('Invalid response from %s', 'wp-sms'), $this->getName()),
            );
        }

        return $data;
    }

    private function isChannelConfigComplete(array $config, array $channelSchema, ?string $channel = null): bool
    {
        if (empty($channelSchema)) {
            return true;
        }

        $channelConfig = $channel ? ($config['channels'][$channel] ?? []) : [];

        foreach ($channelSchema as $key => $field) {
            if (!empty($field['required'])
                && $this->isFieldVisible($field, $channelConfig, $channelSchema)
                && empty($channelConfig[$key])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a config field should be considered active given the current values.
     *
     * Mirrors the dashboard's show_if logic so backend validation matches the form
     * the user sees: a hidden field is not required, even if its schema says so.
     * Falls back to the dependency field's default when no value is saved yet.
     *
     * @param array $field         The field schema entry being checked.
     * @param array $sectionValues Saved values for the same section (shared or one channel).
     * @param array $sectionSchema Full schema for the same section.
     */
    protected function isFieldVisible(array $field, array $sectionValues, array $sectionSchema): bool
    {
        if (empty($field['show_if']) || !is_array($field['show_if'])) {
            return true;
        }
        $depKey = $field['show_if']['field'] ?? null;
        if ($depKey === null) {
            return true;
        }
        $expected = $field['show_if']['equals'] ?? null;
        $depDefault = $sectionSchema[$depKey]['default'] ?? null;
        $current = $sectionValues[$depKey] ?? $depDefault;

        return $current === $expected;
    }
}
