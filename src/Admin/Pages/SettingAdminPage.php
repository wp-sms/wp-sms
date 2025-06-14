<?php

namespace WP_SMS\Admin\Pages;

class SettingAdminPage
{
    /**
     * Register all hooks
     */
    public function register()
    {
        add_action('admin_menu', [$this, 'registerSettingPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Add submenu item under WP SMS
     */
    public function registerSettingPage()
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
     * Enqueue Vite-compiled assets only for our page
     */
    public function enqueueAssets($hook_suffix)
    {
        // Only enqueue for our page
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-sms-new-settings') {
            return;
        }

        $manifest_path = WP_SMS_DIR . 'build/settings/.vite/manifest.json';
        $manifest_url  = WP_SMS_URL . 'build/settings/';

        if (!file_exists($manifest_path)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);

        // Adjust this key if needed depending on your Vite entry
        $entry_key = 'src/main.jsx';

        if (!isset($manifest[$entry_key])) {
            return;
        }

        $entry = $manifest[$entry_key];

        if (isset($entry['file'])) {
            wp_enqueue_script(
                'wp-sms-react-settings',
                $manifest_url . $entry['file'],
                array(),
                null,
                true
            );
        }

        if (isset($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $css_file) {
                wp_enqueue_style(
                    'wp-sms-react-settings-' . md5($css_file),
                    $manifest_url . $css_file,
                    array(),
                    null
                );
            }
        }
    }

    /**
     * Render the page container
     */
    public function renderSettings($section = 'general', $args = array())
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('New Settings', 'wp-sms') . '</h1>';
        echo '<div id="wpsms-settings-root"></div>';
        echo '</div>';
    }

}
