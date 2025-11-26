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

        add_action('init', [$this, 'constLicenseValidation']);
        add_action('admin_init', [$this, 'showPluginActivationNotice']);
        add_filter('wp_sms_enable_upgrade_to_bundle', [$this, 'showUpgradeToBundle']);
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

    /**
     * Validates licenses for active plugins using constants defined in wp-config.php.
     *
     * This method loops through a list of expected plugin license constants,
     * and for each active plugin with a defined constant and available license key,
     * it triggers license validation via the API communicator.
     *
     * @return void
     *
     * @throws Exception if the API call fails
     */
    public function constLicenseValidation(): void
    {
        if (!defined('WP_SMS_LICENSE')) {
            return;
        }

        $licenses = WP_SMS_LICENSE;

        if (is_string($licenses)) {
            $licenses = array_map('sanitize_text_field', explode(',', $licenses));
        } elseif (is_array($licenses)) {
            $licenses = array_map('sanitize_text_field', $licenses);
        } else {
            return;
        }

        foreach ($licenses as $license) {
            if (LicenseHelper::getLicenseInfo($license)) {
                continue; // License is already stored, skip validation
            }

            try {
                $this->apiCommunicator->validateLicense($license);
            } catch (LicenseException $e) {
                NoticeManager::getInstance()->registerNotice(
                    'license_validation',
                    sprintf(
                        wp_kses_post(__('Failed to validate license: %s', 'wp-sms')),
                        wp_kses_post($e->getMessage())
                    ),
                    true
                );
                return;
            }
        }
    }


    public function addMenuItem($items)
    {
        $items['plugins'] = [
            'sub'      => 'wp-sms',
            'title'    => __('Add-Ons', 'wp-sms'),
            'name'     => '<span class="wpsms-text-warning">' . __('Add-Ons', 'wp-sms') . '</span>',
            'page_url' => 'add-ons',
            'callback' => LicenseManagerPage::class,
            'cap'      => WP_SMS\User\UserHelper::validateCapability(WP_SMS\Utils\OptionUtil::get('manage_capability', 'manage_options')),
            'priority' => 90,
            'break'    => true,
        ];

        return $items;
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
     * Loop through plugins and show license notice for those without a valid license
     */
    public function showPluginActivationNotice()
    {
        $plugins = $this->pluginHandler->getInstalledPlugins();

        foreach ($plugins as $plugin) {
            if (!LicenseHelper::isPluginLicenseValid($plugin['TextDomain'])) {
                $addonUpdater = new AddonUpdater($plugin['TextDomain'], $plugin['Version']);
                $addonUpdater->handleLicenseNotice();
            }
        }
    }

    /**
     * Show the "Upgrade To Premium" only if the user has a premium license.
     *
     * @return bool
     */
    public function showUpgradeToBundle()
    {
        return !LicenseHelper::isPremiumLicenseAvailable();
    }
}
