<?php

namespace WSms\Container;

use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AccountSuspension;
use WSms\Auth\UserInfo;
use WSms\Database\Connection;
use WSms\Auth\ApiAuthGuard;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthRouter;
use WSms\Auth\AuthSession;
use WSms\Auth\AuthBlock;
use WSms\Auth\AuthShortcode;
use WSms\Auth\AvatarManager;
use WSms\Auth\CaptchaGuard;
use WSms\Auth\CaptchaProviders\HcaptchaProvider;
use WSms\Auth\CaptchaProviders\RecaptchaProvider;
use WSms\Auth\CaptchaProviders\TurnstileProvider;
use WSms\Auth\LoginGuard;
use WSms\Auth\PolicyEngine;
use WSms\Auth\ProfileFieldRegistry;
use WSms\Auth\RateLimiter;
use WSms\Auth\RegistrationFormRepository;
use WSms\Auth\SettingsRepository;
use WSms\Auth\TrustedDeviceManager;
use WSms\Service\Admin\AdminBarManager;

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
        $container->register('auth.settings', function () {
            return new SettingsRepository();
        });

        $container->register('auth.field_registry', function () use ($container) {
            return new ProfileFieldRegistry(
                $container->get('auth.settings'),
            );
        });

        $container->register('auth.avatar_manager', function () {
            return new AvatarManager();
        });

        $container->register('auth.policy', function () use ($container) {
            return new PolicyEngine(
                $container->get('mfa.manager'),
                $container->get('auth.settings'),
                $container->get('auth.field_registry'),
            );
        });

        $container->register('auth.session', function () use ($container) {
            return new AuthSession(
                $container->get('verification.otp_generator'),
            );
        });

        $container->register('auth.rate_limiter', function () {
            return new RateLimiter();
        });

        $container->register('auth.lockout', function () use ($container) {
            return new AccountLockout(
                $container->get('auth.settings'),
            );
        });

        $container->register('auth.suspension', function () {
            return new AccountSuspension();
        });

        $container->register('auth.trusted_devices', function () use ($container) {
            return new TrustedDeviceManager(
                $container->get('auth.settings'),
            );
        });

        $container->register('auth.orchestrator', function () use ($container) {
            return new AuthOrchestrator(
                $container->get('auth.policy'),
                $container->get('mfa.manager'),
                $container->get('audit.logger'),
                $container->get('auth.session'),
                $container->get('auth.lockout'),
                $container->get('auth.account_manager'),
                $container->get('auth.settings'),
                $container->get('auth.trusted_devices'),
                $container->get('auth.suspension'),
            );
        });

        $container->register('auth.account_manager', function () use ($container) {
            return new AccountManager(
                $container->get('audit.logger'),
                $container->get('verification.otp_service'),
                $container->get('mfa.manager'),
                $container->get('auth.session'),
                $container->get('auth.settings'),
                $container->get('message.dispatcher'),
                $container->get('template.manager'),
                $container->get('auth.field_registry'),
                $container->get('auth.trusted_devices'),
                $container->get('log.app'),
            );
        });

        $container->register('auth.captcha_guard', function () use ($container) {
            return new CaptchaGuard([
                'turnstile' => new TurnstileProvider(),
                'recaptcha' => new RecaptchaProvider(),
                'hcaptcha'  => new HcaptchaProvider(),
            ], $container->get('auth.settings'));
        });

        $container->register('auth.router', function () use ($container) {
            return new AuthRouter(
                $container->get('auth.settings'),
                $container->get('branding.repository'),
            );
        });

        $container->register('auth.shortcode', function () use ($container) {
            return new AuthShortcode(
                $container->get('auth.settings'),
                $container->get('branding.repository'),
            );
        });

        $container->register('auth.block', function () use ($container) {
            return new AuthBlock(
                $container->get('auth.shortcode'),
            );
        });

        $container->register('auth.form_repository', function () {
            return new RegistrationFormRepository();
        });

        $container->register('auth.login_guard', function () use ($container) {
            return new LoginGuard(
                $container->get('auth.policy'),
                $container->get('auth.session'),
                $container->get('mfa.manager'),
                $container->get('auth.settings'),
                $container->get('auth.suspension'),
            );
        });

        $container->register('auth.api_guard', function () use ($container) {
            return new ApiAuthGuard(
                $container->get('mfa.manager'),
                $container->get('auth.suspension'),
            );
        });

        $container->register('auth.admin_bar', function () use ($container) {
            return new AdminBarManager(
                $container->get('auth.settings'),
            );
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        $settings = $container->get('auth.settings');

        // Auth disabled: skip all public-facing auth hooks, register no-op shortcode.
        if (!$settings->get('auth_enabled', false)) {
            $this->bootNonAuthHooks($container);
            add_shortcode('wsms_auth', fn() => '');
            return;
        }

        // Transition mode: skip auth hooks that conflict with the active migration source plugin.
        if (get_option('wsms_transition_mode')) {
            $this->bootNonAuthHooks($container);
            return;
        }

        $container->get('auth.router')->setCaptchaGuard($container->get('auth.captcha_guard'));
        $container->get('auth.router')->registerHooks();
        $container->get('auth.shortcode')->registerHooks();
        $container->get('auth.block')->registerHooks();

        $container->get('auth.login_guard')->registerHooks();
        $container->get('auth.api_guard')->registerHooks();
        $container->get('auth.admin_bar')->registerHooks();

        // Register custom profile field meta on init.
        add_action('init', function () use ($container) {
            $container->get('auth.field_registry')->registerMeta();
        });

        // Avatar: WordPress integration hooks.
        $avatarManager = $container->get('auth.avatar_manager');
        add_filter('get_avatar_url', [$avatarManager, 'filterGetAvatarUrl'], 10, 3);
        add_filter('get_avatar', [$avatarManager, 'filterGetAvatar'], 10, 6);
        add_action('delete_user', [$avatarManager, 'cleanupOnUserDelete']);

        // Clean up custom table rows when a user is deleted.
        add_action('delete_user', function (int $userId) use ($container) {
            $db = $container->get(Connection::class);
            $db->delete(Connection::TABLE_USER_FACTORS, ['user_id' => $userId]);
            $db->delete(Connection::TABLE_VERIFICATIONS, ['user_id' => $userId]);
        });

        // Block wp_mail to placeholder email addresses.
        add_filter('pre_wp_mail', function ($null, $atts) {
            $to = is_array($atts['to'] ?? '') ? implode(',', $atts['to']) : ($atts['to'] ?? '');
            $recipients = array_map('trim', explode(',', $to));

            foreach ($recipients as $r) {
                if (!UserInfo::isPlaceholderEmail($r)) {
                    return $null; // At least one real recipient — allow.
                }
            }

            return false; // All placeholder — block.
        }, 10, 2);
    }

    /**
     * Register only non-conflicting hooks during transition mode.
     *
     * Skips: auth.router, auth.login_guard, auth.api_guard, auth.block, auth.shortcode
     * Keeps: avatar, profile fields, user deletion cleanup, placeholder email filter
     */
    private function bootNonAuthHooks(ServiceContainer $container): void
    {
        // Profile field meta registration.
        add_action('init', function () use ($container) {
            $container->get('auth.field_registry')->registerMeta();
        });

        // Avatar hooks.
        $avatarManager = $container->get('auth.avatar_manager');
        add_filter('get_avatar_url', [$avatarManager, 'filterGetAvatarUrl'], 10, 3);
        add_filter('get_avatar', [$avatarManager, 'filterGetAvatar'], 10, 6);
        add_action('delete_user', [$avatarManager, 'cleanupOnUserDelete']);

        // User deletion cleanup.
        add_action('delete_user', function (int $userId) use ($container) {
            $db = $container->get(Connection::class);
            $db->delete(Connection::TABLE_USER_FACTORS, ['user_id' => $userId]);
            $db->delete(Connection::TABLE_VERIFICATIONS, ['user_id' => $userId]);
        });

        // Placeholder email filter.
        add_filter('pre_wp_mail', function ($null, $atts) {
            $to = is_array($atts['to'] ?? '') ? implode(',', $atts['to']) : ($atts['to'] ?? '');
            $recipients = array_map('trim', explode(',', $to));

            foreach ($recipients as $r) {
                if (!UserInfo::isPlaceholderEmail($r)) {
                    return $null;
                }
            }

            return false;
        }, 10, 2);
    }
}
