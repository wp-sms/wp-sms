<?php

namespace WSms\Messaging\Message;

use WSms\Messaging\Contracts\MessageInterface;

defined('ABSPATH') || exit;

class WebhookMessage implements MessageInterface
{
    public function __construct(
        private readonly string $url,
        private readonly string $body,
        private readonly string $method = 'POST',
        private readonly array $headers = [],
        private readonly ?string $flowExecutionId = null,
    ) {
    }

    public function getChannel(): string
    {
        return 'webhook';
    }

    public function getRecipient(): string
    {
        return $this->url;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getMeta(): array
    {
        return [
            'method'  => $this->method,
            'headers' => $this->headers,
        ];
    }

    public function getFlowExecutionId(): ?string
    {
        return $this->flowExecutionId;
    }
}
