<?php

namespace WSms\Messaging\Template\ValueObjects;

use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\Message\Message;

defined('ABSPATH') || exit;

class RenderedMessage
{
    public function __construct(
        public readonly string $body,
        public readonly ?string $subject = null,
        public readonly array $headers = [],
        public readonly array $meta = [],
    ) {
    }

    public function toMessage(string $channel, string $recipient, array $extraMeta = []): MessageInterface
    {
        if ($channel === 'email') {
            return new EmailMessage(
                $recipient,
                $this->body,
                $this->subject ?? '',
                $this->headers,
            );
        }

        return new Message($channel, $recipient, $this->body, meta: array_merge($this->meta, $extraMeta));
    }
}
