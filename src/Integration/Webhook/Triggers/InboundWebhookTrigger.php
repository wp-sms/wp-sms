<?php

namespace WSms\Integration\Webhook\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\Webhook\WebhookIntegration;

defined('ABSPATH') || exit;

class InboundWebhookTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'webhook.inbound';
    }

    public function getName(): string
    {
        return __('Inbound Webhook', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a webhook request is received', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Webhook';
    }

    public function getPayloadSchema(): array
    {
        return [
            'webhook_id' => [
                'type' => 'string',
                'label' => __('Webhook ID', 'wp-sms'),
                'description' => __('The ID of the webhook endpoint that received the request', 'wp-sms'),
                'example' => 'a1b2c3d4',
            ],
            'method' => [
                'type' => 'string',
                'label' => __('HTTP Method', 'wp-sms'),
                'description' => __('The HTTP method used for the request (POST, PUT)', 'wp-sms'),
                'example' => 'POST',
            ],
            'body' => [
                'type' => 'object',
                'label' => __('Request Body', 'wp-sms'),
                'description' => __('The parsed JSON body of the incoming webhook request', 'wp-sms'),
                'example' => ['event' => 'payment.received', 'amount' => 100],
            ],
            'headers' => [
                'type' => 'object',
                'label' => __('Request Headers', 'wp-sms'),
                'description' => __('HTTP headers from the incoming webhook request', 'wp-sms'),
                'example' => ['content-type' => 'application/json'],
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'webhook_id' => [
                'type' => 'string',
                'label' => __('Webhook Endpoint', 'wp-sms'),
                'description' => __('Only trigger for this specific webhook endpoint', 'wp-sms'),
                'dynamic' => true,
            ],
        ];
    }

    public function getFilterOptions(string $fieldKey): array
    {
        if ($fieldKey !== 'webhook_id') {
            return [];
        }

        $secrets = get_option(WebhookIntegration::SECRETS_OPTION, []);
        $options = [];

        foreach ($secrets as $id => $entry) {
            $options[] = [
                'value' => $id,
                'label' => $entry['label'] ?? $id,
            ];
        }

        return $options;
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_webhook_received', $callback);
    }
}
