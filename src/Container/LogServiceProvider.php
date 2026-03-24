<?php

namespace WSms\Container;

use WSms\Database\Connection;
use WSms\Log\FlowLogger;
use WSms\Log\MessageLogger;
use WSms\Log\WpLogger;

defined('ABSPATH') || exit;

class LogServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('log.app', fn() => new WpLogger('wsms'));
        $container->register('log.message', fn($c) => new MessageLogger($c->get(Connection::class)));
        $container->register('log.flow', fn($c) => new FlowLogger($c->get('log.app'), $c->get(Connection::class)));
    }

    public function boot(ServiceContainer $container): void
    {
    }
}
