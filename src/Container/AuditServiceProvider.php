<?php

namespace WSms\Container;

use WSms\Audit\AuditLogger;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

/**
 * Audit service provider — registers the authentication event logger.
 *
 * @since 8.0
 */
class AuditServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        $container->register('audit.logger', function () use ($container) {
            return new AuditLogger(
                $container->get(Connection::class),
                $container->get('auth.settings'),
            );
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        $container->get('audit.logger')->setEventDispatcher(
            $container->get('event.dispatcher')
        );
    }
}
