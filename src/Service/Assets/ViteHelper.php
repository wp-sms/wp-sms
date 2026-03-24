<?php

namespace WSms\Service\Assets;

defined('ABSPATH') || exit;

/**
 * Enqueues the Vite-built dashboard JS/CSS assets.
 *
 * @since 8.0
 */
class ViteHelper
{
    public static function enqueueDashboard(string $handle): void
    {
        $distUrl = WP_SMS_URL . 'public/app/';

        wp_enqueue_style(
            $handle . '-css',
            $distUrl . 'main.css',
            [],
            WP_SMS_VERSION,
        );

        $jsUrl = $distUrl . 'main.js';

        if (function_exists('wp_enqueue_script_module')) {
            wp_enqueue_script_module($handle, $jsUrl);
        } else {
            wp_enqueue_script($handle, $jsUrl, [], WP_SMS_VERSION, true);
        }
    }

    /**
     * Whether the Vite dev server is active.
     *
     * Set `define('WPSMS_VITE_DEV_SERVER', true)` in wp-config.php
     * during local development.
     */
    public static function isDevServer(): bool
    {
        return defined('WPSMS_VITE_DEV_SERVER') && WPSMS_VITE_DEV_SERVER;
    }
}
