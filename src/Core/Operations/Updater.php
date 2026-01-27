<?php

namespace WP_SMS\Core\Operations;

use WP_SMS\Core\AbstractCore;
use WP_SMS\Option;
use WP_SMS\Services\Database\Managers\TableHandler;
use WP_SMS\Services\Database\Managers\SchemaMaintainer;
use WP_SMS\Services\Database\Migrations\Schema\SchemaManager;

/**
 * Handles update-time migrations and cleanup.
 *
 * Runs on init when a version change is detected; ensures tables exist, executes
 * legacy migrations, updates the stored version, and bootstraps the schema manager.
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
            $this->ensureMediaColumn();
            return;
        }

        $this->checkIsFresh();
        TableHandler::createAllTables();
        $this->legacyMigrations();
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
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $outboxTable           = $this->wpdb->prefix . 'sms_send';
        $subscribersTable      = $this->wpdb->prefix . 'sms_subscribes';
        $subscribersGroupTable = $this->wpdb->prefix . 'sms_subscribes_group';

        // Add response and status for outbox
        $column = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $outboxTable,
            'response'
        ));

        if (empty($column)) {
            $this->wpdb->query("ALTER TABLE {$outboxTable} ADD status varchar(10) NOT NULL AFTER recipient, ADD response TEXT NOT NULL AFTER recipient");
        }

        // Fix columns length issue
        $this->wpdb->query("ALTER TABLE {$subscribersTable} MODIFY name VARCHAR(250)");

        // Delete old last credit option
        delete_option('wp_last_credit');

        // Change charset sms_send table to utf8mb4 if not
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $outboxTable,
            'message'
        ));

        if ($result && $result->COLLATION_NAME != $this->wpdb->collate) {
            $this->wpdb->query("ALTER TABLE {$outboxTable} CONVERT TO CHARACTER SET {$this->wpdb->charset} COLLATE {$this->wpdb->collate}");
        }

        // Change charset sms_subscribes table to utf8mb4 if not
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $subscribersTable,
            'name'
        ));

        if ($result && $result->COLLATION_NAME != $this->wpdb->collate) {
            $this->wpdb->query("ALTER TABLE {$subscribersTable} CONVERT TO CHARACTER SET {$this->wpdb->charset} COLLATE {$this->wpdb->collate}");
        }

        // Change charset sms_subscribes_group table to utf8mb4 if not
        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $subscribersGroupTable,
            'name'
        ));

        if ($result && $result->COLLATION_NAME != $this->wpdb->collate) {
            $this->wpdb->query("ALTER TABLE {$subscribersGroupTable} CONVERT TO CHARACTER SET {$this->wpdb->charset} COLLATE {$this->wpdb->collate}");
        }

        // Add custom_fields column in subscribes table
        if (!$this->wpdb->get_var("SHOW COLUMNS FROM `{$subscribersTable}` like 'custom_fields'")) {
            $this->wpdb->query("ALTER TABLE `{$subscribersTable}` ADD `custom_fields` TEXT NULL AFTER `activate_key`");
        }

        // Initialize default plugin options during upgrade
        if (version_compare($this->currentVersion, '7.1', '<')) {
            Option::updateOption('display_notifications', 1);
            Option::updateOption('store_outbox_messages', 1);
            Option::updateOption('outbox_retention_days', 90);
            Option::updateOption('store_inbox_messages', 1);
            Option::updateOption('inbox_retention_days', 90);
        }

        // Ensure media column exists
        $this->ensureMediaColumn();
    }

    /**
     * Ensures the media column exists in the send table.
     *
     * @return void
     */
    private function ensureMediaColumn()
    {
        $outboxTable = $this->wpdb->prefix . 'sms_send';

        if (!$this->wpdb->get_var("SHOW COLUMNS FROM `{$outboxTable}` like 'media'")) {
            $this->wpdb->query("ALTER TABLE `{$outboxTable}` ADD `media` TEXT NULL AFTER `recipient`");
        }
    }
}
