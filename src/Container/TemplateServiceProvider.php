<?php

namespace WSms\Container;

use WSms\Messaging\Template\Definitions\AccountAlertTemplate;
use WSms\Messaging\Template\Definitions\AccountSuspendedTemplate;
use WSms\Messaging\Template\Definitions\EmailVerificationTemplate;
use WSms\Messaging\Template\Definitions\MagicLinkTemplate;
use WSms\Messaging\Template\Definitions\OtpTemplate;
use WSms\Messaging\Template\Definitions\OtpWithMagicLinkTemplate;
use WSms\Messaging\Template\Definitions\PasswordResetTemplate;
use WSms\Messaging\Template\Definitions\PhoneVerificationTemplate;
use WSms\Messaging\Template\Definitions\WelcomeTemplate;
use WSms\Messaging\Template\Renderer\EmailRenderer;
use WSms\Messaging\Template\Renderer\SmsRenderer;
use WSms\Messaging\Template\Renderer\TelegramRenderer;
use WSms\Messaging\Template\Renderer\WhatsAppRenderer;
use WSms\Messaging\Template\Storage\OptionStorage;
use WSms\Messaging\Template\TemplateManager;

defined('ABSPATH') || exit;

class TemplateServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('template.storage', fn () => new OptionStorage());

        $container->register('template.manager', function () use ($container) {
            return new TemplateManager(
                $container->get('template.storage'),
                $container->get('template.engine'),
            );
        });
    }

    public function boot(ServiceContainer $container): void
    {
        $manager = $container->get('template.manager');

        // Register renderers.
        $manager->registerRenderer(new EmailRenderer());
        $manager->registerRenderer(new SmsRenderer());
        $manager->registerRenderer(new TelegramRenderer());
        $manager->registerRenderer(new WhatsAppRenderer());

        // Register template definitions.
        $manager->registerTemplate(new OtpTemplate());
        $manager->registerTemplate(new MagicLinkTemplate());
        $manager->registerTemplate(new OtpWithMagicLinkTemplate());
        $manager->registerTemplate(new EmailVerificationTemplate());
        $manager->registerTemplate(new PhoneVerificationTemplate());
        $manager->registerTemplate(new PasswordResetTemplate());
        $manager->registerTemplate(new WelcomeTemplate());
        $manager->registerTemplate(new AccountAlertTemplate());
        $manager->registerTemplate(new AccountSuspendedTemplate());
    }
}
