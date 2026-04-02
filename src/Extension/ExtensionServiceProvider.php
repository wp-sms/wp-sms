<?php

namespace WSms\Extension;

use WSms\Container\ServiceContainer;
use WSms\Container\ServiceProvider;

defined('ABSPATH') || exit;

class ExtensionServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('extension.registry', fn() => new ExtensionRegistry());
    }

    public function boot(ServiceContainer $container): void
    {
        $registry = $container->get('extension.registry');

        try {
            do_action('wsms_register_extensions', $registry);
        } catch (\Throwable $e) {
            error_log('[WP-SMS] Extension registration failed: ' . $e->getMessage());
        }
    }
}
