<?php

namespace WSms\Container;

use WSms\Log\FlowLogger;
use WSms\Log\MessageLogger;
use WSms\Log\WpLogger;

defined('ABSPATH') || exit;

class LogServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('log.app', fn() => new WpLogger('wsms'));
        $container->register('log.message', fn() => new MessageLogger());
        $container->register('log.flow', fn($c) => new FlowLogger($c->get('log.app')));
    }

    public function boot(ServiceContainer $container): void
    {
    }
}
