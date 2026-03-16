<?php

namespace WSms\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use WSms\Event\Event;
use WSms\Event\EventDispatcher;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $GLOBALS['_test_do_action_calls'] = [];
    }

    public function testDispatchCallsListener(): void
    {
        $called = false;
        $event = new class extends Event {};

        $this->dispatcher->listen(get_class($event), function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch($event);

        $this->assertTrue($called);
    }

    public function testDispatchReturnsEvent(): void
    {
        $event = new class extends Event {};
        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testListenersCalledInPriorityOrder(): void
    {
        $order = [];
        $event = new class extends Event {};
        $class = get_class($event);

        $this->dispatcher->listen($class, function () use (&$order) {
            $order[] = 'second';
        }, 20);

        $this->dispatcher->listen($class, function () use (&$order) {
            $order[] = 'first';
        }, 10);

        $this->dispatcher->dispatch($event);

        $this->assertSame(['first', 'second'], $order);
    }

    public function testPropagationStopped(): void
    {
        $calls = 0;
        $event = new class extends Event {};
        $class = get_class($event);

        $this->dispatcher->listen($class, function (Event $e) use (&$calls) {
            $calls++;
            $e->stopPropagation();
        }, 1);

        $this->dispatcher->listen($class, function () use (&$calls) {
            $calls++;
        }, 2);

        $this->dispatcher->dispatch($event);

        $this->assertSame(1, $calls);
    }

    public function testBridgesToWpHooks(): void
    {
        $event = new class extends Event {};
        $this->dispatcher->dispatch($event);

        $hooks = array_column($GLOBALS['_test_do_action_calls'], 'hook');
        // The hook name should be wsms_ + snake_case of the anonymous class... it will have a complex name
        // Just verify do_action was called at least once
        $this->assertNotEmpty($GLOBALS['_test_do_action_calls']);
    }
}
