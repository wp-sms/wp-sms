<?php

namespace WSms\Container;

use WSms\Access\AccessManager;

defined('ABSPATH') || exit;

class AccessServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('access.manager', fn() => new AccessManager());
    }

    public function boot(ServiceContainer $container): void
    {
        $container->get('access.manager')->registerMetaCap();
    }
}
