<?php

namespace WSms\Migration;

use WSms\Log\WpLogger;
use WSms\Migration\Contracts\PluginMigratorInterface;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\MigrationBatchJob;

defined('ABSPATH') || exit;

class MigrationBatchHandler
{
    public function __construct(
        private readonly MigrationManager $manager,
        private readonly MigrationStateManager $stateManager,
        private readonly QueueInterface $queue,
        private readonly WpLogger $logger,
    ) {
    }

    public function handle(array $payload): void
    {
        $migratorId = $payload['migrator_id'];
        $stepId = $payload['step_id'];
        $cursor = $payload['cursor'] ?? null;
        $batchSize = $payload['batch_size'] ?? self::adaptiveBatchSize();

        $state = $this->stateManager->getState();
        if ($state === null) {
            return;
        }

        // Abort if paused or cancelled.
        $status = MigrationStatus::tryFrom($state['status'] ?? '');
        if ($status !== MigrationStatus::Running) {
            return;
        }

        // Version safety: pause if plugin was updated mid-migration.
        if (defined('WP_SMS_VERSION') && ($state['plugin_version'] ?? '') !== WP_SMS_VERSION) {
            $this->stateManager->transitionStatus(MigrationStatus::Paused);
            $this->logger->warning('Migration paused: plugin version changed during migration.');
            return;
        }

        $migrator = $this->manager->getMigrator($migratorId);
        if ($migrator === null) {
            $this->stateManager->markStepFailed($stepId, __('Migrator not found.', 'wp-sms'));
            return;
        }

        try {
            $options = $state['options'] ?? [];
            $options['conflict_resolution'] = $state['conflict_resolution'] ?? ConflictResolution::Skip->value;
            $options['batch_size'] = $batchSize;

            $result = $migrator->migrateBatch($stepId, $options, $cursor);

            // Update step progress.
            $this->stateManager->updateStepProgress($stepId, [
                'processed_items' => ($state['steps'][$stepId]['processed_items'] ?? 0) + $result->processed,
                'migrated_count'  => ($state['steps'][$stepId]['migrated_count'] ?? 0) + $result->migrated,
                'skipped_count'   => ($state['steps'][$stepId]['skipped_count'] ?? 0) + $result->skipped,
                'failed_count'    => ($state['steps'][$stepId]['failed_count'] ?? 0) + $result->failed,
                'conflict_count'  => ($state['steps'][$stepId]['conflict_count'] ?? 0) + $result->conflicts,
                'last_cursor'     => $result->nextCursor,
                'errors'          => $result->errors,
            ]);

            if ($result->done) {
                $this->stateManager->markStepCompleted($stepId);
                $this->advanceToNextStep($migrator);
            } else {
                // Dispatch next batch.
                $this->dispatchNext($migratorId, $stepId, $result->nextCursor, $batchSize);
            }
        } catch (\Throwable $e) {
            $this->logger->error("Migration batch failed: {$e->getMessage()}", ['exception' => $e]);
            $this->stateManager->markStepFailed($stepId, $e->getMessage());
            $this->stateManager->transitionStatus(MigrationStatus::Failed);
        }
    }

    private function advanceToNextStep(PluginMigratorInterface $migrator): void
    {
        $nextStepId = $this->stateManager->getNextPendingStep();

        if ($nextStepId === null) {
            // All steps done.
            if ($this->stateManager->areAllStepsComplete()) {
                $this->stateManager->transitionStatus(MigrationStatus::Completed);
            }
            return;
        }

        // Set total items for next step.
        foreach ($migrator->getSteps() as $step) {
            if ($step->getId() === $nextStepId) {
                $this->stateManager->setStepTotalItems($nextStepId, $step->getTotalCount());
                break;
            }
        }

        $this->stateManager->markStepRunning($nextStepId);
        $this->dispatchNext($migrator->getId(), $nextStepId, null, self::adaptiveBatchSize());
    }

    private function dispatchNext(string $migratorId, string $stepId, ?string $cursor, int $batchSize): void
    {
        $this->queue->dispatch(new MigrationBatchJob($migratorId, $stepId, $cursor, $batchSize));
    }

    public static function adaptiveBatchSize(): int
    {
        $memoryLimit = self::parseMemoryLimit(ini_get('memory_limit') ?: '256M');

        if ($memoryLimit < 128 * 1024 * 1024) {
            return 100;
        }
        if ($memoryLimit < 256 * 1024 * 1024) {
            return 200;
        }

        return 500;
    }

    private static function parseMemoryLimit(string $limit): int
    {
        $value = (int) $limit;
        $unit = strtolower(substr(trim($limit), -1));

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
