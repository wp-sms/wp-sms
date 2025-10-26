<?php

namespace WP_SMS\BackgroundProcess\Async;

use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Services\Database\DatabaseFactory;
use WP_SMS\Library\BackgroundProcessing\WP_Background_Process;

class TableOperationProcess extends WP_Background_Process
{
    /**
     * @var string
     */
    protected $prefix = 'wp_sms';

    /**
     * @var string
     */
    protected $action = 'table_operations_process';

    /**
     * Process each table creation task.
     * @param array $data
     * @return bool|string
     */
    protected function task($data)
    {
        $operation = isset($data['operation']) ? $data['operation'] : null;
        $tableName = isset($data['table_name']) ? $data['table_name'] : null;
        $args      = isset($data['args']) ? $data['args'] : [];

        if (!$operation || !$tableName) {
            return false;
        }

        DatabaseFactory::table($operation)
            ->setName($tableName)
            ->setArgs($args)
            ->execute();

        return false;
    }

    public function is_initiated()
    {
        return Option::getOptionGroup('jobs', 'table_operations_process_initiated', false);
    }

    /**
     * Complete processing.
     */
    protected function complete()
    {
        parent::complete();
        Option::saveOptionGroup('check', false, 'db');
    }
}
