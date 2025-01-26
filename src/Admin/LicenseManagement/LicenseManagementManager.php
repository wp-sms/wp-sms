<?php

namespace WP_SMS\Admin\LicenseManagement;

use Exception;
use WP_SMS;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginActions;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginUpdater;
use WP_SMS\Components\Assets;

class LicenseManagementManager
{
    private $pluginHandler;
    private $handledPlugins = [];

    public function __construct()
    {
        $this->pluginHandler = new PluginHandler();

        // Initialize the necessary components.
        $this->initActionCallbacks();

        add_action('init', [$this, 'initPluginUpdaters']);
        add_action('admin_init', [$this, 'showPluginActivationNotice']);
        add_filter('wp_sms_enable_upgrade_to_bundle', [$this, 'showUpgradeToBundle']);
        add_filter('wp_sms_admin_menu_list', [$this, 'addMenuItem']);
        add_action('admin_init', [$this, 'initAdminPreview']);

    }

    public function initAdminPreview()
    {
        // todo change the page address
        if (isset($_GET['page']) && $_GET['page'] == 'wp-sms-add-ons-1' && isset($_GET['tab']) && $_GET['tab'] == 'add-license') {
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        }
    }

    public function enqueueScripts()
    {
        // todo change it to minified version
        $localization = [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'rest_api_nonce' => wp_create_nonce('wp_rest')
        ];
        Assets::script('license-manager', 'src/scripts/license.js', ['jquery'], $localization, true);
    }

    public function addMenuItem($items)
    {
        $items['plugins'] = [
            'sub'      => 'wp-sms',
            'title'    => __('Add-Ons', 'wp-sms'),
            'name'     => '<span class="wps-text-warning">' . __('Add-Ons', 'wp-sms') . '</span>',
            'page_url' => 'add-ons-1',
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
    private function initActionCallbacks()
    {
        add_action('init', [new PluginActions(), 'registerAjaxCallbacks']);
    }

    /**
     * Initialize the PluginUpdater for all stored licenses.
     */
    public function initPluginUpdaters()
    {
        $storedLicenses = LicenseHelper::getLicenses();

        if (!empty($storedLicenses)) {
            foreach ($storedLicenses as $licenseKey => $licenseData) {
                foreach ($licenseData['products'] as $productSlug) {
                    // Avoid duplicate handling for the same product
                    if (!in_array($productSlug, $this->handledPlugins)) {
                        $this->initPluginUpdaterIfValid($productSlug, $licenseKey);
                    }
                }
            }
        }
    }

    /**
     * Initialize PluginUpdater for a specific product and license key.
     *
     * @param string $pluginSlug The slug of the plugin (e.g., 'wp-sms-data-plus').
     * @param string $licenseKey The license key for the product.
     */
    private function initPluginUpdaterIfValid($pluginSlug, $licenseKey)
    {
        try {
            if (!$this->pluginHandler->isPluginActive($pluginSlug)) {
                return;
            }

            // Get the dynamic version of the plugin
            $pluginData = $this->pluginHandler->getPluginData($pluginSlug);
            if (!$pluginData) {
                throw new Exception(sprintf(__('Plugin data not found for: %s', 'wp-sms'), $pluginSlug));
            }

            // Initialize PluginUpdater with the version and license key
            $pluginUpdater = new PluginUpdater($pluginSlug, $pluginData['Version'], $licenseKey);
            $pluginUpdater->handle();

            $this->handledPlugins[] = $pluginSlug;

        } catch (Exception $e) {
            //todo
            WP_SMS::log(sprintf('Failed to initialize PluginUpdater for %s: %s', $pluginSlug, $e->getMessage()));
        }
    }

    /**
     * Loop through plugins and show license notice for those without a valid license
     */
    public function showPluginActivationNotice()
    {
        $plugins = $this->pluginHandler->getInstalledPlugins();

        foreach ($plugins as $plugin) {
            if (!LicenseHelper::isPluginLicenseValid($plugin['TextDomain'])) {
                $pluginUpdater = new PluginUpdater($plugin['TextDomain'], $plugin['Version']);
                $pluginUpdater->handleLicenseNotice();
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
