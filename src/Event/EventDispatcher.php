<?php

namespace WSms\Event;

use WSms\Event\Contracts\EventDispatcherInterface;

defined('ABSPATH') || exit;

class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array{callable, int}[]> */
    private array $listeners = [];

    /** @var array<string, true> */
    private array $sorted = [];

    public function dispatch(object $event): object
    {
        $class = get_class($event);

        if (!isset($this->sorted[$class]) && isset($this->listeners[$class])) {
            usort($this->listeners[$class], fn($a, $b) => $a[1] <=> $b[1]);
            $this->sorted[$class] = true;
        }

        foreach ($this->listeners[$class] ?? [] as [$listener, $priority]) {
            if ($event instanceof Event && $event->isPropagationStopped()) {
                break;
            }
            $listener($event);
        }

        // Bridge to WordPress hooks for external extensibility
        $hookName = 'wsms_' . $this->classToHookName($class);
        do_action($hookName, $event);

        return $event;
    }

    public function listen(string $eventClass, callable $listener, int $priority = 10): void
    {
        $this->listeners[$eventClass][] = [$listener, $priority];
        unset($this->sorted[$eventClass]);
    }

    private function classToHookName(string $class): string
    {
        $short = substr($class, strrpos($class, '\\') + 1);
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $short));
    }
}
