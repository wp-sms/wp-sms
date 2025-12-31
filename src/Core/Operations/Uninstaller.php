<?php

namespace WP_SMS\Core\Operations;

use WP_SMS\Core\AbstractCore;
use WP_SMS\Option;

/**
 * Handles uninstall-time cleanup.
 *
 * On uninstall (and per site on multisite), this class removes plugin data
 * when the "delete_data_on_uninstall" option is enabled: options, transients,
 * scheduled hooks, user/post meta, and plugin-created tables.
 *
 * @see register_uninstall_hook()
 * @package WP_SMS\Core\Operations
 */
class Uninstaller extends AbstractCore
{
    /**
     * Uninstaller constructor.
     *
     * @param bool $networkWide
     * @return void
     */
    public function __construct($networkWide = false)
    {
        parent::__construct($networkWide);
        $this->execute();
    }

    /**
     * Execute the core function.
     *
     * @return void
     */
    public function execute()
    {
        $this->loadRequiredFiles();

        if (is_multisite()) {
            $blogIds = $this->wpdb->get_col("SELECT `blog_id` FROM {$this->wpdb->blogs}");

            foreach ($blogIds as $blogId) {
                switch_to_blog($blogId);

                if (Option::getOption('delete_data_on_uninstall')) {
                    $this->cleanupSiteData();
                }

                restore_current_blog();
            }
        } else {
            if (Option::getOption('delete_data_on_uninstall')) {
                $this->cleanupSiteData();
            }
        }
    }

    /**
     * Removes database options, user meta keys & tables
     *
     * @return void
     */
    public function cleanupSiteData()
    {
        // Delete the options from the WordPress options table
        delete_option('wpsms_settings');
        delete_option('wps_pp_settings');
        Option::deleteGroup(Option::DB_GROUP);
        delete_option('wp_sms_dismissed_notices');
        delete_option('wp_sms_licenses');

        // Delete transients
        delete_transient('wp_sms_check_gateway');

        // Remove all scheduled hooks
        $wpSmsCronEvents = [
            'wp_sms_check_update_licenses_status',
            'wp_sms_admin_email_report',
            'wp_sms_daily_cron_hook',
            'wp_sms_midnight_cron_hook',
        ];

        foreach ($wpSmsCronEvents as $event) {
            wp_clear_scheduled_hook($event);
        }

        // Delete user meta
        $this->wpdb->query("DELETE FROM {$this->wpdb->usermeta} WHERE `meta_key` LIKE 'wp_sms%'");
        $this->wpdb->query("DELETE FROM {$this->wpdb->postmeta} WHERE `meta_key` LIKE 'wp_sms%'");

        // Drop the tables
        $pluginTables = [
            'sms_subscribes',
            'sms_subscribes_group',
            'sms_send',
            'sms_otp',
            'sms_otp_attempts',
        ];

        foreach ($pluginTables as $tbl) {
            $tableName = $this->wpdb->prefix . $tbl;
            $this->wpdb->query("DROP TABLE IF EXISTS {$tableName}");
        }
    }

    /**
     * Clears all scheduled cron events.
     *
     * @return void
     */
    public function clearEvents()
    {
        $wpSmsCronEvents = [
            'wp_sms_check_update_licenses_status',
            'wp_sms_admin_email_report',
            'wp_sms_daily_cron_hook',
            'wp_sms_midnight_cron_hook',
        ];

        foreach ($wpSmsCronEvents as $event) {
            wp_clear_scheduled_hook($event);
        }
    }

    /**
     * Load required files.
     *
     * @return void
     */
    protected function loadRequiredFiles()
    {
        require_once WP_SMS_DIR . 'includes/class-wpsms-option.php';
    }
}
