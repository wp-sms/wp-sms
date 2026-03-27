<?php

namespace WSms\PhoneRestriction;

defined('ABSPATH') || exit;

trait RegistersVendorAsset
{
    private static bool $vendorRegistered = false;

    protected function registerVendorAsset(): void
    {
        if (self::$vendorRegistered) {
            return;
        }
        self::$vendorRegistered = true;

        $baseUrl = plugin_dir_url(WP_SMS_MAIN_FILE) . 'public/auth/';
        $version = WP_SMS_VERSION;

        wp_register_script('wsms-vendor', $baseUrl . 'vendor.js', [], $version, true);
        wp_register_style('wsms-vendor', $baseUrl . 'vendor.css', [], $version);
    }
}
