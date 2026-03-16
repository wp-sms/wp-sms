<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class FlowCompletedEvent extends Event
{
    public function __construct(
        public readonly string $flowId,
        public readonly string $executionId,
        public readonly string $status,
    ) {
    }
}
