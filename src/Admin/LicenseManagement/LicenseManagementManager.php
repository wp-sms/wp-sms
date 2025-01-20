<?php

namespace WP_SMS\Admin\LicenseManagement;

use Exception;
use WP_SMS;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginActions;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginUpdater;

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
    }


    public function addMenuItem($items)
    {
        $items['plugins'] = [
            'sub'      => 'send-sms',
            'title'    => __('Add-Ons', 'wp-sms'),
            'name'     => '<span class="wps-text-warning">' . __('Add-Onssss', 'wp-sms') . '</span>',
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
        add_filter('wp_sms_ajax_list', [new PluginActions(), 'registerAjaxCallbacks']);
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
