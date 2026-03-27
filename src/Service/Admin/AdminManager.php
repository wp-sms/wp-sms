<?php

namespace WSms\Service\Admin;

use WSms\Components\View;

defined('ABSPATH') || exit;

/**
 * Registers admin menus and renders the React dashboard shell.
 *
 * @since 8.0
 */
class AdminManager
{
    /** @var string Menu slug used for the top-level admin page. */
    const MENU_SLUG = 'wsms-auth';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenus']);
        add_action('in_admin_header', [$this, 'suppressNotices'], PHP_INT_MAX);
    }

    /**
     * Add the top-level WSMS admin menu and submenu pages.
     */
    public function registerMenus(): void
    {
        $icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9Ii01IDAgMzYgMzYiPjxwYXRoIGQ9Ik0wIDkuNTM3NTJWMTcuNzMzNUwxOC4yMTAxIDguMTc3NjRWMEwwIDkuNTM3NTJaIiBmaWxsPSIjYTdhYWFkIi8+PHBhdGggZD0iTTAgMjAuNzI5VjI4LjkwNjdMMjYgMTUuMjcxMVY3LjA5MzUxTDAgMjAuNzI5WiIgZmlsbD0iI2E3YWFhZCIvPjxwYXRoIGQ9Ik0yNS45OTcyIDE4LjI2NjZWMjYuMzUyNEw3LjgwNzM0IDM2LjAwMDFMNy43ODcxMSAyNy43MzA2TDI1Ljk5NzIgMTguMjY2NloiIGZpbGw9IiNhN2FhYWQiLz48L3N2Zz4=';

        add_menu_page(
            __('WSMS', 'wp-sms'),
            __('WSMS', 'wp-sms'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderArea'],
            $icon
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'wp-sms'),
            __('Dashboard', 'wp-sms'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderArea'],
        );

        // Fire filter so add-ons can hook into menu data
        apply_filters('wp_sms_admin_menu_list', []);
    }

    /**
     * Remove all admin notice hooks on WSMS pages.
     *
     * Hooked at `in_admin_header` (PHP_INT_MAX) — the last action before
     * `admin_notices` fires. Same pattern WooCommerce uses for fully
     * React-owned admin pages.
     */
    public function suppressNotices(): void
    {
        if (!$this->isWsmsScreen()) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('network_admin_notices');
    }

    /**
     * Render the React dashboard mount point.
     */
    public function renderArea(): void
    {
        View::load('admin/app');
    }

    private function isWsmsScreen(): bool
    {
        $screen = get_current_screen();

        return $screen && str_contains($screen->id, 'wsms-');
    }
}
