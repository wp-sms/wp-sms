<?php

namespace WSms\Tests\Unit\Integration\WordPress\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Actions\CreatePostAction;

class CreatePostActionTest extends TestCase
{
    private CreatePostAction $action;

    protected function setUp(): void
    {
        $this->action = new CreatePostAction();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_insert_post_result'], $GLOBALS['_test_wp_insert_post_data']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('create_post', $this->action->getId());
        $this->assertSame('Create Post', $this->action->getName());
        $this->assertSame('WordPress', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('post_title', $schema);
        $this->assertArrayHasKey('post_content', $schema);
        $this->assertArrayHasKey('post_type', $schema);
        $this->assertArrayHasKey('post_status', $schema);
        $this->assertTrue($schema['post_title']['required']);
        $this->assertContains('draft', $schema['post_status']['enum']);
    }

    public function testExecuteSuccess(): void
    {
        $GLOBALS['_test_wp_insert_post_result'] = 99;

        $result = $this->action->execute([], [
            'post_title' => 'Test Post',
            'post_content' => 'Content here',
            'post_type' => 'post',
            'post_status' => 'draft',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(99, $result->output['post_id']);
        $this->assertSame('Test Post', $GLOBALS['_test_wp_insert_post_data']['post_title']);
    }

    public function testExecuteDefaultValues(): void
    {
        $GLOBALS['_test_wp_insert_post_result'] = 1;

        $this->action->execute([], ['post_title' => 'Minimal']);

        $this->assertSame('post', $GLOBALS['_test_wp_insert_post_data']['post_type']);
        $this->assertSame('draft', $GLOBALS['_test_wp_insert_post_data']['post_status']);
    }

    public function testExecuteFailsWithoutTitle(): void
    {
        $result = $this->action->execute([], ['post_title' => '']);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('post_title is required', $result->error);
    }

    public function testExecuteFailsOnWpError(): void
    {
        $GLOBALS['_test_wp_insert_post_result'] = new \WP_Error('insert_fail', 'Could not insert');

        $result = $this->action->execute([], ['post_title' => 'Test']);
        $this->assertFalse($result->success);
        $this->assertSame('Could not insert', $result->error);
    }
}
