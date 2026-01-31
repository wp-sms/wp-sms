<?php

namespace WP_SMS\Services\Hooks;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Utils\MenuUtil;
use WP_SMS\Utils\PluginHelper;

if (!defined('ABSPATH')) exit;

class HooksManager
{
    public function __construct()
    {
        add_filter('plugin_action_links_' . plugin_basename(WP_SMS_DIR . 'wp-sms.php'), [$this, 'addActionLinks']);
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

}
