<?php

namespace WSms\Container;

use WSms\Messaging\Gateway\Email\WpMailGateway;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Gateway\Telegram\TelegramGateway;
use WSms\Messaging\Gateway\Webhook\HttpWebhookGateway;
use WSms\Messaging\Gateway\WpSms\WpSmsGateway;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Template\MustacheEngine;

defined('ABSPATH') || exit;

class MessagingServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('gateway.registry', fn() => new GatewayRegistry());
        $container->register('gateway.sms.wp', fn() => new WpSmsGateway());
        $container->register('gateway.email.wp', fn() => new WpMailGateway());
        $container->register('gateway.webhook', fn() => new HttpWebhookGateway());
        $container->register('gateway.telegram', fn($c) => new TelegramGateway(
            $c->get('telegram.bot_client'),
        ));
        $container->register('template.engine', fn() => new MustacheEngine());

        $container->register('message.dispatcher', fn($c) => new MessageDispatcher(
            $c->get('gateway.registry'),
            $c->get('log.message'),
            $c->get('event.dispatcher'),
            $c->get('queue'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        $registry = $container->get('gateway.registry');
        $registry->register($container->get('gateway.sms.wp'));
        $registry->register($container->get('gateway.email.wp'));
        $registry->register($container->get('gateway.webhook'));
        $registry->register($container->get('gateway.telegram'));
    }
}
