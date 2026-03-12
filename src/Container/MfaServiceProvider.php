<?php

namespace WSms\Container;

use WSms\Mfa\Channels\BackupCodesChannel;
use WSms\Mfa\Channels\EmailChannel;
use WSms\Mfa\Channels\MagicLinkChannel;
use WSms\Mfa\Channels\PhoneChannel;
use WSms\Mfa\MfaManager;
use WSms\Mfa\OtpGenerator;

defined('ABSPATH') || exit;

/**
 * MFA service provider — registers MFA manager, OTP utilities, and channels.
 *
 * @since 8.0
 */
class MfaServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        $container->register('mfa.manager', function () {
            return new MfaManager();
        });

        $container->register('mfa.otp_generator', function () {
            return new OtpGenerator();
        });

        // MagicLinkChannel is an internal delegate, not registered as a standalone channel.
        $container->register('mfa.channel.magic', function () use ($container) {
            return new MagicLinkChannel(
                $container->get('mfa.otp_generator'),
                $container->get('audit.logger'),
            );
        });

        $container->register('mfa.channel.phone', function () use ($container) {
            return new PhoneChannel(
                $container->get('mfa.otp_generator'),
                $container->get('audit.logger'),
                $container->get('mfa.channel.magic'),
            );
        });

        $container->register('mfa.channel.email', function () use ($container) {
            return new EmailChannel(
                $container->get('mfa.otp_generator'),
                $container->get('audit.logger'),
                $container->get('mfa.channel.magic'),
            );
        });

        $container->register('mfa.channel.backup', function () use ($container) {
            return new BackupCodesChannel(
                $container->get('mfa.otp_generator'),
                $container->get('audit.logger'),
            );
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        $manager = $container->get('mfa.manager');

        $manager->registerChannel($container->get('mfa.channel.phone'));
        $manager->registerChannel($container->get('mfa.channel.email'));
        $manager->registerChannel($container->get('mfa.channel.backup'));
        // MagicLinkChannel is NOT registered — it's used internally by phone/email channels.
    }
}
