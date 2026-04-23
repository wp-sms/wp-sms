<?php

namespace WSms\Verification;

use WSms\PhoneRestriction\RegistersVendorAsset;

defined('ABSPATH') || exit;

trait EnqueuesVerifyWidget
{
    use RegistersVendorAsset;

    private static bool $verifyWidgetEnqueued = false;

    protected function enqueueVerifyWidget(?string $primaryColor = null): void
    {
        if (self::$verifyWidgetEnqueued) {
            return;
        }
        self::$verifyWidgetEnqueued = true;

        $this->registerVendorAsset();

        $baseUrl = WP_SMS_URL . 'public/auth/';
        $version = WP_SMS_VERSION;

        wp_enqueue_style('wsms-verify-widget', $baseUrl . 'verify-widget-style.css', [], $version);
        wp_enqueue_script('wsms-verify-widget', $baseUrl . 'verify-widget.js', ['wsms-vendor', 'wp-api-fetch'], $version, true);

        wp_set_script_translations('wsms-verify-widget', 'wp-sms', WP_SMS_DIR . 'public/languages');

        $config = [];

        if ($primaryColor) {
            $config['primaryColor'] = $primaryColor;
        }

        wp_localize_script('wsms-verify-widget', 'wsmsVerifyConfig', $config);

        wp_register_script(
            'wsms-verify-mounter',
            WP_SMS_URL . 'public/js/mounter.min.js',
            ['wsms-verify-widget'],
            WP_SMS_VERSION,
            true,
        );
    }
}
