<?php

namespace WP_SMS\Services\Database\Migrations\Queue;

use WP_SMS\Settings\Option;
use WP_SMS\Services\Admin\NoticeHandler\Notice;

/**
 * Queue Migration Manager
 *
 * Manages the background execution of database migrations using WordPress queue system.
 * This class provides a comprehensive queue migration system with automatic execution,
 * user notifications, and proper security handling.
 */
class QueueManager
{
    /**
     * The action slug used for manually triggering the queue migration.
     *
     * This constant defines the WordPress admin action that users can trigger
     * to manually start the queue-based migration process.
     *
     * @var string
     */
    private const MIGRATION_ACTION = 'run_queue_background_process';

    /**
     * The nonce name used to secure the manual migration action.
     *
     * This constant defines the nonce field name used to secure the manual
     * migration action against CSRF attacks.
     *
     * @var string
     */
    private const MIGRATION_NONCE = 'run_queue_background_process_nonce';

    /**
     * Class constructor.
     *
     * Initializes the migration handling system and attaches necessary WordPress hooks.
     * Sets up notices for both completed migrations and pending migration requirements.
     * Only attaches migration-related hooks if migrations are needed or completed.
     */
    public function __construct()
    {

    }

    /**
     * Executes all pending queue-based migration steps.
     *
     * This method retrieves all pending migration steps from the QueueFactory
     * and executes them sequentially. Each step is processed individually
     * to ensure proper completion and error handling.
     *
     * @return void
     */
    private function executeAllMigrations()
    {
        $pendingSteps = QueueFactory::getPendingMigrationSteps();

        foreach ($pendingSteps as $step) {
            QueueFactory::executeMigrationStep($step);
        }
    }
}

