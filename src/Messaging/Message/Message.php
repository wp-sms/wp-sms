<?php

namespace WSms\Messaging\Message;

use WSms\Messaging\Contracts\MessageInterface;

defined('ABSPATH') || exit;

class Message implements MessageInterface
{
    public function __construct(
        private readonly string $channel,
        private readonly string $recipient,
        private readonly string $body,
        private readonly ?string $flowExecutionId = null,
        private readonly array $meta = [],
    ) {
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getFlowExecutionId(): ?string
    {
        return $this->flowExecutionId;
    }
}
