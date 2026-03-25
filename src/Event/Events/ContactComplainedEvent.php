<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class ContactComplainedEvent extends Event
{
    public function __construct(
        public readonly string $contactId,
        public readonly ?string $errorCode = null,
    ) {
    }
}
