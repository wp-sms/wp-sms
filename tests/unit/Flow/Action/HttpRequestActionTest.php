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

    public function testOutputSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getOutputSchema();
        $this->assertArrayHasKey('http_status', $schema);
        $this->assertArrayHasKey('body', $schema);
        $this->assertSame('integer', $schema['http_status']['type']);
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
    }

    public function testJsonResponseBodyIsParsed(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => 200],
            'body' => '{"tracking_url":"https://example.com/track/123","status":"shipped"}',
        ];

        $result = $this->action->execute([], [
            'url' => 'https://example.com/api',
            'method' => 'GET',
        ]);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->output['body']);
        $this->assertSame('https://example.com/track/123', $result->output['body']['tracking_url']);
        $this->assertSame('shipped', $result->output['body']['status']);
    }

    public function testNonJsonResponseBodyRemainsString(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => 200],
            'body' => '<html>Not JSON</html>',
        ];

        $result = $this->action->execute([], [
            'url' => 'https://example.com/page',
            'method' => 'GET',
        ]);

        $this->assertTrue($result->success);
        $this->assertIsString($result->output['body']);
        $this->assertSame('<html>Not JSON</html>', $result->output['body']);
    }

    public function testEmptyResponseBodyRemainsString(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => 204],
            'body' => '',
        ];

        $result = $this->action->execute([], [
            'url' => 'https://example.com/api',
            'method' => 'DELETE',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('', $result->output['body']);
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
