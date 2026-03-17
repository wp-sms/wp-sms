<?php

namespace WSms\Integration\Webhook;

use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Webhook\Triggers\InboundWebhookTrigger;

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
            new InboundWebhookTrigger(),
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
