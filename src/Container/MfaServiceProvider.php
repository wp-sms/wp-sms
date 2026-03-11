<?php

namespace WSms\Container;

use WSms\Mfa\Channels\BackupCodesChannel;
use WSms\Mfa\Channels\EmailOtpChannel;
use WSms\Mfa\Channels\MagicLinkChannel;
use WSms\Mfa\Channels\SmsOtpChannel;
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

        $container->register('mfa.channel.sms', function () use ($container) {
            return new SmsOtpChannel(
                $container->get('mfa.otp_generator'),
                $container->get('audit.logger'),
            );
        });

        $container->register('mfa.channel.email', function () use ($container) {
            return new EmailOtpChannel(
                $container->get('mfa.otp_generator'),
                $container->get('audit.logger'),
            );
        });

        $container->register('mfa.channel.magic', function () use ($container) {
            return new MagicLinkChannel(
                $container->get('mfa.otp_generator'),
                $container->get('audit.logger'),
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

        $manager->registerChannel($container->get('mfa.channel.sms'));
        $manager->registerChannel($container->get('mfa.channel.email'));
        $manager->registerChannel($container->get('mfa.channel.magic'));
        $manager->registerChannel($container->get('mfa.channel.backup'));
    }
}
