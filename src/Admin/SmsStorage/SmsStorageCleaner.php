<?php

namespace WP_SMS\Admin\SmsStorage;

use WP_SMS\Option;
use WP_SMS\Utils\Query;
use WP_SMS\Utils\TimeZone;
use WP_SMS\Utils\PluginHelper;

class SmsStorageCleaner
{
    /**
     * Cleans old records in the outbox based on retention days.
     *
     * This method checks if storing outbox messages is enabled and if retention days are set.
     * If retention days is greater than 0, it will delete messages older than the specified retention period.
     */
    public function cleanOutbox()
    {
        $store         = Option::getOption('store_outbox_messages');
        $retentionDays = (int)Option::getOption('outbox_retention_days');

        if (!$store || $retentionDays === 0) {
            return;
        }

        $this->cleanOldRecords('sms_send', 'date', $retentionDays);
    }

    /**
     * Cleans old records in the inbox based on retention days.
     *
     * This method checks if storing inbox messages is enabled and if retention days are set.
     * If retention days is greater than 0, it will delete messages older than the specified retention period.
     */
    public function cleanInbox()
    {
        $twoWayInstalled = PluginHelper::isPluginInstalled('wp-sms-two-way/wp-sms-two-way.php');
        if (!$twoWayInstalled) {
            return;
        }

        $store         = Option::getOption('store_inbox_messages');
        $retentionDays = (int)Option::getOption('inbox_retention_days');

        if (!$store || $retentionDays === 0) {
            return;
        }

        $this->cleanOldRecords('sms_two_way_incoming_messages', 'received_at', $retentionDays, 'int');
    }

    /**
     * Cleans all SMS storage (outbox and inbox).
     *
     * This method calls both cleanOutbox() and cleanInbox() to clean the storage for both outbox and inbox messages.
     */
    public function cleanAll()
    {
        $this->cleanOutbox();
        $this->cleanInbox();
    }

    /**
     * Deletes records from a specific table based on the retention period.
     *
     * @param string $table The name of the table to delete records from (e.g., 'sms_send').
     * @param string $dateField The name of the date field (e.g., 'date').
     * @param int $retentionDays The number of days after which records will be deleted.
     * @param string $fieldType The type of the date field ('int' for timestamp, 'datetime' for MySQL DATETIME).
     */
    private function cleanOldRecords($table, $dateField, $retentionDays, $fieldType = 'datetime')
    {
        $cutoff = null;

        if ($fieldType === 'int') {
            $cutoff = strtotime(TimeZone::getCurrentDate('Y-m-d 00:00:00', "-{$retentionDays}"));
        } elseif ($fieldType === 'datetime') {
            $cutoff = TimeZone::getCurrentDate('Y-m-d 00:00:00', "-{$retentionDays}");
        }

        if ($cutoff !== null) {
            Query::delete($table)
                ->where($dateField, '<', $cutoff)
                ->execute();
        }
    }
}