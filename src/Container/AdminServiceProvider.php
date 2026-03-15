<?php

namespace WSms\Container;

use WSms\Service\Admin\AdminManager;
use WSms\Service\Admin\UserListManager;
use WSms\Service\Admin\UserProfileSection;
use WSms\Service\Assets\AssetManager;

defined('ABSPATH') || exit;

/**
 * Admin service provider — registers services used only in wp-admin.
 *
 * @since 8.0
 */
class AdminServiceProvider implements ServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function register(ServiceContainer $container): void
    {
        $container->register('admin', function () {
            return new AdminManager();
        });

        $container->register('assets', function () {
            return new AssetManager();
        });

        $container->register('admin.user_list', function () use ($container) {
            return new UserListManager(
                $container->get('mfa.manager'),
                $container->get('auth.lockout'),
            );
        });

        $container->register('admin.user_profile', function () use ($container) {
            return new UserProfileSection(
                $container->get('mfa.manager'),
                $container->get('social.repository'),
                $container->get('audit.logger'),
                $container->get('auth.lockout'),
                $container->get('auth.settings'),
            );
        });
    }

    /**
     * {@inheritDoc}
     */
    public function boot(ServiceContainer $container): void
    {
        if (is_admin()) {
            $container->get('admin');
            $container->get('assets');
            $container->get('admin.user_list');
            $container->get('admin.user_profile');
        }
    }
}
