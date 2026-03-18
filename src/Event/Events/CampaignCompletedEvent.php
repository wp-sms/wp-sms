<?php

namespace WSms\Event\Events;

use WSms\Event\Event;

defined('ABSPATH') || exit;

class CampaignCompletedEvent extends Event
{
    public function __construct(
        public readonly string $campaignId,
        public readonly int $sentCount,
        public readonly int $failedCount,
        public readonly int $skippedCount,
    ) {
    }
}
