<?php

namespace WSms\Container;

use WSms\Rest\VerificationController;
use WSms\Verification\VerificationConfig;
use WSms\Verification\VerificationService;
use WSms\Verification\VerificationSession;

defined('ABSPATH') || exit;

class VerificationServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        $container->register('verification.config', fn () => new VerificationConfig());

        $container->register('verification.session', fn () => new VerificationSession(
            $container->get('verification.config'),
        ));

        $container->register('verification.service', fn () => new VerificationService(
            $container->get('mfa.otp_generator'),
            $container->get('verification.session'),
            $container->get('audit.logger'),
            $container->get('verification.config'),
        ));

        $container->register('rest.verification', fn () => new VerificationController(
            $container->get('verification.service'),
            $container->get('auth.rate_limiter'),
        ));
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        add_action('rest_api_init', fn () => $container->get('rest.verification')->registerRoutes());
    }
}
