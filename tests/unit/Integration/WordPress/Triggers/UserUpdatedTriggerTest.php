<?php

namespace WSms\Tests\Unit\Integration\WordPress\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Triggers\UserUpdatedTrigger;

class UserUpdatedTriggerTest extends TestCase
{
    private UserUpdatedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new UserUpdatedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_userdata']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('wordpress.user_updated', $this->trigger->getId());
        $this->assertSame('User Updated', $this->trigger->getName());
        $this->assertSame('WordPress', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('user_id', $schema);
        $this->assertArrayHasKey('user', $schema);
        $this->assertArrayHasKey('changed_fields', $schema);
        $this->assertSame('array', $schema['changed_fields']['type']);
    }

    public function testSubscribeRegistersProfileUpdateHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('profile_update', $GLOBALS['_test_actions']);
    }

    public function testDetectsChangedFields(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'new@example.com';
        $user->user_login = 'testuser';
        $user->display_name = 'New Name';
        $user->first_name = 'Test';
        $user->last_name = 'User';
        $user->roles = ['editor'];
        $GLOBALS['_test_userdata'] = $user;

        $oldUser = (object) [
            'user_email' => 'old@example.com',
            'display_name' => 'Old Name',
            'user_login' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
        ];

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('profile_update', 42, $oldUser);

        $this->assertNotNull($captured);
        $this->assertSame(42, $captured['user_id']);
        $this->assertContains('user_email', $captured['changed_fields']);
        $this->assertContains('display_name', $captured['changed_fields']);
        $this->assertNotContains('user_login', $captured['changed_fields']);
    }

    public function testDoesNotFireIfUserNotFound(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('profile_update', 999, null);
        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
