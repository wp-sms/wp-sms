<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class ContactOptedInEvent extends Event
{
    public function __construct(
        public readonly array $contactIds,
        public readonly string $phone,
        public readonly string $source,
        public readonly ?string $gatewayId = null,
        public readonly ?string $keyword = null,
        public readonly ?string $channel = null,
    ) {
    }
}
