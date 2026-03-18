<?php

namespace WSms\Tests\Unit\Flow\Action;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Action\HttpRequestAction;
use WSms\Flow\Contracts\AbstractAction;

class HttpRequestActionTest extends TestCase
{
    private HttpRequestAction $action;

    protected function setUp(): void
    {
        $this->action = new HttpRequestAction();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_post']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('http_request', $this->action->getId());
        $this->assertSame('HTTP Request', $this->action->getName());
        $this->assertSame('WSMS', $this->action->getGroup());
        $this->assertInstanceOf(AbstractAction::class, $this->action);
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('url', $schema);
        $this->assertArrayHasKey('method', $schema);
        $this->assertArrayHasKey('headers', $schema);
        $this->assertArrayHasKey('body', $schema);
        $this->assertSame('url', $schema['url']['format']);
        $this->assertArrayHasKey('example', $schema['url']);
    }

    public function testExecuteSuccessReturnsStatusAndBody(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => 200],
            'body' => '{"ok":true}',
        ];

        $result = $this->action->execute([], [
            'url' => 'https://example.com/api',
            'method' => 'POST',
            'body' => '{"test":1}',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->output['http_status']);
        $this->assertSame('{"ok":true}', $result->output['body']);
    }

    public function testExecuteFailureOnWpError(): void
    {
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('timeout', 'Connection timed out');

        $result = $this->action->execute([], ['url' => 'https://example.com']);

        $this->assertFalse($result->success);
        $this->assertSame('Connection timed out', $result->error);
    }

    public function testGetPlaceholdersReturnsBodyForAnyTrigger(): void
    {
        $placeholders = $this->action->getPlaceholders('woocommerce.order_created');
        $this->assertArrayHasKey('body', $placeholders);
        $this->assertStringContainsString('{{_trigger_type}}', $placeholders['body']);
    }
}
