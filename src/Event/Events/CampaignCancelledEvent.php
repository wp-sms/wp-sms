<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class CampaignCancelledEvent extends Event
{
    public function __construct(
        public readonly string $campaignId,
    ) {
    }
}
