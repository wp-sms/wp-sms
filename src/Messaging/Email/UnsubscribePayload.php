<?php

namespace WSms\Messaging\Email;

defined('ABSPATH') || exit;

readonly class UnsubscribePayload
{
    public function __construct(
        public string $email,
        public ?string $campaignId,
    ) {
    }
}
