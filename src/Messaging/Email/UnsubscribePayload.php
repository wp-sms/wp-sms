<?php

namespace WSms\Messaging\Email;

defined('ABSPATH') || exit;

class UnsubscribePayload
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $campaignId,
    ) {
    }
}
