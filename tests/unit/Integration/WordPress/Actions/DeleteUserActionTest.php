<?php

namespace WSms\Tests\Unit\Integration\WordPress\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Actions\DeleteUserAction;

class DeleteUserActionTest extends TestCase
{
    private DeleteUserAction $action;

    protected function setUp(): void
    {
        $this->action = new DeleteUserAction();
        $GLOBALS['_test_deleted_users'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_userdata'], $GLOBALS['_test_deleted_users']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('delete_user', $this->action->getId());
        $this->assertSame('Delete User', $this->action->getName());
        $this->assertSame('WordPress', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('user_id', $schema);
        $this->assertArrayHasKey('reassign_to', $schema);
        $this->assertTrue($schema['user_id']['required']);
    }

    public function testExecuteSuccess(): void
    {
        $GLOBALS['_test_userdata'] = new \WP_User(42);

        $result = $this->action->execute([], ['user_id' => '42', 'reassign_to' => '1']);

        $this->assertTrue($result->success);
        $this->assertSame(42, $result->output['user_id']);
        $this->assertSame(1, $result->output['reassign_to']);
        $this->assertContains(42, $GLOBALS['_test_deleted_users']);
    }

    public function testExecuteWithoutReassign(): void
    {
        $GLOBALS['_test_userdata'] = new \WP_User(42);

        $result = $this->action->execute([], ['user_id' => '42']);

        $this->assertTrue($result->success);
        $this->assertNull($result->output['reassign_to']);
    }

    public function testExecuteFailsWithoutUserId(): void
    {
        $result = $this->action->execute([], ['user_id' => '0']);
        $this->assertFalse($result->success);
    }

    public function testExecuteFailsIfUserNotFound(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $result = $this->action->execute([], ['user_id' => '999']);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('User not found', $result->error);
    }
}
