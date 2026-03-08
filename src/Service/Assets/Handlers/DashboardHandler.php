<?php

namespace WP_SMS\Service\Assets\Handlers;

use WP_SMS\Abstracts\BaseAssets;
use WP_SMS\Admin\Dashboard;

if (!defined('ABSPATH')) exit;

class DashboardHandler extends BaseAssets
{
    public function __construct()
    {
        $this->pluginUrl    = WP_SMS_URL;
        $this->pluginDir    = WP_SMS_DIR;
        $this->assetVersion = WP_SMS_VERSION;

        $this->setContext('dashboard');
        $this->setAssetDir('public/dashboard');

        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * Main enqueue entry point — dispatches to prod or dev mode.
     *
     * @param string $hook
     * @return void
     */
    public function enqueue($hook)
    {
        if ($hook !== 'toplevel_page_wsms') {
            return;
        }

        $this->styles();
        $this->scripts($hook);
    }

    /**
     * Enqueue dashboard styles.
     *
     * @return void
     */
    public function styles()
    {
        // RTL overrides for the React dashboard.
        if (is_rtl()) {
            $rtlPath = $this->pluginDir . 'public/css/rtl.css';
            if (file_exists($rtlPath)) {
                $deps = wp_style_is('wsms-dashboard', 'registered') || wp_style_is('wsms-dashboard', 'enqueued')
                    ? ['wsms-dashboard']
                    : [];
                $mtime = filemtime($rtlPath);
                wp_enqueue_style(
                    'wsms-dashboard-rtl',
                    $this->pluginUrl . 'public/css/rtl.css',
                    $deps,
                    $this->getVersion() . ($mtime !== false ? '.' . $mtime : '')
                );
            }
        }
    }

    /**
     * Enqueue dashboard scripts (production or development).
     *
     * @param string $hook
     * @return void
     */
    public function scripts(string $hook = '')
    {
        $distPath = $this->getUrl('', true);
        $distUrl  = $this->getUrl('');

        $manifestPath = $distPath . '.vite/manifest.json';

        // Use dev server if WPSMS_VITE_DEV_SERVER is defined as a non-empty string URL.
        $viteDevServer = (defined('WPSMS_VITE_DEV_SERVER') && is_string(WPSMS_VITE_DEV_SERVER))
            ? rtrim(WPSMS_VITE_DEV_SERVER, '/')
            : false;

        if ($viteDevServer) {
            $this->enqueueDevelopmentAssets($viteDevServer);
        } elseif (file_exists($manifestPath)) {
            $this->enqueueProductionAssets($manifestPath, $distUrl);
        }
    }

    /**
     * Enqueue production assets from Vite build.
     *
     * @param string $manifestPath
     * @param string $distUrl
     * @return void
     */
    private function enqueueProductionAssets($manifestPath, $distUrl)
    {
        $manifest = json_decode(file_get_contents($manifestPath), true);

        // Find the main entry file
        $mainEntry = null;
        foreach ($manifest as $key => $entry) {
            if (isset($entry['isEntry']) && $entry['isEntry']) {
                $mainEntry = $entry;
                break;
            }
        }

        if (!$mainEntry) {
            return;
        }

        // Enqueue CSS
        if (isset($mainEntry['css'])) {
            foreach ($mainEntry['css'] as $index => $cssFile) {
                wp_enqueue_style(
                    'wsms-dashboard' . ($index > 0 ? '-' . $index : ''),
                    $distUrl . $cssFile,
                    [],
                    $this->getVersion()
                );
            }
        }

        // Enqueue JS as native ES module (WP 6.5+)
        if (function_exists('wp_enqueue_script_module')) {
            // null = no version query string appended; Vite content-hashes the filename instead.
            wp_enqueue_script_module('wsms-dashboard', $distUrl . $mainEntry['file'], [], null);

            // Print localized data immediately — admin_enqueue_scripts fires inside <head>,
            // before the module is printed in the footer by print_enqueued_script_modules().
            // wp_localize_script() is incompatible with script modules.
            $this->printLocalizedData();
        } else {
            // Fallback for WP < 6.5
            $filePath  = $this->getUrl($mainEntry['file'], true);
            $fileMtime = file_exists($filePath) ? filemtime($filePath) : false;
            wp_enqueue_script(
                'wsms-dashboard',
                $distUrl . $mainEntry['file'],
                [],
                $this->getVersion() . ($fileMtime !== false ? '.' . $fileMtime : ''),
                true
            );
            add_filter('script_loader_tag', function ($tag, $handle) {
                if ($handle === 'wsms-dashboard') {
                    return str_replace(' src', ' type="module" src', $tag);
                }
                return $tag;
            }, 10, 2);
            wp_localize_script('wsms-dashboard', 'wpSmsSettings', Dashboard::getInstance()->getLocalizedData());
        }
    }

    /**
     * Print localized settings data for the React app.
     *
     * Called directly during admin_enqueue_scripts (which fires inside <head>),
     * so wp_print_inline_script_tag() outputs at the correct position — before
     * any module scripts printed in the footer.
     *
     * Since wp_localize_script() is incompatible with script modules, data is
     * injected as a classic <script> var. The React bundle always reads this as
     * window.wpSmsSettings (never as a bare identifier).
     *
     * @return void
     */
    private function printLocalizedData()
    {
        wp_print_inline_script_tag(
            'var wpSmsSettings = ' . wp_json_encode(Dashboard::getInstance()->getLocalizedData()) . ';',
            ['id' => 'wsms-dashboard-settings']
        );
    }

    /**
     * Enqueue development assets from Vite dev server.
     *
     * @param string $viteDevServerUrl
     * @return void
     */
    private function enqueueDevelopmentAssets(string $viteDevServerUrl)
    {
        // Print localized data immediately — same timing as the production path.
        $this->printLocalizedData();

        // Inject React Refresh preamble + Vite client + entry point via admin_head.
        add_action('admin_head', function () use ($viteDevServerUrl) {
            // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
            echo '<script type="module">
import RefreshRuntime from "' . esc_url($viteDevServerUrl) . '/@react-refresh"
RefreshRuntime.injectIntoGlobalHook(window)
window.$RefreshReg$ = () => {}
window.$RefreshSig$ = () => (type) => type
window.__vite_plugin_react_preamble_installed__ = true
</script>' . "\n";
            echo '<script type="module" src="' . esc_url($viteDevServerUrl) . '/@vite/client"></script>' . "\n";
            // Entry point must match the `input` in vite.config.js (resources/react/src/main.jsx).
            echo '<script type="module" src="' . esc_url($viteDevServerUrl) . '/src/main.jsx"></script>' . "\n";
            // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript
        });
    }
}
