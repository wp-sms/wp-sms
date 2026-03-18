<?php

namespace WSms\Queue\Job;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class ProcessCampaignBatchJob implements JobInterface
{
    public function __construct(
        private readonly string $campaignId,
        private readonly ?string $afterId = null,
        private readonly int $batchSize = 500,
    ) {
    }

    public function getType(): string
    {
        return 'process_campaign_batch';
    }

    public function getPayload(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'after_id'    => $this->afterId,
            'batch_size'  => $this->batchSize,
        ];
    }

    public function getMaxRetries(): int
    {
        return 3;
    }

    public function getRetryBackoff(): int
    {
        return 120;
    }
}
