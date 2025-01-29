<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Admin\LicenseManagement\ApiCommunicator;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Utils\Request;

class LicenseManagerAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_license_manager';
    private $apiCommunicator;
    private $pluginHandler;

    public function __construct()
    {
        parent::__construct();
        $this->apiCommunicator = new ApiCommunicator();
        $this->pluginHandler   = new PluginHandler();
    }

    protected function run()
    {
        $action = $this->get('sub_action');

        switch ($action) {
            case 'check_license':
                $this->checkLicense();
                break;
            case 'download_plugin':
                $this->downloadPlugin();
                break;
            case 'check_plugin':
                $this->checkPlugin();
                break;
            case 'activate_plugin':
                $this->activatePlugin();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'wp-sms'), 400);
        }
    }

    private function checkLicense()
    {
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
    }

    private function downloadPlugin()
    {
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

            $this->pluginHandler->downloadAndInstallPlugin($downloadUrl);

            wp_send_json_success([
                'message' => __('Plugin downloaded and installed successfully.', 'wp-sms'),
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function checkPlugin()
    {
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
    }

    private function activatePlugin()
    {
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
    }
}