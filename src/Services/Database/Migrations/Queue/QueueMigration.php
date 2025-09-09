<?php

namespace WP_SMS\Services\Database\Migrations\Queue;

use WP_SMS\Abstracts\BaseMigrationOperation;

/**
 * Queue migration class for handling database migration steps.
 *
 * This class extends BaseMigrationOperation to provide specific migration
 * functionality for queued operations. It handles various setting updates
 * and data transformations during the migration process.
 */
class QueueMigration extends BaseMigrationOperation
{
    /**
     * Array of migration steps with their corresponding method names.
     *
     * Each key represents a migration step identifier, and the value
     * is the corresponding method name to execute for that step.
     * The methods are called sequentially during the migration process.
     *
     * @var array<string, string> Array mapping step names to method names
     */
    protected $migrationSteps = [
        'sampleQueueMigration' => 'sampleQueueMigration'
    ];

    public function sampleQueueMigration()
    {
        \WP_SMS::log('Queue: sampleQueueMigration triggered.');
    }
}