<?php

namespace WSms\Integration\Webhook;

use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\ActionResult;
use WSms\Flow\Contracts\TriggerInterface;
use WSms\Integration\Contracts\IntegrationInterface;

defined('ABSPATH') || exit;

class WebhookIntegration implements IntegrationInterface
{
    public function getId(): string
    {
        return 'webhook';
    }

    public function getName(): string
    {
        return 'Webhook';
    }

    public function getCategory(): string
    {
        return 'communication';
    }

    public function getIcon(): string
    {
        return 'dashicons-rest-api';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAuthType(): string
    {
        return 'webhook_secret';
    }

    public function getAuthSchema(): array
    {
        return [];
    }

    public function getTriggers(): array
    {
        return [
            new class implements TriggerInterface {
                public function getId(): string { return 'webhook.inbound'; }
                public function getName(): string { return __('Inbound Webhook', 'wp-sms'); }
                public function getGroup(): string { return 'Webhook'; }
                public function getPayloadSchema(): array {
                    return [
                        'body'    => ['type' => 'object', 'label' => __('Request Body', 'wp-sms')],
                        'headers' => ['type' => 'object', 'label' => __('Request Headers', 'wp-sms')],
                    ];
                }
                public function subscribe(callable $callback): void {
                    add_action('wsms_webhook_received', function ($data) use ($callback) {
                        $callback($data);
                    });
                }
            },
        ];
    }

    public function getActions(): array
    {
        return [];
    }

    public function boot(): void
    {
    }
}
