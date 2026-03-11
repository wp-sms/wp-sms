<?php

namespace WSms\Container;

use WSms\Auth\PolicyEngine;

defined('ABSPATH') || exit;

/**
 * Auth service provider — registers authentication and policy services.
 *
 * @since 8.0
 */
class AuthServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        $container->register('auth.policy', function () {
            return new PolicyEngine();
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
    }
}
