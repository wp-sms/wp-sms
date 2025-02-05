<?php

namespace WP_SMS;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Helper;
use WP_SMS\Services\Hooks\HooksManager;
use WP_SMS\Utils\PluginHelper;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * WP SMS version class
 *
 * @category   class
 * @package    WP_SMS
 */
class Version
{

    /**
     * Deprecated: Check if Pro pack is enabled
     * Use LicenseHelper::isPluginLicenseValid() instead
     *
     * @deprecated 7.0.0 Use LicenseHelper::isPluginLicenseValid()
     */
    public static function pro_is_active($pluginSlug = 'wp-sms-pro/wp-sms-pro.php')
    {
        _deprecated_function(__METHOD__, '7.0.0', 'LicenseHelper::isPluginLicenseValid()');
        return LicenseHelper::isPluginLicenseValid($pluginSlug);
    }

    /**
     * Deprecated: Check if Pro pack is installed
     * Use PluginUtilities::isPluginInstalled() instead
     *
     * @deprecated 7.0.0 Use PluginUtilities::isPluginInstalled()
     */
    public static function pro_is_installed($pluginSlug = 'wp-sms-pro/wp-sms-pro.php')
    {
        _deprecated_function(__METHOD__, '7.0.0', 'PluginUtilities::isPluginInstalled()');
        return PluginHelper::isPluginInstalled($pluginSlug);
    }

    /**
     * Deprecated: Adds Pro gateways to the gateway list.
     * Use HooksManager::addProGateways() instead.
     *
     * @deprecated 7.0.0 Use HooksManager::addProGateways()
     */
    public static function addProGateways($gateways)
    {
        _deprecated_function(__METHOD__, '7.0.0', 'HooksManager::addProGateways()');

        // Call the new method for backward compatibility
        $hooksManager = new HooksManager();
        return $hooksManager->addProGateways($gateways);
    }

}

new Version();
