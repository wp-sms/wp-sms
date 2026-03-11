<?php

namespace WSms\Container;

use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthSession;
use WSms\Auth\PolicyEngine;
use WSms\Auth\RateLimiter;

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

        $container->register('auth.session', function () use ($container) {
            return new AuthSession(
                $container->get('mfa.otp_generator'),
            );
        });

        $container->register('auth.rate_limiter', function () {
            return new RateLimiter();
        });

        $container->register('auth.orchestrator', function () use ($container) {
            return new AuthOrchestrator(
                $container->get('auth.policy'),
                $container->get('mfa.manager'),
                $container->get('audit.logger'),
                $container->get('auth.session'),
            );
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
    }
}
