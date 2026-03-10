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
    /** @var string Menu slug used for the top-level admin page. */
    const MENU_SLUG = 'wsms';

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
        $icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9Ii01IDAgMzYgMzYiPjxwYXRoIGQ9Ik0wIDkuNTM3NTJWMTcuNzMzNUwxOC4yMTAxIDguMTc3NjRWMEwwIDkuNTM3NTJaIiBmaWxsPSIjYTdhYWFkIi8+PHBhdGggZD0iTTAgMjAuNzI5VjI4LjkwNjdMMjYgMTUuMjcxMVY3LjA5MzUxTDAgMjAuNzI5WiIgZmlsbD0iI2E3YWFhZCIvPjxwYXRoIGQ9Ik0yNS45OTcyIDE4LjI2NjZWMjYuMzUyNEw3LjgwNzM0IDM2LjAwMDFMNy43ODcxMSAyNy43MzA2TDI1Ljk5NzIgMTguMjY2NloiIGZpbGw9IiNhN2FhYWQiLz48L3N2Zz4=';

        add_menu_page(
            __('WSMS', 'wp-sms'),
            __('WSMS', 'wp-sms'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            $icon
        );

        // Remove the auto-generated submenu item matching the parent
        remove_submenu_page(self::MENU_SLUG, self::MENU_SLUG);

        // Fire filter so add-ons can hook into menu data
        apply_filters('wp_sms_admin_menu_list', []);
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
