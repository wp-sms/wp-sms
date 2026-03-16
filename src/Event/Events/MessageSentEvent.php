<?php

namespace WSms\Event\Events;

use WSms\Event\Event;
use WSms\Messaging\Contracts\DeliveryResult;

defined('ABSPATH') || exit;

class MessageSentEvent extends Event
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $gatewayId,
        public readonly DeliveryResult $result,
        public readonly ?string $executionId = null,
    ) {
    }
}
