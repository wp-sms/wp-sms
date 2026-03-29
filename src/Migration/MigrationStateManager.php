<?php

namespace WSms\Migration;

use WSms\Exception\ConflictException;
use WSms\Exception\ValidationException;

defined('ABSPATH') || exit;

class MigrationStateManager
{
    private const OPTION_KEY = 'wsms_migration_state';

    public function getState(): ?array
    {
        $state = get_option(self::OPTION_KEY);
        return is_array($state) ? $state : null;
    }

    public function getStatus(): ?MigrationStatus
    {
        $state = $this->getState();
        if ($state === null) {
            return null;
        }

        return MigrationStatus::tryFrom($state['status'] ?? '');
    }

    public function getStepStatus(string $stepId): ?MigrationStatus
    {
        $state = $this->getState();
        if ($state === null || !isset($state['steps'][$stepId])) {
            return null;
        }

        return MigrationStatus::tryFrom($state['steps'][$stepId]['status'] ?? '');
    }

    /**
     * @throws ConflictException If a migration is already running.
     */
    public function initializeState(
        string $migratorId,
        array $stepIds,
        ConflictResolution $conflictResolution,
        array $options = [],
    ): void {
        $current = $this->getState();
        if ($current !== null) {
            $status = MigrationStatus::tryFrom($current['status'] ?? '');
            if ($status === MigrationStatus::Running) {
                $createdBy = $current['created_by'] ?? 0;
                $user = get_userdata($createdBy);
                $name = $user ? $user->display_name : __('Unknown', 'wp-sms');
                throw new ConflictException(
                    sprintf(__('Migration already in progress (started by %s).', 'wp-sms'), $name),
                );
            }
        }

        $steps = [];
        foreach ($stepIds as $id) {
            $steps[$id] = [
                'status'          => MigrationStatus::Pending->value,
                'total_items'     => 0,
                'processed_items' => 0,
                'migrated_count'  => 0,
                'skipped_count'   => 0,
                'failed_count'    => 0,
                'conflict_count'  => 0,
                'last_cursor'     => null,
                'errors'          => [],
            ];
        }

        $state = [
            'migrator_id'         => $migratorId,
            'status'              => MigrationStatus::Pending->value,
            'conflict_resolution' => $conflictResolution->value,
            'options'             => $options,
            'plugin_version'      => WP_SMS_VERSION,
            'steps'               => $steps,
            'started_at'          => null,
            'completed_at'        => null,
            'created_by'          => get_current_user_id(),
        ];

        update_option(self::OPTION_KEY, $state, false);
    }

    public function transitionStatus(MigrationStatus $newStatus): void
    {
        $state = $this->getState();
        if ($state === null) {
            throw new ValidationException(['state' => __('No migration state found.', 'wp-sms')]);
        }

        $current = MigrationStatus::from($state['status']);
        if (!$current->canTransitionTo($newStatus)) {
            throw new ValidationException(['status' => sprintf(
                __('Cannot transition from %s to %s.', 'wp-sms'),
                $current->value,
                $newStatus->value,
            )]);
        }

        $state['status'] = $newStatus->value;

        if ($newStatus === MigrationStatus::Running && $state['started_at'] === null) {
            $state['started_at'] = current_time('mysql', true);
        }

        if ($newStatus->isTerminal()) {
            $state['completed_at'] = current_time('mysql', true);
        }

        update_option(self::OPTION_KEY, $state, false);
    }

    public function updateStepProgress(string $stepId, array $update): void
    {
        $state = $this->getState();
        if ($state === null || !isset($state['steps'][$stepId])) {
            return;
        }

        $step = &$state['steps'][$stepId];
        foreach ($update as $key => $value) {
            if ($key === 'errors' && is_array($value)) {
                $step['errors'] = array_merge($step['errors'] ?? [], $value);
            } else {
                $step[$key] = $value;
            }
        }

        update_option(self::OPTION_KEY, $state, false);
    }

    public function setStepTotalItems(string $stepId, int $total): void
    {
        $this->updateStepProgress($stepId, ['total_items' => $total]);
    }

    public function markStepRunning(string $stepId): void
    {
        $this->updateStepProgress($stepId, ['status' => MigrationStatus::Running->value]);
    }

    public function markStepCompleted(string $stepId): void
    {
        $this->updateStepProgress($stepId, ['status' => MigrationStatus::Completed->value]);
    }

    public function markStepFailed(string $stepId, string $error): void
    {
        $this->updateStepProgress($stepId, [
            'status' => MigrationStatus::Failed->value,
            'errors' => [['reason' => $error]],
        ]);
    }

    public function getProgress(): array
    {
        $state = $this->getState();
        if ($state === null) {
            return [];
        }

        $totalItems = 0;
        $processedItems = 0;

        foreach ($state['steps'] as $step) {
            $totalItems += $step['total_items'];
            $processedItems += $step['processed_items'];
        }

        return [
            'status'          => $state['status'],
            'migrator_id'     => $state['migrator_id'],
            'total_items'     => $totalItems,
            'processed_items' => $processedItems,
            'percent'         => $totalItems > 0 ? round(($processedItems / $totalItems) * 100) : 0,
            'steps'           => $state['steps'],
            'started_at'      => $state['started_at'],
            'completed_at'    => $state['completed_at'],
            'created_by'      => $state['created_by'],
        ];
    }

    public function getNextPendingStep(): ?string
    {
        $state = $this->getState();
        if ($state === null) {
            return null;
        }

        foreach ($state['steps'] as $stepId => $step) {
            if ($step['status'] === MigrationStatus::Pending->value) {
                return $stepId;
            }
        }

        return null;
    }

    public function areAllStepsComplete(): bool
    {
        $state = $this->getState();
        if ($state === null) {
            return false;
        }

        foreach ($state['steps'] as $step) {
            if ($step['status'] !== MigrationStatus::Completed->value) {
                return false;
            }
        }

        return true;
    }

    public function clearState(): void
    {
        delete_option(self::OPTION_KEY);
    }
}
