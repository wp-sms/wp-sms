<?php

namespace WSms\Migration;

use WSms\Exception\ConflictException;
use WSms\Exception\NotFoundException;
use WSms\Migration\Contracts\PluginMigratorInterface;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\MigrationBatchJob;

defined('ABSPATH') || exit;

class MigrationManager
{
    /** @var array<string, PluginMigratorInterface> */
    private array $migrators = [];

    public function __construct(
        private readonly MigrationStateManager $stateManager,
        private readonly QueueInterface $queue,
    ) {
    }

    public function registerMigrator(PluginMigratorInterface $migrator): void
    {
        $this->migrators[$migrator->getId()] = $migrator;
    }

    public function getMigrator(string $id): ?PluginMigratorInterface
    {
        return $this->migrators[$id] ?? null;
    }

    /** @return DetectionResult[] keyed by migrator ID */
    public function getAvailableMigrators(): array
    {
        $available = [];
        foreach ($this->migrators as $id => $migrator) {
            $result = $migrator->detect();
            if ($result->dataFound) {
                $available[$id] = $result;
            }
        }
        return $available;
    }

    /**
     * @throws ConflictException If a migration is already running.
     * @throws NotFoundException If the migrator doesn't exist.
     */
    public function startMigration(
        string $migratorId,
        ConflictResolution $conflictResolution,
        array $options = [],
    ): void {
        $migrator = $this->migrators[$migratorId] ?? null;
        if ($migrator === null) {
            throw new NotFoundException(__('Migration adapter not found.', 'wp-sms'));
        }

        $steps = $migrator->getSteps();
        $applicableSteps = array_filter($steps, fn($s) => $s->isApplicable());

        // Sort by priority.
        usort($applicableSteps, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

        $stepIds = array_map(fn($s) => $s->getId(), $applicableSteps);

        // Initialize state (throws ConflictException if already running).
        $this->stateManager->initializeState($migratorId, $stepIds, $conflictResolution, $options);

        // Transition to running.
        $this->stateManager->transitionStatus(MigrationStatus::Running);

        // Start first step.
        $firstStepId = $stepIds[0] ?? null;
        if ($firstStepId === null) {
            $this->stateManager->transitionStatus(MigrationStatus::Completed);
            return;
        }

        // Set total items for the first step.
        foreach ($applicableSteps as $step) {
            if ($step->getId() === $firstStepId) {
                $this->stateManager->setStepTotalItems($firstStepId, $step->getTotalCount());
                break;
            }
        }

        $this->stateManager->markStepRunning($firstStepId);

        // Dispatch first batch.
        $this->dispatchBatch($migratorId, $firstStepId, null);
    }

    public function pauseMigration(): void
    {
        $this->stateManager->transitionStatus(MigrationStatus::Paused);
    }

    public function resumeMigration(): void
    {
        $state = $this->stateManager->getState();
        if ($state === null) {
            throw new NotFoundException(__('No migration state found.', 'wp-sms'));
        }

        $this->stateManager->transitionStatus(MigrationStatus::Running);

        // Find the step that was running and resume from its cursor.
        foreach ($state['steps'] as $stepId => $step) {
            if ($step['status'] === MigrationStatus::Running->value) {
                $this->dispatchBatch($state['migrator_id'], $stepId, $step['last_cursor']);
                return;
            }
        }

        // If no running step, find next pending one.
        $nextStepId = $this->stateManager->getNextPendingStep();
        if ($nextStepId !== null) {
            $this->stateManager->markStepRunning($nextStepId);
            $this->dispatchBatch($state['migrator_id'], $nextStepId, null);
        }
    }

    public function rollback(): RollbackResult
    {
        $state = $this->stateManager->getState();
        if ($state === null) {
            throw new NotFoundException(__('No migration state found.', 'wp-sms'));
        }

        $migrator = $this->migrators[$state['migrator_id']] ?? null;
        if ($migrator === null) {
            throw new NotFoundException(__('Migration adapter not found.', 'wp-sms'));
        }

        $totalRolledBack = 0;
        $totalFailed = 0;
        $allErrors = [];

        // Rollback in reverse step order.
        $stepIds = array_keys($state['steps']);
        foreach (array_reverse($stepIds) as $stepId) {
            $stepState = $state['steps'][$stepId];
            if ($stepState['status'] === MigrationStatus::Pending->value) {
                continue;
            }

            $result = $migrator->rollback($stepId);
            $totalRolledBack += $result->rolledBack;
            $totalFailed += $result->failed;
            $allErrors = array_merge($allErrors, $result->errors);
        }

        $this->stateManager->transitionStatus(MigrationStatus::RolledBack);

        return new RollbackResult($totalRolledBack, $totalFailed, $allErrors);
    }

    public function getProgress(): array
    {
        return $this->stateManager->getProgress();
    }

    private function dispatchBatch(string $migratorId, string $stepId, ?string $cursor): void
    {
        $this->queue->dispatch(new MigrationBatchJob(
            $migratorId,
            $stepId,
            $cursor,
            MigrationBatchHandler::adaptiveBatchSize(),
        ));
    }
}
