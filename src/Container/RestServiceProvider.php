<?php

namespace WSms\Container;

use WSms\Rest\AccountController;
use WSms\Audit\ReportAggregator;
use WSms\Rest\AdminController;
use WSms\Rest\AdminUserController;
use WSms\Rest\AuthController;
use WSms\Rest\MfaController;
use WSms\Rest\EnrollmentController;
use WSms\Rest\SocialAuthController;
use WSms\Rest\TelegramController;

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
                $container->get('auth.captcha_guard'),
                $container->get('social.manager'),
            );
        });

        $container->register('rest.social', function () use ($container) {
            return new SocialAuthController(
                $container->get('social.orchestrator'),
                $container->get('social.manager'),
                $container->get('auth.rate_limiter'),
            );
        });

        $container->register('rest.mfa', function () use ($container) {
            return new MfaController(
                $container->get('auth.orchestrator'),
                $container->get('auth.rate_limiter'),
                $container->get('auth.trusted_devices'),
            );
        });

        $container->register('rest.enrollment', function () use ($container) {
            return new EnrollmentController(
                $container->get('mfa.manager'),
                $container->get('auth.policy'),
                $container->get('auth.field_registry'),
                $container->get('auth.avatar_manager'),
                $container->get('auth.trusted_devices'),
            );
        });

        $container->register('rest.account', function () use ($container) {
            return new AccountController(
                $container->get('auth.account_manager'),
                $container->get('auth.rate_limiter'),
                $container->get('auth.session'),
                $container->get('auth.captcha_guard'),
                $container->get('auth.field_registry'),
                $container->get('auth.avatar_manager'),
            );
        });

        $container->register('audit.reports', function () {
            return new ReportAggregator();
        });

        $container->register('rest.admin', function () use ($container) {
            return new AdminController(
                $container->get('audit.logger'),
                $container->get('mfa.manager'),
                $container->get('auth.field_registry'),
                $container->get('audit.reports'),
            );
        });

        $container->register('rest.telegram', function () use ($container) {
            return new TelegramController(
                $container->get('mfa.channel.telegram'),
            );
        });

        $container->register('rest.admin_user', function () use ($container) {
            return new AdminUserController(
                $container->get('audit.logger'),
                $container->get('mfa.manager'),
                $container->get('social.repository'),
                $container->get('auth.lockout'),
                $container->get('auth.account_manager'),
                $container->get('auth.settings'),
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
            $container->get('rest.social')->registerRoutes();
            $container->get('rest.telegram')->registerRoutes();
            $container->get('rest.admin_user')->registerRoutes();
        });
    }
}
