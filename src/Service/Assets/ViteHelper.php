<?php

namespace WSms\Service\Assets;

defined('ABSPATH') || exit;

/**
 * Reads the Vite build manifest and enqueues JS/CSS assets.
 *
 * Supports WordPress 6.5+ script modules (`wp_enqueue_script_module`)
 * with a classic `wp_enqueue_script` fallback for older versions.
 *
 * @since 8.0
 */
class ViteHelper
{
    /**
     * Read and decode the Vite manifest file.
     *
     * @return array|null Decoded manifest array, or null on failure.
     */
    public static function readManifest(): ?array
    {
        $manifestPath = WP_SMS_DIR . 'public/dashboard/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            return null;
        }

        $content = file_get_contents($manifestPath);
        $manifest = json_decode($content, true);

        return is_array($manifest) ? $manifest : null;
    }

    /**
     * Enqueue JS and CSS for a given manifest entry.
     *
     * @param array  $manifest The decoded Vite manifest.
     * @param string $entry    The entry point key (e.g. 'src/main.jsx').
     * @param string $handle   WordPress script/style handle prefix.
     * @return void
     */
    public static function enqueueFromManifest(array $manifest, string $entry, string $handle): void
    {
        if (!isset($manifest[$entry])) {
            return;
        }

        $entryData = $manifest[$entry];
        $distUrl = WP_SMS_URL . 'public/dashboard/';

        // Enqueue CSS imports
        if (!empty($entryData['css'])) {
            foreach ($entryData['css'] as $i => $cssFile) {
                wp_enqueue_style(
                    $handle . '-css-' . $i,
                    $distUrl . $cssFile,
                    [],
                    WP_SMS_VERSION
                );
            }
        }

        // Enqueue JS as ES module (WP 6.5+) or fallback
        $jsUrl = $distUrl . $entryData['file'];

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
     *
     * @return bool
     */
    public static function isDevServer(): bool
    {
        return defined('WPSMS_VITE_DEV_SERVER') && WPSMS_VITE_DEV_SERVER;
    }
}
