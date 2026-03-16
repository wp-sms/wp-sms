<?php

namespace WSms\Queue\Job;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class SendMessageJob implements JobInterface
{
    public function __construct(
        private readonly string $gatewayId,
        private readonly string $channel,
        private readonly string $recipient,
        private readonly string $body,
        private readonly array $meta = [],
        private readonly ?string $executionId = null,
    ) {
    }

    public function getType(): string
    {
        return 'send_message';
    }

    public function getPayload(): array
    {
        return [
            'gateway_id'    => $this->gatewayId,
            'channel'       => $this->channel,
            'recipient'     => $this->recipient,
            'body'          => $this->body,
            'meta'          => $this->meta,
            'execution_id'  => $this->executionId,
        ];
    }

    public function getMaxRetries(): int
    {
        return 3;
    }

    public function getRetryBackoff(): int
    {
        return 60;
    }
}
