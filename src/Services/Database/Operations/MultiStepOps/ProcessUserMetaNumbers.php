<?php

namespace WP_SMS\Services\Database\Operations\MultiStepOps;

use Exception;
use RuntimeException;
use WP_SMS\BackgroundProcess\Async\BackgroundProcessMonitor;
use WP_SMS\Components\Countries;
use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Services\Database\DatabaseFactory;
use WP_SMS\Services\Database\Operations\AbstractTableOperation;

/**
 * Handles inserting first and last page data for visitors.
 *
 * This operation processes the visitor data immediately.
 */
class ProcessUserMetaNumbers extends AbstractTableOperation
{
    /**
     * Sets up the visitor ID batch for processing.
     *
     * @param array $visitorIds The list of visitor IDs.
     * @return $this
     */
    public function setUserMetaBatch(array $usersMetaData)
    {
        $this->args['users_meta_data'] = $usersMetaData;
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

            if (empty($this->args['users_meta_data'])) {
                throw new RuntimeException("Batch insert process requires users meta data.");
            }

            $usersData = $this->args['users_meta_data'];


            if (!$usersData) {
                return; // No data found, nothing to process
            }

            BackgroundProcessMonitor::setCompletedRecords('data_migration_process', count($usersData));

            foreach ($usersData as $user) {
                try {
                    $userId           = $user['user_id'];
                    $billingFirstName = $user['billing_first_name'];
                    $billingLastName  = $user['billing_last_name'];
                    $billingCountry   = $user['billing_country'];
                    $billingEmail     = $user['billing_email'];
                    $billingPhone     = $user['billing_phone'];
                    $countries        = Countries::getInstance();
                    $country_code     = $countries->getDialCodeByCountryCode($billingCountry);

                    DatabaseFactory::table('insert')
                        ->setName('numbers')
                        ->setArgs([
                            'conditions' => [
                                'number' => $billingPhone,
                            ],
                            'mapping'    => [
                                'number'       => $billingPhone,
                                'first_name'   => $billingFirstName,
                                'last_name'    => $billingLastName,
                                'user_id'      => $userId,
                                'country_code' => $country_code
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
