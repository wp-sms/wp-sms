<?php

namespace WSms\Container;

use WSms\Database\CleanupScheduler;

defined('ABSPATH') || exit;

/**
 * Core service provider — registers services available on every request.
 *
 * Add new services here as features are built.
 *
 * @since 8.0
 */
class CoreServiceProvider implements ServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function register(ServiceContainer $container): void
    {
        $container->register('database.cleanup', fn () => new CleanupScheduler(
            $container->get('audit.logger'),
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function boot(ServiceContainer $container): void
    {
        add_action(CleanupScheduler::HOOK_NAME, [$container->get('database.cleanup'), 'run']);
    }
}
