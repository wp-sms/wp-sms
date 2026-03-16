<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class MessageFailedEvent extends Event
{
    public function __construct(
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $gatewayId,
        public readonly string $error,
        public readonly ?string $executionId = null,
    ) {
    }
}
