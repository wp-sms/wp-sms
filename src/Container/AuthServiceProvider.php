<?php

namespace WSms\Container;

use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthRouter;
use WSms\Auth\AuthSession;
use WSms\Auth\AuthShortcode;
use WSms\Auth\LoginGuard;
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
        $container->register('auth.policy', function () use ($container) {
            return new PolicyEngine(
                $container->get('mfa.manager'),
            );
        });

        $container->register('auth.session', function () use ($container) {
            return new AuthSession(
                $container->get('mfa.otp_generator'),
            );
        });

        $container->register('auth.rate_limiter', function () {
            return new RateLimiter();
        });

        $container->register('auth.lockout', function () {
            return new AccountLockout();
        });

        $container->register('auth.orchestrator', function () use ($container) {
            return new AuthOrchestrator(
                $container->get('auth.policy'),
                $container->get('mfa.manager'),
                $container->get('audit.logger'),
                $container->get('auth.session'),
                $container->get('auth.lockout'),
                $container->get('auth.account_manager'),
            );
        });

        $container->register('auth.account_manager', function () use ($container) {
            return new AccountManager(
                $container->get('audit.logger'),
                $container->get('mfa.otp_generator'),
                $container->get('mfa.manager'),
                $container->get('auth.session'),
            );
        });

        $container->register('auth.router', function () {
            return new AuthRouter();
        });

        $container->register('auth.shortcode', function () {
            return new AuthShortcode();
        });

        $container->register('auth.login_guard', function () {
            return new LoginGuard();
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        $container->get('auth.router')->registerHooks();
        $container->get('auth.shortcode')->registerHooks();

        $container->get('auth.login_guard')->registerHooks();

        // Block wp_mail to placeholder email addresses.
        add_filter('pre_wp_mail', function ($null, $atts) {
            $to = is_array($atts['to'] ?? '') ? implode(',', $atts['to']) : ($atts['to'] ?? '');
            $recipients = array_map('trim', explode(',', $to));

            foreach ($recipients as $r) {
                if (!AccountManager::isPlaceholderEmail($r)) {
                    return $null; // At least one real recipient — allow.
                }
            }

            return false; // All placeholder — block.
        }, 10, 2);
    }
}
