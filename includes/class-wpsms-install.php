<?php

namespace WP_SMS;

use WP_SMS\Services\Database\Managers\TableHandler;
use WP_SMS\Utils\OptionUtil as Option;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Install
{
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
        $version = get_option('wp_sms_plugin_version');

        if (empty($version)) {
            update_option('wp_sms_is_fresh', true);
        } else {
            update_option('wp_sms_is_fresh', false);
        }

        $installationTime = get_option('wp_sms_installation_time');
        if (empty($installationTime)) {
            update_option('wp_sms_installation_time', time());
        }
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

        global $wpdb;

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
        add_option('wp_sms_plugin_version', WP_SMS_VERSION);
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
}

new Install();
