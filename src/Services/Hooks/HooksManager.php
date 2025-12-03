<?php

namespace WP_SMS\Services\Hooks;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Utils\MenuUtil;
use WP_SMS\Utils\PluginHelper;
use WP_SMS\Gateway;

if (!defined('ABSPATH')) exit;

class HooksManager
{
    public function __construct()
    {
        add_filter('plugin_action_links_' . plugin_basename(WP_SMS_DIR . 'wp-sms.php'), [$this, 'addActionLinks']);
        add_filter('wpsms_gateway_list', [$this, 'addProGateways']);
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
        $isPremium = (bool) LicenseHelper::isPremiumLicenseAvailable();

        $customLinks = [
            '<a href="' . MenuUtil::getAdminUrl('settings') . '">' . esc_html__('Settings', 'wp-sms') . '</a>',
        ];

        if (!$isPremium) {
            $premiumLink = '<a class="wpsms-aio-link-btn" target="_blank" href="https://wp-sms-pro.com/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=plugins">' . esc_html__('Get All-in-One', 'wp-sms') . '</a>';
            array_unshift($customLinks, $premiumLink);
        }

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
        // Merge pro gateways into existing gateways, without duplicates
        foreach (Gateway::$proGateways as $country => $gatewayList) {
            foreach ($gatewayList as $key => $value) {
                if (!isset($gateways[$country][$key])) {
                    $gateways[$country][$key] = $value;
                }
            }
        }

        // Fix the first array key value
        unset($gateways['']);
        $gateways = array_merge(['' => ['default' => esc_html__('Please select your gateway', 'wp-sms')]], $gateways);

        // Sort gateways by countries and merge them with global at first
        $gateways_countries = array_splice($gateways, 2);
        ksort($gateways_countries);

        return array_replace_recursive($gateways, $gateways_countries);
    }
}
