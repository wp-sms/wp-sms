<?php

namespace WSms\Queue\Job;

use WSms\Queue\Contracts\JobInterface;

defined('ABSPATH') || exit;

class SyncContactImportBatchJob implements JobInterface
{
    public function __construct(
        private readonly string $integrationId,
        private readonly int $batchSize = 100,
        private readonly mixed $afterCursor = null,
    ) {
    }

    public function getType(): string
    {
        return 'sync_contact_import_batch';
    }

    public function getPayload(): array
    {
        return [
            'integration_id' => $this->integrationId,
            'batch_size'     => $this->batchSize,
            'after_cursor'   => $this->afterCursor,
        ];
    }

    public function getMaxRetries(): int
    {
        return 3;
    }

    public function getRetryBackoff(): int
    {
        return 60;
    }
}
