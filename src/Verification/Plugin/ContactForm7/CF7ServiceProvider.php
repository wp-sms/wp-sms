<?php

namespace WSms\Verification\Plugin\ContactForm7;

use WSms\Container\ServiceContainer;
use WSms\Container\ServiceProvider;

defined('ABSPATH') || exit;

class CF7ServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        if (!defined('WPCF7_VERSION')) {
            return;
        }

        $container->register('integration.cf7', fn () => new CF7Integration(
            $container->get('verification.service'),
        ));
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        if (!defined('WPCF7_VERSION')) {
            return;
        }

        $cf7Settings = $container->get('auth.settings')->channel('contact_form_7');
        if (empty($cf7Settings['verification_enabled'])) {
            return;
        }

        $container->get('integration.cf7')->registerHooks();
    }
}
