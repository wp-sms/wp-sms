<?php

namespace WP_SMS\Services\Hooks;

use WP_SMS\Utils\MenuUtil;
use WP_SMS\Utils\PluginHelper;
use WP_SMS\Gateway;

class HooksManager
{
    public function __construct()
    {
        add_filter('plugin_action_links_' . plugin_basename(WP_SMS_DIR . 'wp-sms.php'), [$this, 'addActionLinks']);

        if (!PluginHelper::isPluginInstalled('wp-sms-pro/wp-sms-pro.php')) {
            add_filter('wpsms_gateway_list', [$this, 'addProGateways']);
        }
    }

    /**
     * Adds custom links to the plugin action links in the WordPress admin plugins page.
     *
     * @param array $links The existing plugin action links.
     *
     * @return array The modified links with the custom links added.
     */
    public function addActionLinks($links)
    {
        $customLinks = [
            '<a class="wps-premium-link-btn" target="_blank" href="https://wp-sms-pro.com/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=header">' . esc_html__('Get All-in-One', 'wp-sms') . '</a>',
            '<a href="' . MenuUtil::getAdminUrl('settings') . '">' . esc_html__('Settings', 'wp-sms') . '</a>',
        ];

        return array_merge($customLinks, $links);
    }

    /**
     * Adds Pro gateways to the gateway list.
     *
     * @param array $gateways The existing gateways.
     *
     * @return array The modified gateways with Pro gateways added.
     */
    public function addProGateways($gateways)
    {
        // Set pro gateways to load in the list as Global.
        $gateways = array_merge_recursive($gateways, Gateway::$proGateways);

        // Fix the first array key value
        unset($gateways['']);
        $gateways = array_merge(['' => ['default' => esc_html__('Please select your gateway', 'wp-sms')]], $gateways);

        // Sort gateways by countries and merge them with global at first
        $gateways_countries = array_splice($gateways, 2);
        ksort($gateways_countries);

        return array_replace_recursive($gateways, $gateways_countries);
    }
}
