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

        $baseUrl = plugin_dir_url(WP_SMS_MAIN_FILE) . 'public/auth/';
        $version = WP_SMS_VERSION;

        wp_enqueue_style('wsms-verify-widget', $baseUrl . 'verify-widget-style.css', [], $version);
        wp_enqueue_script('wsms-verify-widget', $baseUrl . 'verify-widget.js', ['wsms-vendor'], $version, true);

        $config = [
            'restUrl' => rest_url('wsms/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ];

        if ($primaryColor) {
            $config['primaryColor'] = $primaryColor;
        }

        wp_localize_script('wsms-verify-widget', 'wsmsVerifyConfig', $config);

        wp_register_script(
            'wsms-verify-mounter',
            plugin_dir_url(WP_SMS_MAIN_FILE) . 'public/js/mounter.min.js',
            ['wsms-verify-widget'],
            WP_SMS_VERSION,
            true,
        );
    }
}
