<?php

namespace WSms\Tests\Unit\Integration\WordPress\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Triggers\UserRoleChangedTrigger;

class UserRoleChangedTriggerTest extends TestCase
{
    private UserRoleChangedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new UserRoleChangedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_userdata'], $GLOBALS['_test_user_meta']);
        $GLOBALS['_test_user_meta'] = [];
    }

    public function testMetadata(): void
    {
        $this->assertSame('wordpress.user_role_changed', $this->trigger->getId());
        $this->assertSame('User Role Changed', $this->trigger->getName());
        $this->assertSame('WordPress', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('user_id', $schema);
        $this->assertArrayHasKey('new_role', $schema);
        $this->assertArrayHasKey('old_role', $schema);
        $this->assertArrayHasKey('user', $schema);
    }

    public function testSubscribeRegistersSetUserRoleHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('set_user_role', $GLOBALS['_test_actions']);
    }

    public function testProducesCorrectPayload(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'test@example.com';
        $user->user_login = 'testuser';
        $user->display_name = 'Test User';
        $user->first_name = 'Test';
        $user->last_name = 'User';
        $user->roles = ['editor'];
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][42]['wsms_phone'] = '+1234567890';

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('set_user_role', 42, 'editor', ['subscriber']);

        $this->assertNotNull($captured);
        $this->assertSame(42, $captured['user_id']);
        $this->assertSame('editor', $captured['new_role']);
        $this->assertSame('subscriber', $captured['old_role']);
        $this->assertSame('test@example.com', $captured['user']['email']);
        $this->assertSame('+1234567890', $captured['user']['phone']);
        $this->assertSame('Test', $captured['user']['first_name']);
        $this->assertSame('User', $captured['user']['last_name']);
        $this->assertSame(['editor'], $captured['user']['roles']);
    }

    public function testHandlesEmptyOldRoles(): void
    {
        $GLOBALS['_test_userdata'] = new \WP_User(1);

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('set_user_role', 1, 'admin', []);

        $this->assertSame('', $captured['old_role']);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
