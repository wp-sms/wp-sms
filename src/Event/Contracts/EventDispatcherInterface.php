<?php

namespace WSms\Event\Contracts;

defined('ABSPATH') || exit;

interface EventDispatcherInterface
{
    public function dispatch(object $event): object;

    public function listen(string $eventClass, callable $listener, int $priority = 10): void;
}
