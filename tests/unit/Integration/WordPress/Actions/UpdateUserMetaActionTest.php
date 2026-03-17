<?php

namespace WSms\Tests\Unit\Integration\WordPress\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\AbstractAction;
use WSms\Integration\WordPress\Actions\UpdateUserMetaAction;

class UpdateUserMetaActionTest extends TestCase
{
    private UpdateUserMetaAction $action;

    protected function setUp(): void
    {
        $this->action = new UpdateUserMetaAction();
        $GLOBALS['_test_user_meta'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_user_meta'] = [];
    }

    public function testMetadata(): void
    {
        $this->assertSame('update_user_meta', $this->action->getId());
        $this->assertSame('Update User Meta', $this->action->getName());
        $this->assertSame('WordPress', $this->action->getGroup());
        $this->assertInstanceOf(AbstractAction::class, $this->action);
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('user_id', $schema);
        $this->assertArrayHasKey('meta_key', $schema);
        $this->assertArrayHasKey('value', $schema);
        $this->assertTrue($schema['user_id']['required']);
        $this->assertTrue($schema['meta_key']['required']);
        $this->assertArrayHasKey('example', $schema['meta_key']);
    }

    public function testExecuteSuccess(): void
    {
        $result = $this->action->execute([], [
            'user_id' => '42',
            'meta_key' => 'my_key',
            'value' => 'my_value',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(42, $result->output['user_id']);
        $this->assertSame('my_key', $result->output['meta_key']);
        $this->assertSame('my_value', $GLOBALS['_test_user_meta'][42]['my_key']);
    }

    public function testExecuteFailsWithoutUserId(): void
    {
        $result = $this->action->execute([], [
            'user_id' => '0',
            'meta_key' => 'my_key',
            'value' => 'v',
        ]);

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
    }

    public function testExecuteFailsWithoutMetaKey(): void
    {
        $result = $this->action->execute([], [
            'user_id' => '42',
            'meta_key' => '',
            'value' => 'v',
        ]);

        $this->assertFalse($result->success);
    }
}
