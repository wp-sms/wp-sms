<?php

namespace WSms\Container;

use WSms\Service\Admin\AdminManager;
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
    }

    /**
     * {@inheritDoc}
     */
    public function boot(ServiceContainer $container): void
    {
        if (is_admin()) {
            $container->get('admin');
            $container->get('assets');
        }
    }
}
