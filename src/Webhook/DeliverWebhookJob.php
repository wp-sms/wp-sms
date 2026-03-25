<?php

namespace WSms\Webhook;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class DeliverWebhookJob implements JobInterface
{
    public function __construct(
        private readonly string $webhookId,
        private readonly string $url,
        private readonly string $event,
        private readonly array $payload,
        private readonly string $secret,
    ) {
    }

    public function getType(): string
    {
        return 'deliver_webhook';
    }

    public function getPayload(): array
    {
        return [
            'webhook_id' => $this->webhookId,
            'url'        => $this->url,
            'event'      => $this->event,
            'payload'    => $this->payload,
            'secret'     => $this->secret,
        ];
    }

    public function getMaxRetries(): int
    {
        return 4;
    }

    public function getRetryBackoff(): int
    {
        return 30;
    }
}
