<?php

namespace WP_SMS\Admin\LicenseManagement\Plugin;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class AddonUpdater
 *
 * @deprecated Add-on updates are now handled by individual Pro plugins.
 */
class AddonUpdater
{
    /**
     * AddonUpdater constructor.
     *
     * @deprecated Add-on updates are now handled by individual Pro plugins.
     *
     * @param string $pluginSlug
     * @param string $pluginVersion
     * @param string $licenseKey
     */
    public function __construct($pluginSlug = '', $pluginVersion = '', $licenseKey = '')
    {
        // Add-on updates are now handled by individual Pro plugins
    }

    /**
     * @deprecated Add-on updates are now handled by individual Pro plugins.
     */
    public function handleLicenseNotice()
    {
        // Add-on updates are now handled by individual Pro plugins
    }

    /**
     * @deprecated Add-on updates are now handled by individual Pro plugins.
     */
    public function pluginsApiInfo($res, $action, $args)
    {
        return $res;
    }

    /**
     * @deprecated Add-on updates are now handled by individual Pro plugins.
     */
    public function checkForUpdate($transient)
    {
        return $transient;
    }

    /**
     * @deprecated Add-on updates are now handled by individual Pro plugins.
     */
    public function clearCache($upgrader, $options)
    {
        // Add-on updates are now handled by individual Pro plugins
    }
}
