<?php

namespace WP_SMS\Admin\Pages;

class SettingAdminPage
{
    /**
     * Manifest main JS file path
     *
     * @var string
     */
    private $manifestMainJs = '';

    /**
     * Manifest main CSS file paths
     *
     * @var array
     */
    private $manifestMainCss = [];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerSettingPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_notices', [$this, 'removeOtherPluginNotices'], 0);
    }

    public function registerSettingPage(): void
    {
        add_submenu_page(
            'wp-sms',
            esc_html__('New Settings', 'wp-sms'),
            esc_html__('New Settings', 'wp-sms'),
            'wpsms_setting',
            'wp-sms-new-settings',
            [$this, 'renderSettings'],
            6
        );
    }

    /**
     * Remove admin notices from other plugins on WP-SMS settings page
     */
    public function removeOtherPluginNotices(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-sms-new-settings') {
            return;
        }

        // Remove all admin notices except WP-SMS ones
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');

        // Re-add only WP-SMS notices if needed
        add_action('admin_notices', function () {
            // WP-SMS specific notices can be added here if needed
        });
    }

    public function enqueueAssets(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-sms-new-settings') {
            return;
        }

        // Enqueue WordPress media uploader
        wp_enqueue_media();

        // Load manifest
        $this->loadManifest();

        // Enqueue CSS files
        if (!empty($this->manifestMainCss)) {
            foreach ($this->manifestMainCss as $index => $cssFile) {
                wp_enqueue_style('wp-sms-settings-' . $index, WP_SMS_FRONTEND_BUILD_URL . $cssFile, [], WP_SMS_VERSION);
            }
        }

        add_action('admin_head', function () {
            // Get all registered assets
            $assets = isset($GLOBALS['wp_sms_assets']) ? $GLOBALS['wp_sms_assets'] : [];

            ?>
            <script type="text/javascript">
                window.WP_SMS_DATA = <?php echo json_encode([
                                            'nonce'   => wp_create_nonce('wp_rest'),
                                            'restUrl' => esc_url_raw(rest_url('wpsms/v1/')),
                                            'frontend_build_url' => WP_SMS_FRONTEND_BUILD_URL,
                                            'assets' => $assets,
                                            'react_starting_point' => '#settings/general'
                                        ]); ?>;
            </script>
<?php
        });

        // Enqueue JS file
        if (!empty($this->manifestMainJs)) {
            wp_enqueue_script_module('wp-sms-settings', WP_SMS_FRONTEND_BUILD_URL . $this->manifestMainJs, [], WP_SMS_VERSION);

            // Localize script data
            wp_localize_script(
                'wp-sms-settings',
                'WP_SMS_DATA',
                $this->getLocalizedData()
            );
        }
    }

    /**
     * Load Vite manifest file
     *
     * @return void
     */
    private function loadManifest(): void
    {
        if (!empty($this->manifestMainJs) && !empty($this->manifestMainCss)) {
            return;
        }

        $manifestPath = WP_SMS_DIR . 'frontend/build/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            return;
        }

        $manifestContent = file_get_contents($manifestPath);
        $decodedContent = json_decode($manifestContent, true);

        if (empty($decodedContent['src/main.tsx'])) {
            return;
        }

        $this->manifestMainJs = $decodedContent['src/main.tsx']['file'] ?? '';
        $this->manifestMainCss = $decodedContent['src/main.tsx']['css'] ?? [];
    }

    /**
     * Get localized data for JavaScript
     *
     * @return array Localized data for JavaScript
     */
    private function getLocalizedData(): array
    {
        $data = [
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => esc_url_raw(rest_url('wpsms/v1/')),
            'frontend_build_url' => WP_SMS_FRONTEND_BUILD_URL,
            'react_starting_point' => '#settings/general'
        ];

        return apply_filters('wp_sms_settings_localized_data', $data);
    }

    public function renderSettings(): void
    {
        echo '<div class="wrap wp-sms-react-wrap">';
        echo '<div id="wp-sms-react-root"></div>';
        echo '</div>';
    }
}
