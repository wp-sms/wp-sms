<?php

namespace WSms\Integration\Marketing;

use WSms\Integration\Contracts\SupportsContactImport;
use WSms\Integration\IntegrationRegistry;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\SyncContactImportBatchJob;

defined('ABSPATH') || exit;

class ImportSyncManager
{
    public const STATE_KEY = 'wsms_marketing_sync_state';

    public function __construct(
        private readonly IntegrationRegistry $registry,
        private readonly QueueInterface $queue,
    ) {
    }

    public function startImport(string $integrationId): int
    {
        if ($this->isImporting($integrationId)) {
            return 0;
        }

        $integration = $this->resolveImportIntegration($integrationId);
        if (!$integration) {
            return 0;
        }

        $config = $this->getImportSettings($integrationId);
        $total = $integration->countImportable($config);

        if ($total === 0) {
            return 0;
        }

        $this->updateImportStats($integrationId, [
            'sync_in_progress' => true,
            'sync_progress'    => ['synced' => 0],
            'last_error'       => null,
        ]);

        $this->queue->dispatch(new SyncContactImportBatchJob($integrationId, 100, null));

        return $total;
    }

    public function processBatch(string $integrationId, int $batchSize, mixed $afterCursor): void
    {
        $integration = $this->resolveImportIntegration($integrationId);
        if (!$integration || !$integration->isConnected()) {
            $this->failImport($integrationId, 'Integration disconnected during import');
            return;
        }

        // Load state once for the entire batch operation
        $intState = $this->getImportState($integrationId);
        $stats = $intState['import_stats'] ?? [];
        if (!($stats['sync_in_progress'] ?? false)) {
            return;
        }

        $config = $intState['import_settings'] ?? [];

        try {
            $ids = $integration->getImportBatch($config, $batchSize, $afterCursor);
        } catch (\Throwable $e) {
            $this->failImport($integrationId, $e->getMessage());
            return;
        }

        if (empty($ids)) {
            $this->completeImport($integrationId, $stats['sync_progress']['synced'] ?? 0);
            return;
        }

        $synced = 0;
        $lastCursor = $afterCursor;

        foreach ($ids as $externalId) {
            try {
                $result = $integration->importOne($externalId, $config, suppressEvents: true);
                if ($result !== null) {
                    $synced++;
                }
            } catch (\Throwable) {
                // Skip failed individual records
            }
            $lastCursor = $externalId;
        }

        $previousSynced = $stats['sync_progress']['synced'] ?? 0;
        $currentSynced = $previousSynced + $synced;

        if (count($ids) === $batchSize) {
            $this->updateImportStats($integrationId, [
                'sync_progress' => ['synced' => $currentSynced],
            ]);
            $this->queue->dispatch(new SyncContactImportBatchJob($integrationId, $batchSize, $lastCursor));
        } else {
            $this->completeImport($integrationId, $currentSynced);
        }
    }

    public function isImporting(string $integrationId): bool
    {
        $stats = $this->getImportState($integrationId);
        return !empty($stats['import_stats']['sync_in_progress']);
    }

    public function getImportState(string $integrationId): ?array
    {
        $state = get_option(self::STATE_KEY, []);
        return $state[$integrationId] ?? null;
    }

    public function getImportStats(string $integrationId): array
    {
        return $this->getImportState($integrationId)['import_stats'] ?? [];
    }

    public function getImportSettings(string $integrationId): array
    {
        return $this->getImportState($integrationId)['import_settings'] ?? [];
    }

    public function saveImportSettings(string $integrationId, array $settings): void
    {
        $state = get_option(self::STATE_KEY, []);
        $state[$integrationId] = $state[$integrationId] ?? [];

        if (isset($settings['enabled']) && $settings['enabled'] === false) {
            unset($state[$integrationId]['import_settings']);
        } else {
            $settings['enabled'] = true;
            $state[$integrationId]['import_settings'] = $settings;
        }

        update_option(self::STATE_KEY, $state);
    }

    public function getContactCount(string $integrationId): int
    {
        return $this->getImportState($integrationId)['import_stats']['total_synced'] ?? 0;
    }

    private function completeImport(string $integrationId, int $totalSynced): void
    {
        $this->updateImportStats($integrationId, [
            'total_synced'     => $totalSynced,
            'last_synced_at'   => gmdate('c'),
            'sync_in_progress' => false,
            'sync_progress'    => null,
        ]);
    }

    private function failImport(string $integrationId, string $error): void
    {
        $this->updateImportStats($integrationId, [
            'sync_in_progress' => false,
            'sync_progress'    => null,
            'last_error'       => $error,
        ]);
    }

    private function updateImportStats(string $integrationId, array $stats): void
    {
        $state = get_option(self::STATE_KEY, []);
        $state[$integrationId] = $state[$integrationId] ?? [];
        $state[$integrationId]['import_stats'] = array_merge(
            $state[$integrationId]['import_stats'] ?? [],
            $stats,
        );
        update_option(self::STATE_KEY, $state);
    }

    private function resolveImportIntegration(string $integrationId): ?SupportsContactImport
    {
        $integration = $this->registry->get($integrationId);
        if (!$integration instanceof SupportsContactImport) {
            return null;
        }
        return $integration;
    }
}
