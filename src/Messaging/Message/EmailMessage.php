<?php

namespace WSms\Messaging\Message;

use WSms\Messaging\Contracts\MessageInterface;

defined('ABSPATH') || exit;

class EmailMessage implements MessageInterface
{
    public function __construct(
        private readonly string $recipient,
        private readonly string $body,
        private readonly string $subject = '',
        private readonly array $headers = [],
        private readonly ?string $flowExecutionId = null,
    ) {
    }

    public function getChannel(): string
    {
        return 'email';
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
        return [
            'subject' => $this->subject,
            'headers' => $this->headers,
        ];
    }

    public function getFlowExecutionId(): ?string
    {
        return $this->flowExecutionId;
    }

    public function getCampaignId(): ?string
    {
        return null;
    }
}
