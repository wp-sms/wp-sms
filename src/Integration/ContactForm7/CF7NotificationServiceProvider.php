<?php

namespace WSms\Integration\ContactForm7;

use WSms\Container\ServiceContainer;
use WSms\Container\ServiceProvider;

defined('ABSPATH') || exit;

class CF7NotificationServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        if (!defined('WPCF7_VERSION')) {
            return;
        }

        $container->register('integration.cf7.editor_panel', fn($c) => new EditorPanel(
            $c->get('gateway.registry'),
        ));

        $container->register('integration.cf7.notification_sender', fn($c) => new NotificationSender(
            $c->get('message.dispatcher'),
            $c->get('gateway.registry'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        if (!defined('WPCF7_VERSION')) {
            return;
        }

        $cf7Settings = $container->get('auth.settings')->channel('contact_form_7');
        if (empty($cf7Settings['notifications_enabled'])) {
            return;
        }

        $container->get('integration.cf7.editor_panel')->registerHooks();
        $container->get('integration.cf7.notification_sender')->registerHooks();
    }
}
