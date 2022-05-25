<?php

namespace WP_SMS;

use WP_SMS\Admin\Helper;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * WP SMS version class
 *
 * @category   class
 * @package    WP_SMS
 */
class Version
{
    public function __construct()
    {
        if (is_admin()) {
            $this->init();
        }

        $this->registerCheckLicensesCronJob();
    }

    private function init()
    {
        // Check pro pack is installed
        if (self::pro_is_installed()) {

            // Check what version of WP-Pro using? if not new version, َShow the notice in admin area
            if (defined('WP_SMS_PRO_VERSION') and version_compare(WP_SMS_PRO_VERSION, "2.4.2", "<=")) {
                add_action('admin_notices', array($this, 'version_notice'));
            }

            // Check license key.
            if (!self::pro_is_active()) {
                add_action('admin_notices', array($this, 'license_notice'));
            }

            /**
             * Move license and license status from old setting to new setting.
             */
            $option    = Option::getOptions();
            $optionPro = Option::getOptions(true);

            if (isset($optionPro['license_key']) && $optionPro['license_key'] && isset($optionPro['license_key_status']) && $optionPro['license_key_status'] == 'yes') {
                $option['license_wp-sms-pro_key']    = $optionPro['license_key'];
                $option['license_wp-sms-pro_status'] = true;
                update_option('wpsms_settings', $option);

                unset($optionPro['license_key']);
                unset($optionPro['license_key_status']);
                update_option('wps_pp_settings', $optionPro);
            }
        } else {
            add_filter('plugin_row_meta', array($this, 'pro_meta_links'), 10, 2);
            add_filter('wpsms_gateway_list', array(self::class, 'addProGateways'));
        }
    }

    /**
     * Check pro pack is exists
     *
     * @return bool
     */
    private function pro_is_exists()
    {
        if (file_exists(WP_PLUGIN_DIR . '/wp-sms-pro/wp-sms-pro.php')) {
            return true;
        }
    }

    /**
     * Check pro pack is installed
     *
     * @param $pluginSlug
     * @return bool
     */
    public static function pro_is_installed($pluginSlug = 'wp-sms-pro/wp-sms-pro.php')
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        if (is_plugin_active($pluginSlug)) {
            return true;
        }

        return false;
    }

    /**
     * Check pro pack is enabled
     *
     * @return bool
     */
    public static function pro_is_active($pluginSlug = 'wp-sms-pro/wp-sms-pro.php')
    {
        if (!self::pro_is_installed($pluginSlug)) {
            return false;
        }

        $licenseKey    = wp_sms_get_license_key('wp-sms-pro');
        $licenseStatus = Option::getOption('license_wp-sms-pro_status');

        if ($licenseKey && $licenseStatus) {
            return true;
        }
    }

    /**
     * @param $links
     * @param $file
     *
     * @return array
     */
    public function pro_meta_links($links, $file)
    {
        if ($file == 'wp-sms/wp-sms.php') {
            $links[] = sprintf(__('<b><a href="%s" target="_blank" class="wpsms-plugin-meta-link wp-sms-pro" title="Get professional package!">Get professional package!</a></b>', 'wp-sms'), WP_SMS_SITE . '/buy');
        }

        return $links;
    }

    /**
     * @return string
     * @internal param $string
     */
    public function pro_setting_title()
    {
        echo sprintf(__('<p>WP SMS Pro v%s</p>', 'wp-sms'), WP_SMS_PRO_VERSION);
    }

    /**
     * @param $gateways
     *
     * @return mixed
     */
    public static function addProGateways($gateways)
    {
        // Set pro gateways to load in the list as Global.
        $gateways = array_merge_recursive(Gateway::$proGateways, $gateways);

        // Fix the first array key value
        unset($gateways['']);
        $gateways = array_merge(array('' => array('default' => __('Please select your gateway', 'wp-sms'))), $gateways);

        // Sort gateways by countries and merge them with global at first
        $gateways_countries = array_splice($gateways, 2);
        ksort($gateways_countries);

        $gateways = array_replace_recursive($gateways, $gateways_countries);

        return $gateways;
    }

    /**
     * Version notice
     */
    public function version_notice()
    {
        Helper::notice(sprintf(__('The <a href="%s" target="_blank">WP SMS Pro</a> is out of date and not compatible with new version of WP SMS, Please update the plugin to the <a href="%s" target="_blank">latest version</a>.', 'wp-sms'), WP_SMS_SITE, 'https://wp-sms-pro.com/my-account/downloads/'), 'error');
    }

    /**
     * License notice
     */
    public function license_notice()
    {
        $url         = admin_url('admin.php?page=wp-sms-settings&tab=licenses');
        $purchaseUrl = WP_SMS_SITE . '/buy';

        Helper::notice(sprintf(__('Please <a href="%s">enter and activate</a> your license key for WP SMS Pro to enable the features, access automatic updates and support, Need a license key? <a href="%s" target="_blank">Purchase one now!</a>', 'wp-sms'), $url, $purchaseUrl), 'error');
    }

    /**
     * Update all licenses' statuses
     *
     * @return void
     */
    public function updateLicensesStatus()
    {
        foreach (wp_sms_get_addons() as $addOnKey => $addOnName) {
            $licenseIsStillValid = wp_sms_check_remote_license($addOnKey, wp_sms_get_license_key($addOnKey));

            if ($licenseIsStillValid) {
                Option::updateOption("license_{$addOnKey}_status", true);
            } else {
                Option::updateOption("license_{$addOnKey}_status", false);
            }
        }
    }

    /**
     * Register check licenses cron job
     *
     * @return void
     */
    private function registerCheckLicensesCronJob()
    {
        // 1. Register cron schedule interval
        add_filter('cron_schedules', function ($schedules) {
            $schedules['wpsms_monthly_interval'] = [
                'interval' => 2635200,
                'display'  => __('Monthly', 'wp-sms'),
            ];

            return $schedules;
        });

        // 2. Hook the callback
        add_action('wp_sms_check_update_licenses_status', function () {
            $this->updateLicensesStatus();
        });

        // 3. Register the cron schedule
        if (!wp_next_scheduled('wp_sms_check_update_licenses_status')) {
            wp_schedule_event(time(), 'wpsms_monthly_interval', 'wp_sms_check_update_licenses_status');
        }
    }
}

new Version();
