<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Admin\LicenseManagement\ApiCommunicator;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginDecorator;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\User\UserHelper;
use WP_SMS\Utils\Request;

if (!defined('ABSPATH')) exit;

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
            case 'check_plugin':
                $this->checkPlugin();
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
}