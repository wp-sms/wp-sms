<?php

namespace WSms\Container;

use WSms\Integration\Auth\AuthIntegration;
use WSms\Integration\IntegrationRegistry;
use WSms\Integration\Webhook\WebhookIntegration;
use WSms\Integration\WooCommerce\WooCommerceIntegration;
use WSms\Integration\WordPress\WordPressIntegration;

defined('ABSPATH') || exit;

class IntegrationServiceProvider implements ServiceProvider
{
    /** @var class-string[] */
    private array $integrations = [
        WordPressIntegration::class,
        AuthIntegration::class,
        WebhookIntegration::class,
        WooCommerceIntegration::class,
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

            foreach ($this->integrations as $integrationClass) {
                $integration = new $integrationClass();

                if (!$integration->isAvailable()) {
                    continue;
                }

                $registry->register($integration);

                foreach ($integration->getTriggers() as $trigger) {
                    $triggers->register($trigger);
                }
                foreach ($integration->getActions() as $action) {
                    $actions->register($action);
                }

                $integration->boot();
            }

            do_action('wsms_register_integrations', $registry, $triggers, $actions);
        });
    }
}
