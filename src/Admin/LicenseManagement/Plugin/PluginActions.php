<?php

namespace WP_SMS\Admin\LicenseManagement\Plugin;

use Exception;
use WP_SMS\User\UserHelper;
use WP_SMS\Utils\Request;
use WP_SMS\Admin\LicenseManagement\ApiCommunicator;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;

class PluginActions
{
    private $apiCommunicator;
    private $pluginHandler;

    public function __construct()
    {
        $this->apiCommunicator = new ApiCommunicator();
        $this->pluginHandler   = new PluginHandler();
    }

    public function registerAjaxCallbacks()
    {
        $list   = [];
        $list[] = [
            'class'  => $this,
            'action' => 'check_license'
        ];
        $list[] = [
            'class'  => $this,
            'action' => 'download_plugin'
        ];
        $list[] = [
            'class'  => $this,
            'action' => 'check_plugin'
        ];
        $list[] = [
            'class'  => $this,
            'action' => 'activate_plugin'
        ];

        foreach ($list as $item) {
            $class    = $item['class'];
            $action   = $item['action'];
            $callback = $action . '_action_callback';
            $isPublic = isset($item['public']) && $item['public'] == true ? true : false;

            if (method_exists($class, $callback)) {
                add_action('wp_ajax_wp_sms_' . $action, [$class, $callback]);

                if ($isPublic) {
                    add_action('wp_ajax_nopriv_wp_sms_' . $action, [$class, $callback]);
                }
            }
        }
    }

    public function check_license_action_callback()
    {
        check_ajax_referer('wp_rest', 'wps_nonce');

        try {
            $licenseKey = Request::has('license_key') ? wp_unslash(Request::get('license_key')) : false;
            $addOn      = Request::get('addon_slug');

            if (!$licenseKey) {
                throw new Exception(__('License key is missing.', 'wp-sms'));
            }

            $this->apiCommunicator->validateLicense($licenseKey, $addOn);

            wp_send_json_success([
                'message' => __('You\'re All Set! Your License is Successfully Activated!', 'wp-sms'),
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }

        exit;
    }

    public function download_plugin_action_callback()
    {
        check_ajax_referer('wp_rest', 'wps_nonce');

        if (!UserHelper::hasCapability('install_plugins')) {
            wp_send_json_error(__('You are not allowed to install plugins.', 'wp-sms'), 403);
        }

        try {
            $licenseKey = Request::has('license_key') ? wp_unslash(Request::get('license_key')) : false;
            $pluginSlug = Request::has('plugin_slug') ? wp_unslash(Request::get('plugin_slug')) : false;

            if (!is_main_site()) {
                throw new Exception(__('Plugin installation is not permitted on this sub-site. Please contact your network administrator to install the plugin across the entire network.', 'wp-sms'));
            }

            if (!$pluginSlug) {
                throw new Exception(__('Plugin slug is missing.', 'wp-sms'));
            }

            if (empty($licenseKey)) {
                $licenseKey = LicenseHelper::getPluginLicense($pluginSlug);
            }

            if (empty($licenseKey)) {
                throw new Exception(__('License key is missing.', 'wp-sms'));
            }

            $downloadUrl = $this->apiCommunicator->getDownloadUrlFromLicense($licenseKey, $pluginSlug);
            if (!$downloadUrl) {
                throw new Exception(__('Download URL not found!', 'wp-sms'));
            }

            // Download and install the plugin
            $this->pluginHandler->downloadAndInstallPlugin($downloadUrl);

            wp_send_json_success([
                'message' => __('Plugin downloaded and installed successfully.', 'wp-sms'),
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }

        exit;
    }

    /**
     * Handles `check_plugin` ajax call and returns info about a local plugin.
     *
     * @return void
     */
    public function check_plugin_action_callback()
    {
        check_ajax_referer('wp_rest', 'wps_nonce');

        try {
            $pluginSlug = Request::has('plugin_slug') ? wp_unslash(Request::get('plugin_slug')) : false;
            if (!$pluginSlug) {
                throw new Exception(__('Plugin slug is missing.', 'wp-sms'));
            }

            wp_send_json_success([
                'active' => $this->pluginHandler->isPluginActive($pluginSlug),
                'data'   => $this->pluginHandler->getPluginData($pluginSlug),
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }

        exit;
    }

    public function activate_plugin_action_callback()
    {
        check_ajax_referer('wp_rest', 'wps_nonce');

        if (UserHelper::hasCapability('activate_plugins')) {
            wp_send_json_error(__('You are not allowed to activate plugins.', 'wp-sms'), 403);
        }

        try {
            $pluginSlug = Request::has('plugin_slug') ? wp_unslash(Request::get('plugin_slug')) : false;

            if (!$pluginSlug) {
                throw new Exception(__('Plugin slug is missing.', 'wp-sms'));
            }

            $this->pluginHandler->activatePlugin($pluginSlug);

            wp_send_json_success([
                'message' => __('Plugin activated successfully.', 'wp-sms'),
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }

        exit;
    }
}