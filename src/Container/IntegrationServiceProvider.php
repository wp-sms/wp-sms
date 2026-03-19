<?php

namespace WSms\Container;

use WSms\Integration\Auth\AuthIntegration;
use WSms\Integration\ContactForm7\ContactForm7Integration;
use WSms\Integration\IntegrationRegistry;
use WSms\Integration\Webhook\WebhookIntegration;
use WSms\Integration\WooCommerce\WooCommerceIntegration;
use WSms\Integration\WordPress\WordPressIntegration;
use WSms\Integration\Telegram\TelegramIntegration;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\WpSms\WpSmsIntegration;
use WSms\Flow\Trigger\TriggerRegistry;
use WSms\Flow\Action\ActionRegistry;

defined('ABSPATH') || exit;

class IntegrationServiceProvider implements ServiceProvider
{
    /** @var class-string[] */
    private array $integrations = [
        WordPressIntegration::class,
        AuthIntegration::class,
        WebhookIntegration::class,
        WooCommerceIntegration::class,
        ContactForm7Integration::class,
    ];

    public function register(ServiceContainer $container): void
    {
        $container->register('integration.registry', fn() => new IntegrationRegistry());
    }

    public function boot(ServiceContainer $container): void
    {
        add_action('init', function () use ($container) {
            $registry = $container->get('integration.registry');
            $triggers = $container->get('flow.triggers');
            $actions = $container->get('flow.actions');

            // Register WpSmsIntegration first (needs constructor injection)
            $this->registerIntegration(
                new WpSmsIntegration(
                    $container->get('message.dispatcher'),
                    $container->get('gateway.registry'),
                    $container->get('event.dispatcher'),
                ),
                $registry,
                $triggers,
                $actions,
            );

            // Register TelegramIntegration (needs constructor injection)
            $this->registerIntegration(
                new TelegramIntegration($container->get('telegram.bot_client')),
                $registry,
                $triggers,
                $actions,
            );

            // Simple integrations (no constructor injection)
            foreach ($this->integrations as $integrationClass) {
                $integration = new $integrationClass();

                if (!$integration->isAvailable()) {
                    continue;
                }

                $this->registerIntegration($integration, $registry, $triggers, $actions);
            }

            do_action('wsms_register_integrations', $registry, $triggers, $actions);
        });
    }

    private function registerIntegration(
        IntegrationInterface $integration,
        IntegrationRegistry $registry,
        TriggerRegistry $triggers,
        ActionRegistry $actions,
    ): void {
        $registry->register($integration);

        if ($integration->isConnected()) {
            foreach ($integration->getTriggers() as $trigger) {
                $triggers->register($trigger);
            }
            foreach ($integration->getActions() as $action) {
                $actions->register($action);
            }
        }

        $integration->boot();
    }
}
