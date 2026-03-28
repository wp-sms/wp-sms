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

        wp_enqueue_script(
            $handle,
            $distUrl . 'main.js',
            ['wp-i18n'],
            WP_SMS_VERSION,
            true,
        );

        wp_set_script_translations($handle, 'wp-sms', WP_SMS_DIR . 'public/languages');
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
