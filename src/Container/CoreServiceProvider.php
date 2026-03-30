<?php

namespace WSms\Container;

use WSms\Database\CleanupScheduler;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

/**
 * Core service provider — registers services available on every request.
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
        $container->register(Connection::class, fn () => new Connection());

        $container->register('database.cleanup', fn () => new CleanupScheduler(
            $container->get('audit.logger'),
            $container->get('flow.execution_repository'),
            $container->get('log.message'),
            $container->get('verification.repository'),
            $container->get('auth.settings'),
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
