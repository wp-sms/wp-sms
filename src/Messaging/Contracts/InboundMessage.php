<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

class InboundMessage
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $body,
        public readonly ?string $providerId = null,
        public readonly ?string $optOutType = null,
        public readonly array $meta = [],
    ) {
    }
}
