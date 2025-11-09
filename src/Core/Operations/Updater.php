<?php

namespace WP_SMS\Core\Operations;

use WP_SMS\Core\AbstractCore;
use WP_SMS\Services\Database\Managers\TableHandler;
use WP_SMS\Services\Database\Migrations\Schema\SchemaManager;
use WP_SMS\Services\Database\Managers\SchemaMaintainer;
use WP_SMS\Utils\OptionUtil;

/**
 * Handles update-time migrations and cleanup.
 *
 * Runs on init when a version change is detected; ensures tables exist, executes
 * legacy migrations, updates the stored version, and bootstraps the schema manager.
 * Also adjusts schedules, options, and cached data as needed.
 *
 * @package WP_SMS\Core\Operations
 */
class Updater extends AbstractCore
{
    /**
     * Updater constructor.
     *
     * @return void
     */
    public function __construct($networkWide = false)
    {
        parent::__construct($networkWide);
        add_action('init', [$this, 'execute']);
    }

    /**
     * Execute the core function.
     *
     * @return void
     */
    public function execute()
    {
        if (is_multisite()) {
            $this->initializeDefaultOptions();
        }

        if (!$this->isUpdated()) {
            return;
        }

        $this->checkIsFresh();
        TableHandler::createAllTables();
        $this->legacyMigrations();
        $this->updateOptions();
        $this->updateVersion();

        SchemaManager::init();
        SchemaMaintainer::repair(true);
    }

    /**
     * Execute the legacy migrations.
     *
     * @return void
     */
    private function legacyMigrations()
    {
        $outboxTable           = $this->wpdb->prefix . 'sms_send';
        $subscribersTable      = $this->wpdb->prefix . 'sms_subscribes';
        $subscribersGroupTable = $this->wpdb->prefix . 'sms_subscribes_group';

        /**
         * Add `status` and `response` fields to sms_send table if missing.
         */
        $column = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
            DB_NAME,
            $outboxTable,
            'response'
        ));

        if (empty($column)) {
            $this->wpdb->query("ALTER TABLE {$outboxTable} ADD status varchar(10) NOT NULL AFTER recipient, ADD response TEXT NOT NULL AFTER recipient");
        }

        /**
         * Ensure `name` field can store larger values on sms_subscribes table.
         */
        $this->wpdb->query("ALTER TABLE {$subscribersTable} MODIFY name VARCHAR(250)");

        /**
         * Normalize charset/collation for sms_send table.
         */
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
            DB_NAME,
            $outboxTable,
            'message'
        ));

        if ($result->COLLATION_NAME != $this->wpdb->collate) {
            $this->wpdb->query("ALTER TABLE {$outboxTable} CONVERT TO CHARACTER SET {$this->wpdb->charset} COLLATE {$this->wpdb->collate}");
        }

        /**
         * Normalize charset/collation for sms_subscribes names.
         */
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
            DB_NAME,
            $subscribersTable,
            'name'
        ));

        if ($result->COLLATION_NAME != $this->wpdb->collate) {
            $this->wpdb->query("ALTER TABLE {$subscribersTable} CONVERT TO CHARACTER SET {$this->wpdb->charset} COLLATE {$this->wpdb->collate}");
        }

        /**
         * Normalize charset/collation for sms_subscribes_group names.
         */
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
            DB_NAME,
            $subscribersGroupTable,
            'name'
        ));

        if ($result->COLLATION_NAME != $this->wpdb->collate) {
            $this->wpdb->query("ALTER TABLE {$subscribersGroupTable} CONVERT TO CHARACTER SET {$this->wpdb->charset} COLLATE {$this->wpdb->collate}");
        }

        /**
         * Add custom_fields support to sms_subscribes data if missing.
         */
        if (!$this->wpdb->get_var("SHOW COLUMNS FROM `{$subscribersTable}` like 'custom_fields'")) {
            $this->wpdb->query("ALTER TABLE `{$subscribersTable}` ADD `custom_fields` TEXT NULL AFTER `activate_key`");
        }

        /**
         * Add media attachment field to sms_send if missing.
         */
        if (!$this->wpdb->get_var("SHOW COLUMNS FROM `{$outboxTable}` like 'media'")) {
            $this->wpdb->query("ALTER TABLE `{$outboxTable}` ADD `media` TEXT NULL AFTER `recipient`");
        }

        /**
         * Remove deprecated option.
         */
        delete_option('wp_last_credit');
        delete_option('wp_notification_new_wp_version');
        delete_option('wp_sms_db_version');
    }

    /**
     * Initialize default plugin options when upgrading.
     *
     * @return void
     */
    private function updateOptions()
    {
        $initializedSettings = get_option('wp_sms_initialized_settings', []);

        if (!in_array('plugin_notifications', $initializedSettings)) {
            if (OptionUtil::get('plugin_notifications') === false) {
                OptionUtil::update('plugin_notifications', 1);
            }

            $initializedSettings[] = 'plugin_notifications';
        }
    }
}