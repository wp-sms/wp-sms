<?php

namespace WSms\Messaging\Message;

use WSms\Messaging\Contracts\MessageInterface;

defined('ABSPATH') || exit;

class TelegramMessage implements MessageInterface
{
    public function __construct(
        private readonly string $chatId,
        private readonly string $body,
        private readonly ?string $flowExecutionId = null,
    ) {
    }

    public function getChannel(): string
    {
        return 'telegram';
    }

    public function getRecipient(): string
    {
        return $this->chatId;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getMeta(): array
    {
        return [];
    }

    public function getFlowExecutionId(): ?string
    {
        return $this->flowExecutionId;
    }
}
