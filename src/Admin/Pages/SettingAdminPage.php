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
        add_action('admin_notices', function() {
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
        wp_enqueue_script('wp-element');
        wp_enqueue_script('wp-i18n');

        $manifest_path = WP_SMS_DIR . 'frontend/build/.vite/manifest.json';
        $manifest_url  = WP_SMS_URL . 'frontend/build/';

        if (!file_exists($manifest_path)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        $entry_key = 'src/pages/settings/index.tsx';
        if (!isset($manifest[$entry_key])) {
            return;
        }

        $entry = $manifest[$entry_key];
        $import_handles = [];

        // Enqueue imported chunks (not present in this manifest, but keeping logic intact)
        if (!empty($entry['imports']) && is_array($entry['imports'])) {
            foreach ($entry['imports'] as $import_key) {
                if (!empty($manifest[$import_key]['file'])) {
                    $handle = 'wp-sms-settings-import-' . md5($import_key);
                    $src    = $manifest_url . $manifest[$import_key]['file'];

                    if (function_exists('wp_enqueue_script_module')) {
                        wp_enqueue_script_module($handle, $src, ['wp-element', 'wp-i18n']);
                    } else {
                        wp_enqueue_script($handle, $src, ['wp-element', 'wp-i18n'], null, true);
                    }

                    $import_handles[] = $handle;
                }
            }
        }
        // Enqueue main JS entry
        $script_url = $manifest_url . $entry['file'];
        $handle     = 'wp-sms-settings';

        if (function_exists('wp_enqueue_script_module')) {
            wp_enqueue_script_module($handle, $script_url, array_merge(['wp-element', 'wp-i18n'], $import_handles));
        } else {
            wp_enqueue_script($handle, $script_url, array_merge(['wp-element', 'wp-i18n'], $import_handles), null, true);
        }

        // Enqueue CSS
        if (!empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $css_file) {
                wp_enqueue_style(
                    'wp-sms-settings-' . md5($css_file),
                    $manifest_url . $css_file,
                    [],
                    null
                );
            }
        }

        // Add WP_SMS_DATA to page head to ensure it's available before React loads
        add_action('admin_head', function() {
            ?>
            <script type="text/javascript">
                window.WP_SMS_DATA = <?php echo json_encode([
                    'nonce'   => wp_create_nonce('wp_rest'),
                    'restUrl' => esc_url_raw(rest_url('wpsms/v1/')),
                ]); ?>;
            </script>
            <?php
        });

        // Also localize the script as backup
        wp_localize_script(
            $handle,
            'WP_SMS_DATA',
            [
                'nonce'   => wp_create_nonce('wp_rest'),
                'restUrl' => esc_url_raw(rest_url('wpsms/v1/')),
            ]
        );
    }

    public function renderSettings(): void
    {
        echo '<div class="wrap wp-sms-settings-wrap">';
        echo '<div id="wp-sms-settings-root"></div>';
        echo '</div>';
    }
}
