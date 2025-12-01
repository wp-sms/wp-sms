<?php

namespace WP_SMS\Controller;

use Exception;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Utils\Request;

if (!defined('ABSPATH')) exit;

class LicenseManagerAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_license_manager';
    private $pluginHandler;

    public function __construct()
    {
        parent::__construct();
        $this->pluginHandler = new PluginHandler();
    }

    protected function run()
    {
        $action = $this->get('sub_action');

        switch ($action) {
            case 'check_plugin':
                $this->checkPlugin();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'wp-sms'), 400);
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