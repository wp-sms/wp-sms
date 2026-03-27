<?php

namespace WSms\Container;

use WSms\Mfa\Channels\BackupCodesChannel;
use WSms\Mfa\Channels\EmailChannel;
use WSms\Mfa\Channels\MagicLinkChannel;
use WSms\Mfa\Channels\PhoneChannel;
use WSms\Mfa\Channels\LineChannel;
use WSms\Mfa\Channels\TelegramChannel;
use WSms\Mfa\Channels\PasskeyChannel;
use WSms\Mfa\Channels\TotpChannel;
use WSms\Mfa\MfaManager;
use WSms\Verification\OtpGenerator;
use WSms\Mfa\SecretEncryptor;
use WSms\Mfa\UserFactorRepository;
use WSms\Line\LineBotClient;
use WSms\Telegram\TelegramBotClient;
use WSms\Database\Connection;

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
        $container->register('mfa.factor_repository', fn ($c) => new UserFactorRepository($c->get(Connection::class)));

        $container->register('mfa.manager', function () use ($container) {
            return new MfaManager(
                $container->get('mfa.factor_repository'),
            );
        });

        // MagicLinkChannel is an internal delegate, not registered as a standalone channel.
        $container->register('mfa.channel.magic', function () use ($container) {
            return new MagicLinkChannel(
                $container->get('verification.otp_generator'),
                $container->get('audit.logger'),
                $container->get('message.dispatcher'),
                $container->get('verification.repository'),
                $container->get('template.manager'),
                $container->get('verification.otp_service'),
            );
        });

        $container->register('mfa.channel.phone', function () use ($container) {
            return new PhoneChannel(
                $container->get('verification.otp_generator'),
                $container->get('audit.logger'),
                $container->get('message.dispatcher'),
                $container->get('mfa.channel.magic'),
                $container->get('verification.repository'),
                $container->get('template.manager'),
                $container->get('verification.otp_service'),
            );
        });

        $container->register('mfa.channel.email', function () use ($container) {
            return new EmailChannel(
                $container->get('verification.otp_generator'),
                $container->get('audit.logger'),
                $container->get('message.dispatcher'),
                $container->get('mfa.channel.magic'),
                $container->get('verification.repository'),
                $container->get('template.manager'),
                $container->get('verification.otp_service'),
            );
        });

        $container->register('mfa.channel.backup', function () use ($container) {
            return new BackupCodesChannel(
                $container->get('verification.otp_generator'),
                $container->get('audit.logger'),
            );
        });

        $container->register('telegram.bot_client', function () use ($container) {
            $settingsRepo = $container->get('auth.settings');
            $botToken = $settingsRepo->channel('telegram')['bot_token'] ?? '';

            return new TelegramBotClient($botToken);
        });

        $container->register('mfa.channel.telegram', function () use ($container) {
            return new TelegramChannel(
                $container->get('verification.otp_generator'),
                $container->get('audit.logger'),
                $container->get('message.dispatcher'),
                $container->get('verification.repository'),
                $container->get('template.manager'),
                $container->get('verification.otp_service'),
            );
        });

        $container->register('line.bot_client', function () {
            $configs = get_option('wsms_gateway_configs', []);
            $token = $configs['line']['shared']['channel_access_token'] ?? '';

            // Fall back to integration configs.
            if (empty($token)) {
                $integrationConfigs = get_option('wsms_integration_configs', []);
                $token = $integrationConfigs['line']['credentials']['channel_access_token'] ?? '';
            }

            return new LineBotClient($token);
        });

        $container->register('mfa.channel.line', function () use ($container) {
            return new LineChannel(
                $container->get('verification.otp_generator'),
                $container->get('audit.logger'),
                $container->get('message.dispatcher'),
                $container->get('verification.repository'),
                $container->get('template.manager'),
                $container->get('verification.otp_service'),
            );
        });

        $container->register('mfa.secret_encryptor', function () {
            return new SecretEncryptor();
        });

        $container->register('mfa.channel.totp', function () use ($container) {
            return new TotpChannel(
                $container->get('audit.logger'),
                $container->get('mfa.secret_encryptor'),
            );
        });

        $container->register('mfa.channel.passkey', function () use ($container) {
            return new PasskeyChannel(
                $container->get('audit.logger'),
                $container->get('mfa.secret_encryptor'),
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
        $manager->registerChannel($container->get('mfa.channel.telegram'));
        $manager->registerChannel($container->get('mfa.channel.line'));
        $manager->registerChannel($container->get('mfa.channel.totp'));
        $manager->registerChannel($container->get('mfa.channel.passkey'));
        // Inject UserFactorRepository into channels.
        $factorRepo = $container->get('mfa.factor_repository');
        foreach ($manager->getAvailableChannels() as $channel) {
            if (method_exists($channel, 'setUserFactorRepository')) {
                $channel->setUserFactorRepository($factorRepo);
            }
        }
        // Also inject into internal magic link channel.
        $container->get('mfa.channel.magic')->setUserFactorRepository($factorRepo);

        // MagicLinkChannel is NOT registered — it's used internally by phone/email channels.
    }
}
