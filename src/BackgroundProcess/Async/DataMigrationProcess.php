<?php

namespace WP_SMS\BackgroundProcess\Async;

use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Services\Database\Migrations\DataMigration;
use WP_SMS\Library\BackgroundProcessing\WP_Background_Process;

class DataMigrationProcess extends WP_Background_Process
{
    /**
     * @var string
     */
    protected $prefix = 'wp_sms';

    /**
     * @var string
     */
    protected $action = 'data_migration_process';

    /**
     * Is the background process currently running?
     *
     * @return bool
     */
    public function is_processing()
    {
        if (get_site_transient($this->identifier . '_process_lock')) {
            Option::saveOptionGroup('migration_status_detail', [
                'status' => 'progress'
            ], 'db');
            return true;
        }

        return false;
    }

    /**
     * Process a single data migration task.
     *
     * @param array $data
     * @return bool|string
     */
    protected function task($data)
    {
        $class   = isset($data['class']) ? $data['class'] : null;
        $method  = isset($data['method']) ? $data['method'] : null;
        $version = isset($data['version']) ? $data['version'] : null;
        $task    = isset($data['task']) ? $data['task'] : null;
        $type    = isset($data['type']) ? $data['type'] : null;

        if (!$class || !$method || !$version) {
            return false;
        }

        if (!class_exists($class)) {
            return;
        }

        $instance = new $class();

        if (!method_exists($instance, 'setMethod')) {
            return false;
        }

        if ('schema' === $type) {
            if (!method_exists($instance, $method)) {
                return false;
            }

            $instance->setMethod($method, $version);
            $instance->$method($version);

            $dataSteps = (new DataMigration)->getMigrationSteps();

            if (!isset($dataSteps[$version])) {
                $instance->setVersion();
            }

            return false;
        }

        if (!$task) {
            return false;
        }

        if (!method_exists($task, 'execute')) {
            return false;
        }

        $instance->setMethod($method, $version);
        $task->execute();
        $instance->setVersion();

        return false;
    }

    /**
     * Complete processing.
     */
    protected function complete()
    {
        parent::complete();

        $details = Option::getOptionGroup('db', 'migration_status_detail', null);

        $operationStatus = [
            'status' => 'done',
        ];

        if (!empty($details['status']) && 'failed' === $details['status']) {
            $operationStatus = [
                'status'  => 'failed',
                'message' => $details['message'],
            ];
        }

        Option::deleteOptionGroup('data_migration_process_started', 'jobs');
        Option::saveOptionGroup('migrated', true, 'db');
        Option::saveOptionGroup('migration_status_detail', $operationStatus, 'db');
    }
}
