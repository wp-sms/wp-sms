<?php

namespace WP_SMS\Admin\Pages;

use WP_SMS\Services\Assets\AssetsFactory;

class SettingAdminPage
{
    public function register(): void
    {
        AssetsFactory::React();
        add_action('admin_menu', [$this, 'registerSettingPage']);
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

    public function renderSettings(): void
    {
        echo '<div class="wrap wp-sms-react-wrap">';
        echo '<div id="wp-sms-react-root"></div>';
        echo '</div>';
    }
}
