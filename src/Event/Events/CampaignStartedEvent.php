<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class CampaignStartedEvent extends Event
{
    public function __construct(
        public readonly string $campaignId,
        public readonly string $channel,
        public readonly int $totalRecipients,
    ) {
    }
}
