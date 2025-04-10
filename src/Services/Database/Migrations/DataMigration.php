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
            $userIds   = get_users(['fields' => 'ID']);
            $users     = [];

            foreach ($userIds as $userId) {
                $users[] = [
                    'user_id'            => $userId,
                    'billing_first_name' => get_user_meta($userId, 'billing_first_name', true),
                    'billing_last_name'  => get_user_meta($userId, 'billing_last_name', true),
                    'billing_country'    => get_user_meta($userId, 'billing_country', true),
                    'billing_email'      => get_user_meta($userId, 'billing_email', true),
                    'billing_phone'      => get_user_meta($userId, 'billing_phone', true)
                ];
            }

            if ($users) {

                $batches = array_chunk($users, $batchSize);

                foreach ($batches as $batch) {
                    $tasks[] = [
                        'data'    => $batch,
                        'setData' => 'setUserMetaBatch',
                        'class'   => 'process_user_meta_numbers',
                    ];
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
            ->setName('sms_numbers')
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