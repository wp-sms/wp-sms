<?php

namespace WP_SMS\Admin\Pages;

class SettingAdminPage
{
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

        // Enqueue WordPress React dependencies
        wp_enqueue_script('wp-i18n');

        // Load assets directly from build folder
        $this->enqueueBuildAssets();

        // Add WP_SMS_DATA to page head to ensure it's available before React loads
        add_action('admin_head', function () {
?>
            <script type="text/javascript">
                window.WP_SMS_DATA = <?php echo json_encode([
                                            'nonce'   => wp_create_nonce('wp_rest'),
                                            'restUrl' => esc_url_raw(rest_url('wpsms/v1/')),
                                            'frontend_build_url' => WP_SMS_FRONTEND_BUILD_URL
                                        ]); ?>;
            </script>
<?php
        });

        // Also localize the script as backup
        wp_localize_script(
            'wp-sms-settings',
            'WP_SMS_DATA',
            [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => esc_url_raw(rest_url('wpsms/v1/')),
                'frontend_build_url' => WP_SMS_FRONTEND_BUILD_URL
            ]
        );
    }

    /**
     * Enqueue assets directly from build folder
     */
    private function enqueueBuildAssets(): void
    {
        $build_url = WP_SMS_FRONTEND_BUILD_URL;
        $build_dir = WP_SMS_DIR . 'frontend/build/assets/';

        // Find main CSS file
        $main_css = $this->findAssetFile($build_dir, 'main-', '.css');
        if ($main_css) {
            wp_enqueue_style(
                'wp-sms-settings-styles',
                $build_url . 'assets/' . $main_css,
                [],
                WP_SMS_VERSION
            );
        }

        // Find main JS file
        $main_js = $this->findAssetFile($build_dir, 'main-', '.js');
        if ($main_js) {
            $handle = 'wp-sms-settings';
            $script_url = $build_url . 'assets/' . $main_js;

            if (function_exists('wp_enqueue_script_module')) {
                wp_enqueue_script_module($handle, $script_url, ['wp-i18n']);
            } else {
                wp_enqueue_script($handle, $script_url, ['wp-i18n'], WP_SMS_VERSION, true);
            }
        }

        // Find dynamic pages JS file (optional)
        $dynamic_js = $this->findAssetFile($build_dir, 'dynamic-pages-', '.js');
        if ($dynamic_js) {
            $dynamic_script_url = $build_url . 'assets/' . $dynamic_js;
            if (function_exists('wp_enqueue_script_module')) {
                wp_enqueue_script_module('wp-sms-dynamic-pages', $dynamic_script_url, ['wp-i18n']);
            } else {
                wp_enqueue_script('wp-sms-dynamic-pages', $dynamic_script_url, ['wp-i18n'], WP_SMS_VERSION, true);
            }
        }
    }

    /**
     * Find asset file by prefix and extension
     */
    private function findAssetFile(string $directory, string $prefix, string $extension): ?string
    {
        if (!is_dir($directory)) {
            return null;
        }

        $files = glob($directory . $prefix . '*' . $extension);
        return !empty($files) ? basename($files[0]) : null;
    }

    public function renderSettings(): void
    {
        echo '<div class="wrap wp-sms-settings-wrap">';
        echo '<div id="wp-sms-settings-root"></div>';
        echo '</div>';
    }
}
