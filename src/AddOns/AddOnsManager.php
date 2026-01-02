<?php

namespace WP_SMS\AddOns;

use WP_SMS;
use WP_SMS\AddOns\Page\AddOnsPage;
use WP_SMS\Components\Assets;
use WP_SMS\Utils\Request;
use Veronalabs\LicenseClient\LicenseHub;

if (!defined('ABSPATH')) exit;

class AddOnsManager
{
    public function __construct()
    {
        add_filter('wp_sms_enable_upgrade_to_bundle', [$this, 'showUpgradeToBundle']);
        add_filter('wp_sms_enable_upgrade_notice', [$this, 'showUpgradeNotice']);
        add_filter('wp_sms_admin_menu_list', [$this, 'addMenuItem']);
        add_action('admin_init', [$this, 'initAdminPreview']);
        add_action('init', [$this, 'redirectOldLicenseUrlToNew']);
    }

    public function redirectOldLicenseUrlToNew()
    {
        if (
            (Request::compare('page', 'wp-sms-settings') && Request::compare('tab', 'licenses')) ||
            (Request::compare('page', 'wp-sms-plugins') && Request::compare('tab', 'add-license'))
        ) {
            wp_redirect(admin_url('admin.php?page=wp-sms-add-ons'));
            exit;
        }
    }

    public function initAdminPreview()
    {
        if (isset($_GET['page']) && $_GET['page'] == 'wp-sms-add-ons') {
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        }
    }

    public function enqueueScripts()
    {
        Assets::script('license-manager', 'js/licenseManager.min.js', ['jquery'], [], true);
    }

    public function addMenuItem($items)
    {
        $items['plugins'] = [
            'sub'      => 'wp-sms',
            'title'    => __('Add-Ons', 'wp-sms'),
            'name'     => '<span class="wpsms-text-warning">' . __('Add-Ons', 'wp-sms') . '</span>',
            'page_url' => 'add-ons',
            'callback' => AddOnsPage::class,
            'cap'      => WP_SMS\User\UserHelper::validateCapability(WP_SMS\Utils\OptionUtil::get('manage_capability', 'manage_options')),
            'priority' => 90,
            'break'    => true,
        ];

        return $items;
    }

    public function showUpgradeToBundle()
    {
        return !LicenseHub::isPremium();
    }

    public function showUpgradeNotice()
    {
        return !(LicenseHub::isPremium() || LicenseHub::isPluginLicensed('wp-sms-pro'));
    }
}
