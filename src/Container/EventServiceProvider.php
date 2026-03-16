<?php

namespace WSms\Container;

use WSms\Event\EventDispatcher;

defined('ABSPATH') || exit;

class EventServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('event.dispatcher', fn() => new EventDispatcher());
    }

    public function boot(ServiceContainer $container): void
    {
    }
}
