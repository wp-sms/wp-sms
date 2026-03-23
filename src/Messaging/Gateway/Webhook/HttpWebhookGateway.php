<?php

namespace WSms\Messaging\Gateway\Webhook;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;

defined('ABSPATH') || exit;

class HttpWebhookGateway implements GatewayInterface
{
    public function getId(): string
    {
        return 'webhook';
    }

    public function getName(): string
    {
        return __('HTTP Webhook', 'wp-sms');
    }

    public function getSupportedChannels(): array
    {
        return ['webhook'];
    }

    public function send(MessageInterface $message): DeliveryResult
    {
        $meta = $message->getMeta();
        $method = strtoupper($meta['method'] ?? 'POST');
        $headers = $meta['headers'] ?? ['Content-Type' => 'application/json'];

        if (isset($meta['log_id'])) {
            $headers['X-WSMS-Delivery-Id'] = $meta['log_id'];
        }

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'body'    => $message->getBody(),
            'timeout' => 30,
        ];

        $response = wp_remote_post($message->getRecipient(), $args);

        if (is_wp_error($response)) {
            return DeliveryResult::failed($response->get_error_message(), [], true);
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return DeliveryResult::sent(null, null, ['http_status' => $code]);
        }

        $retryable = $code >= 500 || $code === 429;

        return DeliveryResult::failed(
            sprintf('HTTP %d: %s', $code, mb_substr(wp_remote_retrieve_body($response), 0, 500)),
            ['http_status' => $code],
            $retryable,
        );
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [],
            'channels' => [],
        ];
    }

    public function validateConfig(array $config): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function isConfiguredForChannel(string $channel): bool
    {
        return $channel === 'webhook';
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Send data to any URL via HTTP request', 'wp-sms'),
        ];
    }

    public function getFeatures(): array
    {
        return ['unicode' => true];
    }

    public function getCredit(): ?string
    {
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        return TestConnectionResult::error(__('Connection testing is not supported for this gateway', 'wp-sms'));
    }
}
