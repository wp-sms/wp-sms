<?php

namespace WP_SMS\BackgroundProcess\Async;

use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Library\BackgroundProcessing\WP_Background_Process;

class SchemaMigrationProcess extends WP_Background_Process
{
    /**
     * @var string
     */
    protected $prefix = 'wp_sms';

    /**
     * @var string
     */
    protected $action = 'schema_migration_process';

    /**
     * Process a single schema migration task.
     *
     * @param array $data
     * @return bool|string
     */
    protected function task($data)
    {
        $class   = isset($data['class']) ? $data['class'] : null;
        $method  = isset($data['method']) ? $data['method'] : null;
        $version = isset($data['version']) ? $data['version'] : null;

        if (!$class || !$method || !$version) {
            return false;
        }

        if (!class_exists($class)) {
            return false;
        }

        $instance = new $class();

        if (!method_exists($instance, 'setMethod') || !method_exists($instance, $method)) {
            return false;
        }

        $instance->setMethod($method, $version);
        $instance->$method();
        $instance->setVersion();

        return false;
    }

    /**
     * Complete processing.
     */
    protected function complete()
    {
        parent::complete();

        Option::deleteOptionGroup('schema_migration_process_started', 'jobs');
        Option::saveOptionGroup('migrated', true, 'db');
        Option::saveOptionGroup('auto_migration_tasks', [], 'db');
    }
}
