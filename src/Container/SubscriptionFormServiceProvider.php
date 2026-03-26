<?php

namespace WSms\Container;

use WSms\SubscriptionForm\SubscriptionFormRenderer;
use WSms\SubscriptionForm\SubscriptionFormRepository;
use WSms\SubscriptionForm\SubscriptionHandler;

defined('ABSPATH') || exit;

class SubscriptionFormServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('subscription_form.repository', fn() => new SubscriptionFormRepository());

        $container->register('subscription_form.handler', fn($c) => new SubscriptionHandler(
            $c->get('contact.repository'),
            $c->get('contact.list_repository'),
            $c->get('verification.service'),
        ));

        $container->register('subscription_form.renderer', fn($c) => new SubscriptionFormRenderer(
            $c->get('subscription_form.repository'),
            $c->get('phone_restriction.settings'),
            $c->get('branding.repository'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        // Only register shortcodes/blocks if at least one form exists.
        add_action('init', function () use ($container) {
            if (!empty(get_option('wsms_subscription_forms', []))) {
                $container->get('subscription_form.renderer')->init();
            }
        });

        // Sync auth verification status to linked contacts.
        add_action('wsms_identifier_verified', function (string $channel, string $identifier, ?int $userId) use ($container) {
            if (!$userId) {
                return;
            }

            /** @var \WSms\Contact\ContactRepository $contacts */
            $contacts = $container->get('contact.repository');
            $contact = $contacts->findByWpUser($userId);

            if (!$contact) {
                return;
            }

            $field = $channel === 'email' ? 'email_verified' : 'phone_verified';
            $contacts->update($contact['id'], [$field => 1]);
        }, 10, 3);
    }
}
