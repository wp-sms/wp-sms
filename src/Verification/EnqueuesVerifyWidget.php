<?php

namespace WSms\Verification;

defined('ABSPATH') || exit;

trait EnqueuesVerifyWidget
{
    protected function enqueueVerifyWidget(): void
    {
        $baseUrl = plugin_dir_url(WP_SMS_MAIN_FILE) . 'public/auth/';
        $version = WP_SMS_VERSION;

        wp_enqueue_style('wsms-verify-widget', $baseUrl . 'verify-widget-style.css', [], $version);
        wp_enqueue_script('wsms-verify-widget', $baseUrl . 'verify-widget.js', [], $version, true);
        wp_localize_script('wsms-verify-widget', 'wsmsVerifyConfig', [
            'restUrl' => rest_url('wsms/v1/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }
}
