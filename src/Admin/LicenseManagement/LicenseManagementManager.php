<?php

namespace WP_SMS\Admin\LicenseManagement;

use Exception;
use WP_SMS;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginActions;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Admin\LicenseManagement\Plugin\AddonUpdater;
use WP_SMS\Components\Assets;
use WP_SMS\Exceptions\LicenseException;
use WP_SMS\Notice\NoticeManager;
use WP_SMS\Utils\Request;

if (!defined('ABSPATH')) exit;

class LicenseManagementManager
{
    private $apiCommunicator;
    private $pluginHandler;
    private $handledPlugins = [];

    /**
     * Admin Page Slug
     *
     * @var string
     */
    public static $admin_menu_slug = 'wpsms_[slug]_page';

    public function __construct()
    {
        $this->apiCommunicator = new ApiCommunicator();
        $this->pluginHandler   = new PluginHandler();

        // Initialize the necessary components.
        $this->initActionCallbacks();

        add_filter('wp_sms_enable_upgrade_to_bundle', [$this, 'showUpgradeToBundle']);
        add_action('init', [$this, 'redirectOldLicenseUrlToNew']);
    }

    public function redirectOldLicenseUrlToNew()
    {
        if (
            (Request::compare('page', 'wp-sms-settings') && Request::compare('tab', 'licenses')) ||
            (Request::compare('page', 'wp-sms-plugins') && Request::compare('tab', 'add-license'))
        ) {
            wp_redirect(admin_url('admin.php?page=wsms&tab=add-ons'));
            exit;
        }
    }

    /**
     * Initialize AJAX callbacks for various license management actions.
     */
    public function initActionCallbacks()
    {
        add_action('init', [new PluginActions(), 'registerAjaxCallbacks']);
    }

    /**
     * Convert Page Slug to Page key
     *
     * @param $page_slug
     * @return mixed
     * @example wps_hists_pages -> hits
     */
    public static function getPageKeyFromSlug($page_slug)
    {
        $admin_menu_slug = explode("[slug]", self::$admin_menu_slug);
        preg_match('/(?<=' . $admin_menu_slug[0] . ').*?(?=' . $admin_menu_slug[1] . ')/', $page_slug, $page_name);
        return $page_name; # for get use $page_name[0]
    }

    /**
     * Show the "Upgrade To Premium" only if the user has a premium license.
     *
     * @return bool
     */
    public function showUpgradeToBundle()
    {
        return !(LicenseHelper::isPremiumLicenseAvailable() || LicenseHelper::isPluginLicenseValid());
    }
}
