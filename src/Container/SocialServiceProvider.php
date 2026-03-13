<?php

namespace WSms\Container;

use WSms\Social\OAuthStateManager;
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
            );
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        $manager = $container->get('social.manager');

        $manager->registerProvider($container->get('social.provider.google'));
    }
}
