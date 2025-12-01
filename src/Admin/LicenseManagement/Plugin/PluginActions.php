<?php

namespace WP_SMS\Admin\LicenseManagement\Plugin;

use Exception;
use WP_SMS\Utils\Request;

if (!defined('ABSPATH')) exit;

class PluginActions
{
    private $pluginHandler;

    public function __construct()
    {
        $this->pluginHandler = new PluginHandler();
    }

    public function registerAjaxCallbacks()
    {
        $list   = [];
        $list[] = [
            'class'  => $this,
            'action' => 'check_plugin'
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

}