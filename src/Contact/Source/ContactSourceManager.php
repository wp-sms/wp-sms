<?php

namespace WSms\Contact\Source;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\SyncContactSourceBatchJob;

defined('ABSPATH') || exit;

class ContactSourceManager
{
    public const OPTION_KEY = 'wsms_contact_sources';

    public function __construct(
        private readonly ContactSourceRegistry $registry,
        private readonly ContactRepositoryInterface $contacts,
        private readonly QueueInterface $queue,
    ) {
    }

    // ── wp_option CRUD ──

    public function getAll(): array
    {
        return get_option(self::OPTION_KEY, []);
    }

    public function get(string $type): ?array
    {
        $all = $this->getAll();
        return $all[$type] ?? null;
    }

    public function save(string $type, array $data): void
    {
        $all = $this->getAll();
        $all[$type] = $data;
        update_option(self::OPTION_KEY, $all);
    }

    public function delete(string $type): void
    {
        $all = $this->getAll();
        unset($all[$type]);
        update_option(self::OPTION_KEY, $all);
    }

    public function updateStats(string $type, array $stats): void
    {
        $all = $this->getAll();
        if (!isset($all[$type])) {
            return;
        }

        $all[$type]['stats'] = array_merge($all[$type]['stats'] ?? [], $stats);
        update_option(self::OPTION_KEY, $all);
    }

    public function updateStatus(string $type, string $status): void
    {
        $all = $this->getAll();
        if (!isset($all[$type])) {
            return;
        }

        $all[$type]['status'] = $status;
        update_option(self::OPTION_KEY, $all);
    }

    // ── Sync orchestration ──

    public function startSync(string $type): void
    {
        if ($this->isSyncing($type)) {
            return;
        }

        $existing = $this->get($type);
        if (!$existing) {
            return;
        }

        $existing['status'] = 'syncing';
        $existing['stats'] = array_merge($existing['stats'] ?? [], [
            'sync_in_progress' => true,
            'sync_progress'    => ['synced' => 0],
            'last_error'       => null,
        ]);
        $this->save($type, $existing);

        $this->queue->dispatch(new SyncContactSourceBatchJob($type, 100, null));
    }

    public function processBatch(string $type, int $batchSize, ?int $afterId): void
    {
        $source = $this->registry->get($type);
        $stored = $this->get($type);

        if (!$source || !$stored || $stored['status'] !== 'syncing') {
            return;
        }

        $config = $stored['config'] ?? [];

        try {
            $ids = $source->getBatch($config, $batchSize, $afterId);
        } catch (\Throwable $e) {
            $this->failSync($type, $e->getMessage());
            return;
        }

        $synced = 0;
        $lastId = $afterId;

        foreach ($ids as $externalId) {
            try {
                $result = $source->syncOne($externalId, $config, suppressEvents: true);
                if ($result !== null) {
                    $synced++;
                }
            } catch (\Throwable) {
                // Skip failed individual records, continue with batch
            }
            $lastId = (int) $externalId;
        }

        // Update stats — reuse $stored to avoid extra get_option
        $previousSynced = $stored['stats']['sync_progress']['synced'] ?? 0;
        $currentSynced = $previousSynced + $synced;
        $stored['stats'] = array_merge($stored['stats'] ?? [], [
            'sync_progress' => ['synced' => $currentSynced],
        ]);
        $this->save($type, $stored);

        // Queue next batch or complete
        if (count($ids) === $batchSize) {
            $this->queue->dispatch(new SyncContactSourceBatchJob($type, $batchSize, $lastId));
        } else {
            $this->completeSync($type, $currentSynced);
        }
    }

    public function isSyncing(string $type): bool
    {
        $stored = $this->get($type);
        return $stored && $stored['status'] === 'syncing';
    }

    public function getContactCount(string $type): int
    {
        return $this->contacts->count(['source' => $type]);
    }

    private function completeSync(string $type, int $totalSynced): void
    {
        $all = $this->getAll();
        if (!isset($all[$type])) {
            return;
        }

        $all[$type]['status'] = 'connected';
        $all[$type]['stats'] = array_merge($all[$type]['stats'] ?? [], [
            'total_synced'     => $totalSynced,
            'last_synced_at'   => gmdate('c'),
            'sync_in_progress' => false,
            'sync_progress'    => null,
        ]);
        update_option(self::OPTION_KEY, $all);
    }

    private function failSync(string $type, string $error): void
    {
        $all = $this->getAll();
        if (!isset($all[$type])) {
            return;
        }

        $all[$type]['status'] = 'error';
        $all[$type]['stats'] = array_merge($all[$type]['stats'] ?? [], [
            'sync_in_progress' => false,
            'sync_progress'    => null,
            'last_error'       => $error,
        ]);
        update_option(self::OPTION_KEY, $all);
    }
}
