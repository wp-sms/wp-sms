<?php

namespace WSms\Container;

use WSms\Log\WpLogger;
use WSms\Queue\ActionSchedulerQueue;
use WSms\Queue\JobProcessor;

defined('ABSPATH') || exit;

class QueueServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('queue', fn() => new ActionSchedulerQueue());
        $container->register('queue.processor', function () use ($container) {
            return new JobProcessor($container->get('log.app'));
        });
    }

    public function boot(ServiceContainer $container): void
    {
        add_action('wsms_process_job', function ($args) use ($container) {
            $container->get('queue.processor')->process($args);
        });
    }
}
