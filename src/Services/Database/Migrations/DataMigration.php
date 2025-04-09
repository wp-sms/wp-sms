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
            'migrateMobileNumbersFromUserMeta',
            'verifyMobileNumberMigration'
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
                ->setName('sms_subscribes')
                ->setArgs([
                    'columns'  => ['ID', 'mobile', 'name', 'status', 'group_ID'],
                    'where'    => ['mobile IS NOT NULL AND mobile != ""'],
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
            return [];
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
            $metaKeys  = ['mobile', 'phone', 'billing_phone'];

            foreach ($metaKeys as $key) {
                $userNumbers = DatabaseFactory::table('select')
                    ->setName('usermeta')
                    ->setArgs([
                        'columns' => ['user_id', 'meta_value as number'],
                        'where'   => [
                            'meta_key' => $key,
                            'meta_value IS NOT NULL AND meta_value != ""'
                        ]
                    ])
                    ->execute()
                    ->getResult();

                if ($userNumbers) {
                    $total = count($userNumbers);
                    BackgroundProcessMonitor::addTotalRecords('data_migration_process', $total);

                    $batches = array_chunk($userNumbers, $batchSize);

                    foreach ($batches as $batch) {
                        $tasks[] = [
                            'data'     => $batch,
                            'setData'  => 'setUserMetaBatch',
                            'class'    => 'process_user_meta_numbers',
                            'meta_key' => $key
                        ];
                    }
                }
            }

            return $tasks;
        } catch (Exception $e) {
            $this->setErrorStatus($e->getMessage());
            return [];
        }
    }

    /**
     * Verify the mobile number migration
     */
    public function verifyMobileNumberMigration()
    {
        return [
            [
                'task'  => 'validate_mobile_number_migration',
                'class' => 'migration_verification'
            ]
        ];
    }

    /**
     * Process a batch of subscriber numbers
     */
    public function processSubscriberNumbers($batch)
    {
        foreach ($batch as $subscriber) {
            $this->insertMobileNumber([
                'number' => $subscriber['mobile'],
                'name'   => $subscriber['name'] ?? null,
                'status' => $subscriber['status'] ? 'active' : 'pending',
                'source' => 'legacy_subscriber',
                'meta'   => json_encode([
                    'legacy_id' => $subscriber['ID'],
                    'group_id'  => $subscriber['group_ID']
                ])
            ]);
        }
        return true;
    }

    /**
     * Process a batch of user meta numbers
     */
    public function processUserMetaNumbers($batch, $metaKey)
    {
        foreach ($batch as $user) {
            $this->insertMobileNumber([
                'number'  => $user['number'],
                'user_id' => $user['user_id'],
                'status'  => 'active',
                'source'  => 'usermeta_' . $metaKey
            ]);
        }
        return true;
    }

    /**
     * Insert a mobile number record
     */
    protected function insertMobileNumber($data)
    {
        $defaults = [
            'country_code' => $this->detectCountryCode($data['number']),
            'verified'     => false,
            'created_at'   => current_time('mysql')
        ];

        $record = array_merge($defaults, $data);

        return DatabaseFactory::table('insert')
            ->setName('numbers')
            ->setArgs([
                'data'   => $record,
                'format' => [
                    '%s', // number
                    '%s', // country_code
                    '%d', // user_id
                    '%s', // name
                    '%s', // status
                    '%s', // source
                    '%s', // meta
                    '%d', // verified
                    '%s'  // created_at
                ]
            ])
            ->execute()
            ->getResult();
    }

    /**
     * Simple country code detection
     */
    protected function detectCountryCode($number)
    {
        // Implement proper country code detection
        return '';
    }
}