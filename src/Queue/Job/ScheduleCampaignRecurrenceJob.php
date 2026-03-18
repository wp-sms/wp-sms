<?php

namespace WSms\Queue\Job;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class ScheduleCampaignRecurrenceJob implements JobInterface
{
    public function __construct(
        private readonly string $campaignId,
    ) {
    }

    public function getType(): string
    {
        return 'schedule_campaign_recurrence';
    }

    public function getPayload(): array
    {
        return [
            'campaign_id' => $this->campaignId,
        ];
    }

    public function getMaxRetries(): int
    {
        return 1;
    }

    public function getRetryBackoff(): int
    {
        return 300;
    }
}
