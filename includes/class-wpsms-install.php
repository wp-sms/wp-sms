<?php

namespace WP_SMS;

use WP_SMS\Services\Database\Managers\TableHandler;
use WP_SMS\Utils\OptionUtil as Option;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Install
{
    const TABLE_OTP          = 'sms_otp';
    const TABLE_OTP_ATTEMPTS = 'sms_otp_attempts';

    public function __construct()
    {
        add_action('delete_blog', [$this, 'onSiteDelete'], 10, 2);
    }

    /**
     * Checks whether the plugin is a fresh installation.
     *
     * @return void
     */
    private function checkIsFresh()
    {
        $version = get_option('wp_sms_db_version');

        if (empty($version)) {
            update_option('wp_sms_is_fresh', true);
            return;
        }

        update_option('wp_sms_is_fresh', false);
    }

    /**
     * Determines if the plugin is marked as freshly installed.
     *
     * @return bool.
     */
    public static function isFresh()
    {
        $isFresh = get_option('wp_sms_is_fresh', false);

        if ($isFresh) {
            return true;
        }

        return false;
    }

    /**
     * Creating plugin tables
     *
     * @param $network_wide
     */
    public function install($network_wide)
    {
        require_once WP_SMS_DIR . 'src/Utils/OptionUtil.php';
        require_once WP_SMS_DIR . 'src/Services/Database/DatabaseManager.php';
        require_once WP_SMS_DIR . 'src/Services/Database/Managers/TransactionHandler.php';
        require_once WP_SMS_DIR . 'src/Services/Database/AbstractDatabaseOperation.php';
        require_once WP_SMS_DIR . 'src/Services/Database/Operations/AbstractTableOperation.php';
        require_once WP_SMS_DIR . 'src/Services/Database/Operations/Create.php';
        require_once WP_SMS_DIR . 'src/Services/Database/Operations/Inspect.php';
        require_once WP_SMS_DIR . 'src/Services/Database/DatabaseFactory.php';
        require_once WP_SMS_DIR . 'src/Services/Database/Schema/Manager.php';
        require_once WP_SMS_DIR . 'src/Services/Database/Managers/TableHandler.php';

        global $wp_sms_db_version, $wpdb;
        // Delete notification new wp_version option
        delete_option('wp_notification_new_wp_version');

        if (is_multisite() && $network_wide) {
            $blog_ids = $wpdb->get_col("SELECT `blog_id` FROM $wpdb->blogs");
            foreach ($blog_ids as $blog_id) {

                switch_to_blog($blog_id);
                $this->checkIsFresh();
                TableHandler::createAllTables();
                restore_current_blog();
            }
        } else {
            $this->checkIsFresh();
            TableHandler::createAllTables();
        }

        $this->markBackgroundProcessAsInitiated();
        add_option('wp_sms_db_version', WP_SMS_VERSION);
    }

    private function markBackgroundProcessAsInitiated()
    {
        if (!self::isFresh()) {
            return;
        }

        Option::saveOptionGroup('schema_migration_process_started', true, 'jobs');
        Option::saveOptionGroup('update_source_channel_process_initiated', true, 'jobs');
        Option::saveOptionGroup('table_operations_process_initiated', true, 'jobs');
    }

    /**
     * Upgrade plugin requirements if needed
     */
    public function upgrade()
    {
        if (is_multisite()) {
            $site_ids = get_sites(['fields' => 'ids']);
            foreach ($site_ids as $site_id) {
                switch_to_blog($site_id);
                $this->runUpgradeForCurrentSite();
                restore_current_blog();
            }
        } else {
            $this->runUpgradeForCurrentSite();
        }
    }

    /**
     * Run upgrade routine for the current site context.
     *
     * This method checks the current database version and applies
     * schema updates, table modifications, and new column additions.
     * It is designed to be safely used in a multisite environment
     * with switch_to_blog().
     *
     * @return void
     */
    private function runUpgradeForCurrentSite()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        global $wpdb;
        $charset_collate       = $wpdb->get_charset_collate();
        $installer_wpsms_ver   = get_option('wp_sms_db_version');
        $outboxTable           = $wpdb->prefix . 'sms_send';
        $subscribersTable      = $wpdb->prefix . 'sms_subscribes';
        $subscribersGroupTable = $wpdb->prefix . 'sms_subscribes_group';

        if ($installer_wpsms_ver < WP_SMS_VERSION) {
            // Add response and status for outbox
            $column = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                DB_NAME,
                $outboxTable,
                'response'
            ));

            if (empty($column)) {
                $wpdb->query("ALTER TABLE {$outboxTable} ADD status varchar(10) NOT NULL AFTER recipient, ADD response TEXT NOT NULL AFTER recipient");
            }

            // Fix columns length issue
            $wpdb->query("ALTER TABLE {$subscribersTable} MODIFY name VARCHAR(250)");

            // Delete old last credit option
            delete_option('wp_last_credit');

            // Change charset sms_send table to utf8mb4 if not
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                DB_NAME,
                $outboxTable,
                'message'
            ));

            if ($result->COLLATION_NAME != $wpdb->collate) {
                $wpdb->query("ALTER TABLE {$outboxTable} CONVERT TO CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}");
            }

            // Change charset sms_subscribes table to utf8mb4 if not
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                DB_NAME,
                $subscribersTable,
                'name'
            ));

            if ($result->COLLATION_NAME != $wpdb->collate) {
                $wpdb->query("ALTER TABLE {$subscribersTable} CONVERT TO CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}");
            }

            // Change charset sms_subscribes_group table to utf8mb4 if not
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s ",
                DB_NAME,
                $subscribersGroupTable,
                'name'
            ));

            if ($result->COLLATION_NAME != $wpdb->collate) {
                $wpdb->query("ALTER TABLE {$subscribersGroupTable} CONVERT TO CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}");
            }

            /**
             * Add custom_fields column in send subscribes table
             */
            if (!$wpdb->get_var("SHOW COLUMNS FROM `{$subscribersTable}` like 'custom_fields'")) {
                $wpdb->query("ALTER TABLE `{$subscribersTable}` ADD `custom_fields` TEXT NULL AFTER `activate_key`");
            }

            self::createSmsOtpTable();
            self::createSmsOtpAttemptsTable();

            $this->checkIsFresh();

            TableHandler::createAllTables();

            update_option('wp_sms_db_version', WP_SMS_VERSION);
        }

        /**
         * Add media column in send table
         */
        if (!$wpdb->get_var("SHOW COLUMNS FROM `{$outboxTable}` like 'media'")) {
            $wpdb->query("ALTER TABLE `{$outboxTable}` ADD `media` TEXT NULL AFTER `recipient`");
        }
    }

    /**
     * Handle site deletion in multisite: drop plugin tables
     *
     * @param int $blog_id
     * @param bool $drop Whether to drop tables (true by default)
     */
    public static function onSiteDelete($blog_id, $drop = true)
    {
        if (!$drop || !is_multisite()) {
            return;
        }

        switch_to_blog($blog_id);

        try {
            TableHandler::dropAllTables();
        } catch (\Exception $e) {
            WPSms()::log(
                sprintf(__('WP SMS cleanup failed for site %d: %s', 'wp-sms'), $blog_id, $e->getMessage()),
                'error'
            );
        }

        restore_current_blog();
    }


    /**
     * Create sms_otp table
     *
     * @return array|false
     */
    private static function createSmsOtpTable()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $tableName       = $wpdb->prefix . self::TABLE_OTP;
        if ($wpdb->get_var("show tables like '{$tableName}'") != $tableName) {
            $query = "CREATE TABLE IF NOT EXISTS {$tableName}(
                `ID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT ,
                `phone_number` VARCHAR(20) NOT NULL,
                `agent` VARCHAR(255) NOT NULL,
                `code` CHAR(32) NOT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                PRIMARY KEY  (ID)) $charset_collate";
            return dbDelta($query);
        }
    }

    /**
     * Create sms_otp_attempts table
     *
     * @return array|false
     */
    private static function createSmsOtpAttemptsTable()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $tableName       = $wpdb->prefix . self::TABLE_OTP_ATTEMPTS;
        if ($wpdb->get_var("show tables like '{$tableName}'") != $tableName) {
            $query = "CREATE TABLE IF NOT EXISTS {$tableName}(
                `ID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `phone_number` VARCHAR(20) NOT NULL,
                `agent` VARCHAR(255) NOT NULL,
                `code` VARCHAR(255) NOT NULL,
                `result` TINYINT(1) NOT NULL,
                `time` INT UNSIGNED NOT NULL,
                PRIMARY KEY  (ID),
                KEY (phone_number)) $charset_collate";
            return dbDelta($query);
        }
    }
}

new Install();
