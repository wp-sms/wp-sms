<?php

namespace WSms\Container;

use WSms\Auth\AccountManager;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthRouter;
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

        $container->register('auth.account_manager', function () use ($container) {
            return new AccountManager(
                $container->get('audit.logger'),
                $container->get('mfa.otp_generator'),
                $container->get('mfa.manager'),
            );
        });

        $container->register('auth.router', function () {
            return new AuthRouter();
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        $container->get('auth.router')->registerHooks();
    }
}
