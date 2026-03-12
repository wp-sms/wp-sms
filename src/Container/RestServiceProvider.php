<?php

namespace WSms\Container;

use WSms\Rest\AccountController;
use WSms\Rest\AdminController;
use WSms\Rest\AuthController;
use WSms\Rest\MfaController;
use WSms\Rest\EnrollmentController;

defined('ABSPATH') || exit;

class RestServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        $container->register('rest.auth', function () use ($container) {
            return new AuthController(
                $container->get('auth.orchestrator'),
                $container->get('auth.rate_limiter'),
                $container->get('auth.policy'),
            );
        });

        $container->register('rest.mfa', function () use ($container) {
            return new MfaController(
                $container->get('auth.orchestrator'),
                $container->get('auth.rate_limiter'),
            );
        });

        $container->register('rest.enrollment', function () use ($container) {
            return new EnrollmentController(
                $container->get('mfa.manager'),
                $container->get('auth.policy'),
            );
        });

        $container->register('rest.account', function () use ($container) {
            return new AccountController(
                $container->get('auth.account_manager'),
                $container->get('auth.rate_limiter'),
                $container->get('auth.session'),
            );
        });

        $container->register('rest.admin', function () use ($container) {
            return new AdminController(
                $container->get('audit.logger'),
                $container->get('mfa.manager'),
            );
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        add_action('rest_api_init', function () use ($container) {
            $container->get('rest.auth')->registerRoutes();
            $container->get('rest.mfa')->registerRoutes();
            $container->get('rest.enrollment')->registerRoutes();
            $container->get('rest.account')->registerRoutes();
            $container->get('rest.admin')->registerRoutes();
        });
    }
}
