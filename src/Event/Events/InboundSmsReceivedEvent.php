<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class InboundSmsReceivedEvent extends Event
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $body,
        public readonly ?string $gatewayId = null,
        public readonly ?string $providerId = null,
    ) {
    }
}
