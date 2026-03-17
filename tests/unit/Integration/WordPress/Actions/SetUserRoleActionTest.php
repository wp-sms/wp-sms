<?php

namespace WSms\Tests\Unit\Integration\WordPress\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Actions\SetUserRoleAction;

class SetUserRoleActionTest extends TestCase
{
    private SetUserRoleAction $action;

    protected function setUp(): void
    {
        $this->action = new SetUserRoleAction();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_userdata'], $GLOBALS['_test_wp_update_user_result']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('set_user_role', $this->action->getId());
        $this->assertSame('Set User Role', $this->action->getName());
        $this->assertSame('WordPress', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('user_id', $schema);
        $this->assertArrayHasKey('role', $schema);
        $this->assertTrue($schema['role']['required']);
        $this->assertArrayHasKey('example', $schema['role']);
    }

    public function testExecuteSuccess(): void
    {
        $GLOBALS['_test_userdata'] = new \WP_User(42);

        $result = $this->action->execute([], ['user_id' => '42', 'role' => 'editor']);

        $this->assertTrue($result->success);
        $this->assertSame(42, $result->output['user_id']);
        $this->assertSame('editor', $result->output['role']);
    }

    public function testExecuteFailsWithMissingParams(): void
    {
        $result = $this->action->execute([], ['user_id' => '0', 'role' => '']);
        $this->assertFalse($result->success);
    }

    public function testExecuteFailsIfUserNotFound(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $result = $this->action->execute([], ['user_id' => '999', 'role' => 'editor']);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('User not found', $result->error);
    }
}
