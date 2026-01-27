<?php

namespace WP_SMS\Services\Database\Migrations\Queue;

use WP_SMS\Option;

/**
 * Factory class responsible for managing and coordinating queue-based database migrations.
 *
 * This class serves as the central orchestrator for database migration operations using a queue-based approach.
 * It provides functionality to discover migration steps from migration classes, manage their execution through
 * a simple queue system, and track completion status.
 *
 * Key responsibilities:
 * - Discovering and collecting migration steps from migration classes
 * - Managing execution state and tracking completed steps
 * - Providing methods to check migration requirements and completion status
 * - Coordinating the execution of individual migration steps
 * - Handling fresh installations where no migrations are needed
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class QueueFactory
{
    /**
     * Creates and returns a queue migration instance.
     *
     * @return QueueMigration The migration class instance containing all migration steps.
     */
    public static function getQueueMigration()
    {
        return new QueueMigration();
    }

    /**
     * Determines if a queue-based database migration is required.
     *
     * @return bool True if migration is required and steps are pending, false otherwise.
     */
    public static function needsMigration()
    {
        if (self::isMigrationCompleted()) {
            return false;
        }

        if (self::isFreshInstall()) {
            $allStepIdentifiers = array_keys(self::getQueueMigration()->getMigrationSteps());
            self::saveCompletedSteps($allStepIdentifiers);

            Option::updateInGroup('queue_completed', true, Option::DB_GROUP);
            return false;
        }

        $migrationSteps = self::collectQueueMigrationSteps();

        if (empty($migrationSteps)) {
            Option::updateInGroup('queue_completed', true, Option::DB_GROUP);
            return false;
        }

        return true;
    }

    /**
     * Checks if the overall queue migration process has been completed.
     *
     * @return bool True if the migration process is completed, false otherwise.
     */
    public static function isMigrationCompleted()
    {
        return Option::getFromGroup('queue_completed', Option::DB_GROUP, false) === true;
    }

    /**
     * Collects all pending migration steps from the queue migration class.
     *
     * @return array Array of pending migration steps with their metadata.
     */
    public static function collectQueueMigrationSteps()
    {
        $allSteps = [];

        $completedSteps = self::getCompletedSteps();
        $migrationInstance = self::getQueueMigration();
        $migrationSteps = $migrationInstance->getMigrationSteps();

        foreach ($migrationSteps as $stepKey => $methodName) {
            if (self::isStepCompleted($stepKey, $completedSteps)) {
                continue;
            }

            $allSteps[] = [
                'identifier' => $stepKey,
                'method'     => $methodName,
                'instance'   => $migrationInstance
            ];
        }

        return $allSteps;
    }

    /**
     * Retrieves pending migration steps that haven't been completed yet.
     *
     * @return array Array of pending migration steps with their metadata.
     */
    public static function getPendingMigrationSteps()
    {
        return self::collectQueueMigrationSteps();
    }

    /**
     * Determines whether the database migration process has been completed.
     *
     * @return bool True if the database is considered fully migrated, false otherwise.
     */
    public static function isDatabaseMigrated()
    {
        $migrated = Option::getFromGroup('migrated', Option::DB_GROUP, false);
        $check    = Option::getFromGroup('schema_check', Option::DB_GROUP, true);

        return $migrated && !$check;
    }

    /**
     * Marks a specific migration step as completed.
     *
     * @param string $stepIdentifier The migration step identifier to mark as completed.
     * @return void
     */
    public static function markStepCompleted($stepIdentifier)
    {
        if (empty($stepIdentifier)) {
            return;
        }

        $completedSteps = self::getCompletedSteps();

        if (!in_array($stepIdentifier, $completedSteps, true)) {
            $completedSteps[] = $stepIdentifier;
            self::saveCompletedSteps($completedSteps);
        }
    }

    /**
     * Executes a specific migration step and handles the result.
     *
     * @param array $step Migration step array containing method name and instance.
     * @return bool True if the step was executed successfully, false on failure.
     */
    public static function executeMigrationStep($step)
    {
        try {
            $instance = $step['instance'];
            $method = $step['method'];

            if (!method_exists($instance, $method)) {
                error_log(sprintf(
                    '[WP SMS] Migration method %s does not exist in class %s',
                    $method,
                    get_class($instance)
                ));
                return false;
            }

            $instance->$method();

            self::markStepCompleted($step['identifier']);

            return true;
        } catch (\Exception $e) {
            error_log(sprintf(
                '[WP SMS] Queue migration step failed [%s]: %s',
                $step['identifier'],
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Retrieves the list of completed migration steps.
     *
     * @return array Array of completed step identifiers.
     */
    private static function getCompletedSteps()
    {
        return (array) Option::getFromGroup('queue_completed_steps', Option::DB_GROUP, []);
    }

    /**
     * Persists the list of completed migration steps to storage.
     *
     * @param array $completedSteps Array of completed step identifiers.
     * @return void
     */
    private static function saveCompletedSteps($completedSteps)
    {
        Option::updateInGroup('queue_completed_steps', $completedSteps, Option::DB_GROUP);
    }

    /**
     * Checks if a specific migration step has been completed.
     *
     * @param string $stepIdentifier The step identifier to check for completion.
     * @param array $completedSteps Array of completed step identifiers.
     * @return bool True if the step is completed, false otherwise.
     */
    private static function isStepCompleted($stepIdentifier, $completedSteps)
    {
        return in_array($stepIdentifier, $completedSteps, true);
    }

    /**
     * Check if this is a fresh installation.
     *
     * @return bool True if fresh install, false otherwise.
     */
    private static function isFreshInstall()
    {
        return (bool) Option::getFromGroup('is_fresh', Option::DB_GROUP, false);
    }
}
