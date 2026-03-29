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

        $baseUrl = WP_SMS_URL . 'public/auth/';
        $version = WP_SMS_VERSION;

        wp_register_script('wsms-vendor', $baseUrl . 'vendor.js', ['wp-i18n'], $version, true);
        wp_register_style('wsms-vendor', $baseUrl . 'vendor.css', [], $version);
    }
}
