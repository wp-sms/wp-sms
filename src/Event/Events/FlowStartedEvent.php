<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class FlowStartedEvent extends Event
{
    public function __construct(
        public readonly string $flowId,
        public readonly string $executionId,
        public readonly string $triggerType,
        public readonly array $triggerData,
    ) {
    }
}
