<?php

namespace WP_SMS\Services\Database\Migrations;

use Exception;
use WP_SMS\BackgroundProcess\Async\BackgroundProcessMonitor;
use WP_SMS\Services\Database\DatabaseFactory;

/**
 * Manages migrations related to database data.
 */
class DataMigration extends AbstractMigrationOperation
{
    /**
     * The name of the migration operation.
     *
     * @var string
     */
    protected $name = 'data';

    protected $migrationSteps = [
        '14.12.7' => [
            'migrateMobileNumbersFromSubscribers',
            'migrateMobileNumbersFromUserMeta'
        ],
    ];

    /**
     * Migrate mobile numbers from subscribers table
     */
    public function migrateMobileNumbersFromSubscribers()
    {
        try {
            $this->ensureConnection();
            $tasks     = [];
            $batchSize = 100;

            // Get all distinct mobile numbers from subscribers table
            $subscribers = DatabaseFactory::table('select')
                ->setName('subscribes')
                ->setArgs([
                    'columns'  => ['ID', 'mobile', 'name', 'status', 'group_ID'],
                    'order_by' => 'ID ASC'
                ])
                ->execute()
                ->getResult();

            if (!$subscribers) {
                return $tasks;
            }

            $total = count($subscribers);
            BackgroundProcessMonitor::setTotalRecords('data_migration_process', $total);

            // Create batches
            $batches = array_chunk($subscribers, $batchSize);

            foreach ($batches as $batch) {
                $tasks[] = [
                    'data'    => $batch,
                    'setData' => 'setSubscriberBatch',
                    'class'   => 'process_subscriber_numbers'
                ];
            }

            return $tasks;
        } catch (Exception $e) {
            $this->setErrorStatus($e->getMessage());
        }
    }

    /**
     * Migrate mobile numbers from user meta
     */
    public function migrateMobileNumbersFromUserMeta()
    {
        try {
            $this->ensureConnection();
            $tasks     = [];
            $batchSize = 100;
            $offset    = 0;

            do {
                $rows = \WP_SMS\Utils\Query::select([
                    'um1.user_id',
                    'um2.meta_value AS billing_first_name',
                    'um3.meta_value AS billing_last_name',
                    'um4.meta_value AS billing_country',
                    'um5.meta_value AS billing_email',
                    'um1.meta_value AS billing_phone'
                ])
                    ->from('usermeta AS um1')
                    ->join('usermeta AS um2', 'um1.user_id', '=', 'um2.user_id AND um2.meta_key = "billing_first_name"')
                    ->join('usermeta AS um3', 'um1.user_id', '=', 'um3.user_id AND um3.meta_key = "billing_last_name"')
                    ->join('usermeta AS um4', 'um1.user_id', '=', 'um4.user_id AND um4.meta_key = "billing_country"')
                    ->join('usermeta AS um5', 'um1.user_id', '=', 'um5.user_id AND um5.meta_key = "billing_email"')
                    ->where('um1.meta_key', '=', 'billing_phone')
                    ->where('um1.meta_value', '!=', '')
                    ->orderBy('um1.user_id', 'ASC')
                    ->limit($batchSize, $offset)
                    ->getAll();

                $batch = [];
                foreach ($rows as $row) {
                    $batch[] = [
                        'user_id'            => $row->user_id,
                        'billing_first_name' => $row->billing_first_name,
                        'billing_last_name'  => $row->billing_last_name,
                        'billing_country'    => $row->billing_country,
                        'billing_email'      => $row->billing_email,
                        'billing_phone'      => $row->billing_phone,
                    ];
                }

                if ($batch) {
                    $tasks[] = [
                        'data'    => $batch,
                        'setData' => 'setUserMetaBatch',
                        'class'   => 'process_user_meta_numbers',
                    ];
                }

                $offset += $batchSize;
            } while (count($rows) === $batchSize);

            return $tasks;
        } catch (Exception $e) {
            $this->setErrorStatus($e->getMessage());
        }
    }
}