<?php

namespace WSms\Service\Admin;

use WSms\Auth\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Adds a "My Account" link to the WordPress admin bar user dropdown.
 *
 * @since 8.0
 */
class AdminBarManager
{
    private SettingsRepository $settingsRepo;

    public function __construct(SettingsRepository $settingsRepo)
    {
        $this->settingsRepo = $settingsRepo;
    }

    public function registerHooks(): void
    {
        add_action('admin_bar_menu', [$this, 'addAccountNode'], 999);
    }

    /**
     * @param \WP_Admin_Bar $adminBar
     */
    public function addAccountNode($adminBar): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $basePath = rtrim($this->settingsRepo->get('auth_base_url', '/account'), '/');

        // WP_Admin_Bar renders siblings in insertion order with no positional API,
        // so we remove logout and re-add it after our node to appear above it.
        $logoutNode = $adminBar->get_node('logout');
        if ($logoutNode) {
            $adminBar->remove_node('logout');
        }

        $adminBar->add_node([
            'id'     => 'wsms-account',
            'parent' => 'user-actions',
            'title'  => __('My Account', 'wp-sms'),
            'href'   => home_url($basePath),
        ]);

        if ($logoutNode) {
            $adminBar->add_node((array) $logoutNode);
        }
    }
}
