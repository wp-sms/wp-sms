<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class TriggerFiredEvent extends Event
{
    public function __construct(
        public readonly string $triggerType,
        public readonly array $payload,
    ) {
    }
}
