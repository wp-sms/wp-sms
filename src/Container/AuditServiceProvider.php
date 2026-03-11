<?php

namespace WSms\Container;

use WSms\Audit\AuditLogger;

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
        $container->register('audit.logger', function () {
            return new AuditLogger();
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
    }
}
