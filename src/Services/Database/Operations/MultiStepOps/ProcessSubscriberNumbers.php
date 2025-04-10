<?php

namespace WP_SMS\Services\Database\Operations\MultiStepOps;

use Exception;
use RuntimeException;
use WP_SMS\BackgroundProcess\Async\BackgroundProcessMonitor;
use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Services\Database\DatabaseFactory;
use WP_SMS\Services\Database\Operations\AbstractTableOperation;

/**
 * Handles inserting first and last page data for visitors.
 *
 * This operation processes the visitor data immediately.
 */
class ProcessSubscriberNumbers extends AbstractTableOperation
{
    /**
     * Sets up the subscriber batch for processing.
     *
     * @param array $subscribers The list of subscribers.
     * @return $this
     */
    public function setSubscriberBatch(array $subscribers)
    {
        $this->args['subscribers'] = $subscribers;
        return $this;
    }

    /**
     * Executes the process for each visitor batch.
     *
     * @return void
     * @throws RuntimeException
     */
    public function execute()
    {
        try {
            $this->ensureConnection();

            $this->setRunTimeError();

            if (empty($this->args['subscribers'])) {
                throw new RuntimeException("Batch insert process requires subscribers.");
            }

            $subscribers = $this->args['subscribers'];

            if (!$subscribers) {
                return; // No data found, nothing to process
            }

            BackgroundProcessMonitor::setCompletedRecords('data_migration_process', count($subscribers));

            foreach ($subscribers as $subscriber) {
                try {
                    $name   = $subscriber['name'];
                    $mobile = $subscriber['mobile'];
                    $status = $subscriber['status'];

                    DatabaseFactory::table('insert')
                        ->setName('sms_numbers')
                        ->setArgs([
                            'conditions' => [
                                'number' => $mobile,
                            ],
                            'mapping'    => [
                                'display_name' => $name,
                                'number'       => $mobile,
                                'status'       => $status ? 'active' : 'deactivated',
                            ],
                        ])
                        ->execute();

                } catch (Exception $e) {
                    Option::saveOptionGroup('migration_status_detail', [
                        'status'  => 'failed',
                        'message' => "Batch aborted due to visitor processing failure: " . $e->getMessage()
                    ], 'db');

                    //@todo add logs here
                }
            }
        } catch (Exception $e) {
            Option::saveOptionGroup('migration_status_detail', [
                'status'  => 'failed',
                'message' => "Visitor first and last log Insert failed: " . $e->getMessage()
            ], 'db');

            //@todo add logs here
        }
    }
}
