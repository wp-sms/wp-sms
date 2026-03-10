<?php

namespace WSms\Service\Assets;

defined('ABSPATH') || exit;

/**
 * Enqueues scripts and styles for the WSMS admin dashboard.
 *
 * Uses ViteHelper to read the build manifest and enqueue
 * the React app as an ES module (WP 6.5+) or classic script.
 *
 * @since 8.0
 */
class AssetManager
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
    }

    /**
     * Enqueue assets on WSMS admin pages only.
     *
     * @param string $hook The current admin page hook suffix.
     * @return void
     */
    public function enqueueAdmin(string $hook): void
    {
        if (!$this->isWsmsPage($hook)) {
            return;
        }

        $this->enqueueDashboard();
    }

    /**
     * Enqueue the Vite-built React dashboard app.
     *
     * @return void
     */
    private function enqueueDashboard(): void
    {
        $manifest = ViteHelper::readManifest();

        if (!$manifest) {
            return;
        }

        ViteHelper::enqueueFromManifest($manifest, 'src/main.jsx', 'wsms-dashboard');

        wp_print_inline_script_tag(
            'var wpSmsSettings = ' . wp_json_encode($this->getLocalizedData()) . ';',
            ['id' => 'wsms-settings-data']
        );
    }

    /**
     * Build the data object exposed to JavaScript as `wpSmsSettings`.
     *
     * @return array<string, mixed>
     */
    private function getLocalizedData(): array
    {
        return [
            'restUrl'   => rest_url('wsms/v1/'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'version'   => WP_SMS_VERSION,
            'adminUrl'  => admin_url(),
            'isPremium' => defined('WP_SMS_PREMIUM_FILE'),
        ];
    }

    /**
     * Check whether the current admin page belongs to WSMS.
     *
     * @param string $hook Admin page hook suffix.
     * @return bool
     */
    private function isWsmsPage(string $hook): bool
    {
        return strpos($hook, 'toplevel_page_wsms') !== false
            || strpos($hook, '_page_wsms') !== false;
    }
}
