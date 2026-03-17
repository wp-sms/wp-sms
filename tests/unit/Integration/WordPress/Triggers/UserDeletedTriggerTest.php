<?php

namespace WSms\Tests\Unit\Integration\WordPress\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Triggers\UserDeletedTrigger;

class UserDeletedTriggerTest extends TestCase
{
    private UserDeletedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new UserDeletedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_userdata']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('wordpress.user_deleted', $this->trigger->getId());
        $this->assertSame('User Deleted', $this->trigger->getName());
        $this->assertSame('WordPress', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('user_id', $schema);
        $this->assertArrayHasKey('user', $schema);
        $this->assertArrayHasKey('reassign_to', $schema);
    }

    public function testSubscribeRegistersDeleteUserHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('delete_user', $GLOBALS['_test_actions']);
    }

    public function testProducesCorrectPayloadWithUser(): void
    {
        $user = new \WP_User(42);
        $user->user_email = 'deleted@example.com';
        $user->user_login = 'deleteduser';
        $GLOBALS['_test_userdata'] = $user;

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('delete_user', 42, 1);

        $this->assertNotNull($captured);
        $this->assertSame(42, $captured['user_id']);
        $this->assertSame('deleted@example.com', $captured['user']['email']);
        $this->assertSame(1, $captured['reassign_to']);
    }

    public function testProducesPayloadWithNullReassign(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('delete_user', 42, null);

        $this->assertNotNull($captured);
        $this->assertSame(42, $captured['user_id']);
        $this->assertSame([], $captured['user']);
        $this->assertNull($captured['reassign_to']);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
