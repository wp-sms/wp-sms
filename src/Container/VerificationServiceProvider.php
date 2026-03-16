<?php

namespace WSms\Container;

use WSms\Rest\VerificationController;
use WSms\Verification\OtpGenerator;
use WSms\Verification\OtpService;
use WSms\Verification\ProfileReverification;
use WSms\Verification\VerificationConfig;
use WSms\Verification\VerificationRepository;
use WSms\Verification\VerificationService;
use WSms\Verification\VerificationSession;

defined('ABSPATH') || exit;

class VerificationServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        $container->register('verification.repository', fn () => new VerificationRepository());

        $container->register('verification.otp_generator', fn () => new OtpGenerator());

        $container->register('verification.otp_service', fn () => new OtpService(
            $container->get('verification.repository'),
            $container->get('verification.otp_generator'),
        ));

        $container->register('verification.config', fn () => new VerificationConfig());

        $container->register('verification.session', fn () => new VerificationSession(
            $container->get('verification.config'),
        ));

        $container->register('verification.service', fn () => new VerificationService(
            $container->get('mfa.otp_generator'),
            $container->get('verification.session'),
            $container->get('audit.logger'),
            $container->get('verification.config'),
            $container->get('message.dispatcher'),
            $container->get('template.engine'),
            $container->get('verification.repository'),
        ));

        $container->register('rest.verification', fn () => new VerificationController(
            $container->get('verification.service'),
            $container->get('auth.rate_limiter'),
        ));

        $container->register('verification.profile_reverification', fn () => new ProfileReverification(
            $container->get('auth.account_manager'),
            $container->get('auth.settings'),
        ));
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        add_action('rest_api_init', fn () => $container->get('rest.verification')->registerRoutes());

        // Only construct ProfileReverification (+ AccountManager tree) when
        // re-verification is actually enabled. Check settings first (cheap).
        add_action('init', function () use ($container) {
            $settings = $container->get('auth.settings');

            if (empty($settings->channel('email')['reverify_on_change']) && empty($settings->channel('phone')['reverify_on_change'])) {
                return;
            }

            $container->get('verification.profile_reverification')->registerHooks();
        });
    }
}
