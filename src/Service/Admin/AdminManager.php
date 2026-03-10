<?php

namespace WSms\Service\Admin;

defined('ABSPATH') || exit;

/**
 * Registers admin menus and renders the React dashboard shell.
 *
 * @since 8.0
 */
class AdminManager
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenus']);
    }

    /**
     * Add the top-level WSMS admin menu.
     *
     * @return void
     */
    public function registerMenus(): void
    {
        add_menu_page(
            __('WSMS', 'wp-sms'),
            __('WSMS', 'wp-sms'),
            'manage_options',
            'wp-sms',
            [$this, 'renderDashboard'],
            'dashicons-email-alt',
            25
        );
    }

    /**
     * Output the mount point for the React dashboard app.
     *
     * @return void
     */
    public function renderDashboard(): void
    {
        echo '<div id="wpsms-settings-root"></div>';
    }
}
