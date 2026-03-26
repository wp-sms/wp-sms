<?php

namespace WSms\Container;

use WSms\MessagingButton\DisplayRuleEvaluator;
use WSms\MessagingButton\MessageHandler;
use WSms\MessagingButton\MessagingButtonRenderer;
use WSms\MessagingButton\MessagingButtonSettings;
use WSms\MessagingButton\MessagingButtonTrigger;

defined('ABSPATH') || exit;

class MessagingButtonServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('messaging_button.settings', fn() => new MessagingButtonSettings());

        $container->register('messaging_button.display_rules', fn() => new DisplayRuleEvaluator());

        $container->register('messaging_button.renderer', fn($c) => new MessagingButtonRenderer(
            $c->get('messaging_button.settings'),
            $c->get('messaging_button.display_rules'),
            $c->get('phone_restriction.settings'),
            $c->get('branding.repository'),
        ));

        $container->register('messaging_button.handler', fn($c) => new MessageHandler(
            $c->get('messaging_button.settings'),
            $c->get('contact.repository'),
            $c->get('contact.list_repository'),
            $c->get('message.dispatcher'),
        ));

        $container->register('messaging_button.trigger', fn() => new MessagingButtonTrigger());
    }

    public function boot(ServiceContainer $container): void
    {
        // Skip all hooks if the messaging button feature is disabled.
        if (!$container->get('messaging_button.settings')->isEnabled()) {
            return;
        }

        // Defer renderer initialization to template_redirect so the
        // renderer (+ settings + display-rule evaluator) is only
        // instantiated on actual frontend page loads.
        if (!is_admin()) {
            add_action('template_redirect', function () use ($container) {
                $container->get('messaging_button.renderer')->init();
            });
        }

        // Register flow trigger
        add_action('init', function () use ($container) {
            if ($container->has('flow.triggers')) {
                $container->get('flow.triggers')->register(
                    $container->get('messaging_button.trigger')
                );
            }
        }, 15);
    }
}
