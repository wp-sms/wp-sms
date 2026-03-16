<?php

namespace WSms\Event\Events;

use WSms\Enums\EventType;
use WSms\Event\Event;

defined('ABSPATH') || exit;

class AuthEvent extends Event
{
    public function __construct(
        public readonly EventType $eventType,
        public readonly string $status,
        public readonly ?int $userId = null,
        public readonly array $meta = [],
    ) {
    }
}
