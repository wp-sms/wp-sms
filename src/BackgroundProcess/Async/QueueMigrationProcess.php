<?php

namespace WP_SMS\BackgroundProcess\Async;

use WP_SMS\Library\BackgroundProcessing\WP_Background_Process;
use WP_SMS\Services\Database\Migrations\Queue\QueueFactory;
use WP_SMS\Settings\Option;

class QueueMigrationProcess extends WP_Background_Process
{
    /**
     * @var string
     */
    protected $prefix = 'wp_sms';

    /**
     * @var string
     */
    protected $action = 'queue_migration_process';

    /**
     * Process a single queue migration task.
     *
     * @param array $data Migration step data containing 'identifier', 'method', and 'instance'
     * @return bool False to indicate the task is complete and should be removed from queue
     */
    protected function task($data)
    {
        // Execute the migration step
        QueueFactory::executeMigrationStep($data);

        // Return false to remove this item from the queue
        return false;
    }

    /**
     * Complete processing.
     *
     * This method is called when all migration tasks in the queue have been processed.
     * It marks the overall migration as completed.
     */
    protected function complete()
    {
        parent::complete();

        require_once WP_SMS_DIR . 'src/Settings/Option.php';

        // Mark the queue migration as completed
        Option::saveOptionGroup('completed', true, 'queue_background_process');
    }
}
