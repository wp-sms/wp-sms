<?php

namespace WSms\Queue\Job;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class MarketingPushContactJob implements JobInterface
{
    public function __construct(
        private readonly string $integrationId,
        private readonly array $contact,
    ) {
    }

    public function getType(): string
    {
        return 'marketing_push_contact';
    }

    public function getPayload(): array
    {
        return [
            'integration_id' => $this->integrationId,
            'contact'        => $this->contact,
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
