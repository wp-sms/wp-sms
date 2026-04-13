<?php

namespace WSms\Container;

use WSms\Contact\StatusPropagator;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Email\EmailHeaderComposer;
use WSms\Messaging\Email\UnsubscribeTokenService;
use WSms\Messaging\Gateway\Email\MailtrapGateway;
use WSms\Messaging\Gateway\Email\WpMailGateway;
use WSms\Messaging\Gateway\GatewayApiClient;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Gateway\Provider\KavenegarProvider;
use WSms\Messaging\Gateway\Provider\NetGsmProvider;
use WSms\Messaging\Gateway\Provider\OvhProvider;
use WSms\Messaging\Gateway\Provider\TwilioProvider;
use WSms\Messaging\Gateway\Provider\SmsIrProvider;
use WSms\Messaging\Gateway\Provider\VonageProvider;
use WSms\Messaging\Gateway\Line\LineGateway;
use WSms\Messaging\Gateway\Telegram\TelegramGateway;
use WSms\Messaging\Gateway\TestGateway;
use WSms\Messaging\Gateway\Webhook\HttpWebhookGateway;
use WSms\Messaging\Inbound\KeywordMatcher;
use WSms\Messaging\Inbound\OptOutManager;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\SuppressionGuard;
use WSms\Messaging\Template\MustacheEngine;

defined('ABSPATH') || exit;

class MessagingServiceProvider implements ServiceProvider
{
    /** @var array<string, class-string> Provider ID => class name for deferred registration */
    private const PROVIDERS = [
        'twilio'    => TwilioProvider::class,
        'vonage'    => VonageProvider::class,
        'kavenegar' => KavenegarProvider::class,
        'ovh'       => OvhProvider::class,
        'netgsm'    => NetGsmProvider::class,
        'smsir'     => SmsIrProvider::class,
    ];

    public function register(ServiceContainer $container): void
    {
        $container->register('gateway.api_client', fn() => new GatewayApiClient());
        $container->register('gateway.registry', fn() => new GatewayRegistry());
        $container->register('gateway.email.wp', fn() => new WpMailGateway());
        $container->register('gateway.webhook', fn() => new HttpWebhookGateway());
        $container->register('gateway.telegram', fn($c) => new TelegramGateway(
            $c->get('telegram.bot_client'),
        ));
        $container->register('gateway.line', fn($c) => new LineGateway(
            $c->get('line.bot_client'),
        ));
        $container->register('gateway.test', fn() => new TestGateway());
        $container->register('template.engine', fn() => new MustacheEngine());

        $container->register('message.dispatcher', fn($c) => new MessageDispatcher(
            $c->get('gateway.registry'),
            $c->get('log.message'),
            $c->get('event.dispatcher'),
            $c->get('queue'),
        ));

        $container->register('messaging.keyword_matcher', function () {
            $settings = get_option('wsms_optout_settings', []);
            return new KeywordMatcher(
                $settings['custom_stop_keywords'] ?? [],
                $settings['custom_start_keywords'] ?? [],
            );
        });

        $container->register('messaging.optout_manager', fn($c) => new OptOutManager(
            $c->get('contact.repository'),
            $c->get('event.dispatcher'),
            $c->get('message.dispatcher'),
            $c->get('messaging.keyword_matcher'),
        ));

        $container->register('messaging.suppression_guard', fn($c) => new SuppressionGuard(
            $c->get('contact.repository'),
        ));

        $container->register('messaging.status_propagator', fn($c) => new StatusPropagator(
            $c->get('contact.repository'),
            $c->get('event.dispatcher'),
        ));

        $container->register('email.unsubscribe_token', fn() => new UnsubscribeTokenService());

        $container->register('email.header_composer', fn($c) => new EmailHeaderComposer(
            $c->get('email.unsubscribe_token'),
        ));

        $container->register('template.catalog_manager', fn($c) => new TemplateCatalogManager(
            $c->get('gateway.registry'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        // Lazy wire: OptOutManager is only resolved if a send failure looks like an opt-out
        $container->get('message.dispatcher')->setOptOutManagerResolver(
            fn() => $container->get('messaging.optout_manager'),
        );

        // Lazy wire: SuppressionGuard is only resolved when checking a send
        $container->get('message.dispatcher')->setSuppressionGuardResolver(
            fn() => $container->get('messaging.suppression_guard'),
        );

        $registry = $container->get('gateway.registry');
        $apiClient = $container->get('gateway.api_client');

        // Eager: built-in gateways (always available, no external API deps)
        $registry->register($container->get('gateway.email.wp'));
        $registry->register($container->get('gateway.webhook'));

        // Deferred: email gateways with external API deps
        $registry->registerDeferred('mailtrap', function () use ($apiClient) {
            $gateway = new MailtrapGateway();
            $gateway->setApiClient($apiClient);
            return $gateway;
        });

        // Deferred: gateways with constructor dependencies
        $registry->registerDeferred('telegram', function () use ($container, $apiClient) {
            $gateway = $container->get('gateway.telegram');
            $gateway->setApiClient($apiClient);
            return $gateway;
        });
        $registry->registerDeferred('line', function () use ($container, $apiClient) {
            $gateway = $container->get('gateway.line');
            $gateway->setApiClient($apiClient);
            return $gateway;
        });

        // Deferred: all SMS/messaging providers (lazy — only instantiated when accessed)
        // All providers get the API client; template providers also get the catalog manager
        $templateProviders = ['twilio', 'kavenegar', 'smsir'];

        foreach (self::PROVIDERS as $id => $class) {
            $registry->registerDeferred($id, function () use ($container, $class, $id, $apiClient, $templateProviders) {
                $provider = new $class();
                $provider->setApiClient($apiClient);
                if (in_array($id, $templateProviders, true)) {
                    $provider->setCatalogManager($container->get('template.catalog_manager'));
                }
                return $provider;
            });
        }

        // Test gateway: only in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $registry->register($container->get('gateway.test'));
        }

        try {
            do_action('wsms_register_gateways', $registry);
        } catch (\Throwable $e) {
            error_log('[WP-SMS] Gateway registration failed: ' . $e->getMessage());
        }
    }
}
