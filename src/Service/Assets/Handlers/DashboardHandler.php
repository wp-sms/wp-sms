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
        if (strpos($hook, 'page_wsms') === false) {
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
                wp_enqueue_style(
                    'wsms-dashboard-rtl',
                    $this->pluginUrl . 'public/css/rtl.css',
                    $deps,
                    $this->getVersion() . '.' . filemtime($rtlPath)
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

        // Use dev server if WPSMS_VITE_DEV_SERVER constant is defined
        $viteDevServer = defined('WPSMS_VITE_DEV_SERVER') ? WPSMS_VITE_DEV_SERVER : false;

        if ($viteDevServer) {
            // Dev mode: localization is injected via admin_footer inside this method
            $this->enqueueDevelopmentAssets($viteDevServer);
        } elseif (file_exists($manifestPath)) {
            $this->enqueueProductionAssets($manifestPath, $distUrl);

            // Production: localize script data via wp_localize_script
            $unifiedPage = Dashboard::getInstance();
            wp_localize_script('wsms-dashboard', 'wpSmsSettings', $unifiedPage->getLocalizedData());
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

        // Enqueue JS
        wp_enqueue_script(
            'wsms-dashboard',
            $distUrl . $mainEntry['file'],
            [],
            $this->getVersion() . '.' . filemtime($this->getUrl($mainEntry['file'], true)),
            true
        );

        // Add type="module" to the script tag for ESM
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle === 'wsms-dashboard') {
                return str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Enqueue development assets from Vite dev server.
     *
     * @param string $viteDevServerUrl
     * @return void
     */
    private function enqueueDevelopmentAssets($viteDevServerUrl)
    {
        $unifiedPage   = Dashboard::getInstance();
        $localizedData = $unifiedPage->getLocalizedData();

        // Inject localized data and Vite scripts directly in footer
        add_action('admin_footer', function () use ($viteDevServerUrl, $localizedData) {
            ?>
            <script>
                window.wpSmsSettings = <?php echo wp_json_encode($localizedData); ?>;
            </script>
            <script type="module">
                import RefreshRuntime from '<?php echo esc_url($viteDevServerUrl); ?>/@react-refresh'
                RefreshRuntime.injectIntoGlobalHook(window)
                window.$RefreshReg$ = () => {}
                window.$RefreshSig$ = () => (type) => type
                window.__vite_plugin_react_preamble_installed__ = true
            </script>
            <?php // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Vite dev server requires type="module" scripts ?>
            <script type="module" src="<?php echo esc_url($viteDevServerUrl); ?>/@vite/client"></script>
            <script type="module" src="<?php echo esc_url($viteDevServerUrl); ?>/src/main.jsx"></script>
            <?php // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
            <?php
        });

        // Register empty script for wp_localize_script compatibility
        wp_register_script('wsms-dashboard', '', [], $this->getVersion(), true);
        wp_enqueue_script('wsms-dashboard');
    }
}
