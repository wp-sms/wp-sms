<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Webhook;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Gateway\Webhook\HttpWebhookGateway;
use WSms\Messaging\Message\Message;

class HttpWebhookGatewayTest extends TestCase
{
    private HttpWebhookGateway $gateway;

    protected function setUp(): void
    {
        $this->gateway = new HttpWebhookGateway();
        $GLOBALS['_test_options'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_options'],
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
    }

    public function testSuccessfulDelivery(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => 200],
            'body'     => 'OK',
        ];

        $message = new Message('webhook', 'https://example.com/hook', '{"data":"test"}');
        $result = $this->gateway->send($message);

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertFalse($result->retryable);
        $this->assertSame(200, $result->meta['http_status']);
    }

    public function testWpErrorIsRetryable(): void
    {
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'Connection timed out');

        $message = new Message('webhook', 'https://example.com/hook', '{"data":"test"}');
        $result = $this->gateway->send($message);

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertSame('Connection timed out', $result->error);
    }

    /**
     * @dataProvider retryableHttpCodesProvider
     */
    public function testServerErrorsAreRetryable(int $code): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => $code],
            'body'     => 'Server error',
        ];

        $message = new Message('webhook', 'https://example.com/hook', '{}');
        $result = $this->gateway->send($message);

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertSame($code, $result->meta['http_status']);
    }

    public static function retryableHttpCodesProvider(): array
    {
        return [
            '500 Internal Server Error' => [500],
            '502 Bad Gateway'           => [502],
            '503 Service Unavailable'   => [503],
            '504 Gateway Timeout'       => [504],
            '429 Too Many Requests'     => [429],
        ];
    }

    /**
     * @dataProvider permanentHttpCodesProvider
     */
    public function testClientErrorsAreNotRetryable(int $code): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => $code],
            'body'     => 'Client error',
        ];

        $message = new Message('webhook', 'https://example.com/hook', '{}');
        $result = $this->gateway->send($message);

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        $this->assertSame($code, $result->meta['http_status']);
    }

    public static function permanentHttpCodesProvider(): array
    {
        return [
            '400 Bad Request'  => [400],
            '401 Unauthorized' => [401],
            '403 Forbidden'    => [403],
            '404 Not Found'    => [404],
            '422 Unprocessable' => [422],
        ];
    }

    public function testDeliveryIdHeaderSentWhenLogIdInMeta(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => 200],
            'body'     => 'OK',
        ];

        $message = new Message('webhook', 'https://example.com/hook', '{"data":"test"}', null, [
            'log_id' => 'log-abc-123',
        ]);
        $this->gateway->send($message);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertArrayHasKey('X-WSMS-Delivery-Id', $args['headers']);
        $this->assertSame('log-abc-123', $args['headers']['X-WSMS-Delivery-Id']);
    }

    public function testDeliveryIdHeaderNotSentWhenLogIdMissing(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => 200],
            'body'     => 'OK',
        ];

        $message = new Message('webhook', 'https://example.com/hook', '{"data":"test"}');
        $this->gateway->send($message);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertArrayNotHasKey('X-WSMS-Delivery-Id', $args['headers']);
    }

    public function testHttp201IsSuccess(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'response' => ['code' => 201],
            'body'     => 'Created',
        ];

        $message = new Message('webhook', 'https://example.com/hook', '{}');
        $result = $this->gateway->send($message);

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
    }
}
