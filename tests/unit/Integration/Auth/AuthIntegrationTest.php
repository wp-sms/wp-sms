<?php

namespace WSms\Tests\Unit\Integration\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Enums\EventType;
use WSms\Event\Events\AuthEvent;
use WSms\Integration\Auth\AuthEventTrigger;
use WSms\Integration\Auth\AuthIntegration;

class AuthIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset WordPress action hooks between tests.
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions']);
    }

    public function testAuthEventCarriesCorrectProperties(): void
    {
        $event = new AuthEvent(
            eventType: EventType::LoginSuccess,
            status: 'success',
            userId: 42,
            meta: ['method' => 'password'],
        );

        $this->assertSame(EventType::LoginSuccess, $event->eventType);
        $this->assertSame('success', $event->status);
        $this->assertSame(42, $event->userId);
        $this->assertSame(['method' => 'password'], $event->meta);
    }

    public function testAuthEventDefaults(): void
    {
        $event = new AuthEvent(
            eventType: EventType::LoginFailure,
            status: 'failure',
        );

        $this->assertNull($event->userId);
        $this->assertSame([], $event->meta);
    }

    public function testGetTriggersReturnsThreeTriggers(): void
    {
        $integration = new AuthIntegration();
        $triggers = $integration->getTriggers();

        $this->assertCount(3, $triggers);
    }

    public function testTriggerIdsAreCorrect(): void
    {
        $integration = new AuthIntegration();
        $triggers = $integration->getTriggers();
        $ids = array_map(fn($t) => $t->getId(), $triggers);

        $this->assertContains('auth.login_success', $ids);
        $this->assertContains('auth.login_failure', $ids);
        $this->assertContains('auth.account_locked', $ids);
    }

    public function testSubscribeRegistersWordPressAction(): void
    {
        $trigger = new AuthEventTrigger(
            'auth.login_success',
            'Login Success',
            [],
            [EventType::LoginSuccess],
        );

        $trigger->subscribe(function () {});

        // Verify add_action was called for 'wsms_auth_event'.
        $this->assertArrayHasKey('wsms_auth_event', $GLOBALS['_test_actions']);
    }

    public function testTriggerCallbackFiresForMatchingEventType(): void
    {
        $trigger = new AuthEventTrigger(
            'auth.login_success',
            'Login Success',
            [],
            [EventType::LoginSuccess],
        );

        $captured = null;
        $trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        // Simulate the WordPress hook firing with a matching event.
        $event = new AuthEvent(EventType::LoginSuccess, 'success', 42, ['method' => 'password']);
        $this->fireAction('wsms_auth_event', $event);

        $this->assertNotNull($captured);
        $this->assertSame(42, $captured['user_id']);
        $this->assertSame('password', $captured['method']);
    }

    public function testTriggerCallbackDoesNotFireForNonMatchingEventType(): void
    {
        $trigger = new AuthEventTrigger(
            'auth.login_success',
            'Login Success',
            [],
            [EventType::LoginSuccess],
        );

        $fired = false;
        $trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        // Fire with a non-matching event type.
        $event = new AuthEvent(EventType::AccountLocked, 'success', 42);
        $this->fireAction('wsms_auth_event', $event);

        $this->assertFalse($fired);
    }

    public function testTriggerCallbackMergesUserIdAndMeta(): void
    {
        $trigger = new AuthEventTrigger(
            'auth.account_locked',
            'Account Locked',
            [],
            [EventType::AccountLocked],
        );

        $captured = null;
        $trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $event = new AuthEvent(EventType::AccountLocked, 'success', 99, ['reason' => 'too_many_failures']);
        $this->fireAction('wsms_auth_event', $event);

        $this->assertSame(99, $captured['user_id']);
        $this->assertSame('too_many_failures', $captured['reason']);
    }

    /**
     * Simulate firing a WordPress action by calling all registered callbacks.
     */
    private function fireAction(string $hook, ...$args): void
    {
        if (!isset($GLOBALS['_test_actions'][$hook])) {
            return;
        }

        foreach ($GLOBALS['_test_actions'][$hook] as $callback) {
            $callback(...$args);
        }
    }
}
