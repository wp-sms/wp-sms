<?php

namespace WSms\Verification\Plugin\WPForms;

use WSms\Container\ServiceContainer;
use WSms\Container\ServiceProvider;

defined('ABSPATH') || exit;

class WPFormsServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        if (!defined('WPFORMS_VERSION')) {
            return;
        }

        $container->register('integration.wpforms.verify_email', fn () => new VerifyEmailField(
            $container->get('verification.service'),
        ));

        $container->register('integration.wpforms.verify_phone', fn () => new VerifyPhoneField(
            $container->get('verification.service'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        if (!defined('WPFORMS_VERSION')) {
            return;
        }

        // WPForms field classes self-register on construction via parent::__construct().
        // Defer to 'init' so translations are available (wpforms_loaded fires during plugins_loaded).
        add_action('init', function () use ($container) {
            if (!class_exists('WPForms_Field')) {
                return;
            }
            $container->get('integration.wpforms.verify_email');
            $container->get('integration.wpforms.verify_phone');
        });
    }
}
