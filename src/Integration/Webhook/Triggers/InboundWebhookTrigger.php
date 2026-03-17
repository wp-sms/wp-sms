<?php

namespace WSms\Integration\Webhook\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

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

    public function getGroup(): string
    {
        return 'Webhook';
    }

    public function getPayloadSchema(): array
    {
        return [
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

    public function subscribe(callable $callback): void
    {
        add_action('wsms_webhook_received', $callback);
    }
}
