<?php

namespace WSms\Container;

use WSms\Social\OAuthStateManager;
use WSms\Social\Oidc\JwtValidator;
use WSms\Social\Oidc\OidcDiscovery;
use WSms\Social\Oidc\OidcPresets;
use WSms\Social\Oidc\OidcProvider;
use WSms\Social\Providers\GitHubProvider;
use WSms\Social\Providers\GoogleProvider;
use WSms\Social\SocialAccountRepository;
use WSms\Social\SocialAuthManager;
use WSms\Social\SocialAuthOrchestrator;

defined('ABSPATH') || exit;

class SocialServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        $container->register('social.repository', function () {
            return new SocialAccountRepository();
        });

        $container->register('social.state_manager', function () {
            return new OAuthStateManager();
        });

        $container->register('social.manager', function () {
            return new SocialAuthManager();
        });

        $container->register('social.provider.google', function () {
            return new GoogleProvider();
        });

        $container->register('social.provider.github', function () {
            return new GitHubProvider();
        });

        // Shared OIDC services.
        $container->register('social.oidc.discovery', function () {
            return new OidcDiscovery();
        });

        $container->register('social.oidc.jwt_validator', function () {
            return new JwtValidator();
        });

        // Telegram OIDC provider.
        $container->register('social.provider.telegram', function () use ($container) {
            $settings = get_option('wsms_auth_settings', []);
            $tg = $settings['social']['telegram'] ?? [];

            return new OidcProvider(
                OidcPresets::telegram($tg['client_id'] ?? '', $tg['client_secret'] ?? ''),
                $container->get('social.oidc.discovery'),
                $container->get('social.oidc.jwt_validator'),
            );
        });

        $container->register('social.orchestrator', function () use ($container) {
            return new SocialAuthOrchestrator(
                $container->get('social.manager'),
                $container->get('social.repository'),
                $container->get('social.state_manager'),
                $container->get('auth.orchestrator'),
                $container->get('auth.account_manager'),
                $container->get('auth.session'),
                $container->get('audit.logger'),
                $container->get('auth.lockout'),
                $container->get('auth.policy'),
                $container->has('mfa.channel.telegram') ? $container->get('mfa.channel.telegram') : null,
                $container->get('auth.avatar_manager'),
            );
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        $manager = $container->get('social.manager');

        $manager->registerProvider($container->get('social.provider.google'));
        $manager->registerProvider($container->get('social.provider.github'));
        $manager->registerProvider($container->get('social.provider.telegram'));
    }
}
