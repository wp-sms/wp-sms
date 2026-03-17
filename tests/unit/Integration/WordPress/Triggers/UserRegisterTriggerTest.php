<?php

namespace WSms\Tests\Unit\Integration\WordPress\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\WordPress\Triggers\UserRegisterTrigger;

class UserRegisterTriggerTest extends TestCase
{
    private UserRegisterTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new UserRegisterTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_userdata']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('wordpress.user_register', $this->trigger->getId());
        $this->assertSame('User Registered', $this->trigger->getName());
        $this->assertSame('WordPress', $this->trigger->getGroup());
        $this->assertInstanceOf(AbstractTrigger::class, $this->trigger);
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('user_id', $schema);
        $this->assertArrayHasKey('user', $schema);
        $this->assertSame('integer', $schema['user_id']['type']);
        $this->assertArrayHasKey('example', $schema['user_id']);
        $this->assertSame('object', $schema['user']['type']);
    }

    public function testSubscribeRegistersUserRegisterHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('user_register', $GLOBALS['_test_actions']);
    }

    public function testSubscribeProducesCorrectPayload(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'test@example.com';
        $user->user_login = 'testuser';
        $user->display_name = 'Test User';
        $user->roles = ['subscriber'];
        $GLOBALS['_test_userdata'] = $user;

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('user_register', 42);

        $this->assertNotNull($captured);
        $this->assertSame(42, $captured['user_id']);
        $this->assertSame('test@example.com', $captured['user']['email']);
        $this->assertSame('testuser', $captured['user']['login']);
        $this->assertSame(['subscriber'], $captured['user']['roles']);
    }

    public function testSubscribeDoesNotFireIfUserNotFound(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('user_register', 999);
        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
