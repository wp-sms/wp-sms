<?php

namespace WSms\Tests\Unit\Telegram;

use PHPUnit\Framework\TestCase;
use WSms\Telegram\TelegramBotClient;

class TelegramBotClientTest extends TestCase
{
    private TelegramBotClient $client;

    protected function setUp(): void
    {
        $this->client = new TelegramBotClient('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
        unset($GLOBALS['_test_wp_remote_post']);
    }

    public function testSendMessageReturnsTrueOnSuccess(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['ok' => true, 'result' => ['message_id' => 1]]),
        ];

        $this->assertTrue($this->client->sendMessage(12345, 'Hello'));
    }

    public function testSendMessageReturnsFalseOnFailure(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['ok' => false, 'description' => 'Bad Request']),
        ];

        $this->assertFalse($this->client->sendMessage(12345, 'Hello'));
    }

    public function testSendMessageReturnsFalseOnWpError(): void
    {
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_error', 'Timeout');

        $this->assertFalse($this->client->sendMessage(12345, 'Hello'));
    }

    public function testSetWebhookReturnsTrueOnSuccess(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['ok' => true, 'result' => true]),
        ];

        $this->assertTrue($this->client->setWebhook('https://example.com/webhook', 'secret'));
    }

    public function testSetWebhookReturnsFalseOnFailure(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['ok' => false]),
        ];

        $this->assertFalse($this->client->setWebhook('https://example.com/webhook', 'secret'));
    }

    public function testDeleteWebhookReturnsTrueOnSuccess(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['ok' => true]),
        ];

        $this->assertTrue($this->client->deleteWebhook());
    }

    public function testGetMeReturnsDataOnSuccess(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode([
                'ok'     => true,
                'result' => [
                    'id'         => 123456,
                    'is_bot'     => true,
                    'first_name' => 'Test Bot',
                    'username'   => 'test_bot',
                ],
            ]),
        ];

        $result = $this->client->getMe();

        $this->assertSame(123456, $result['id']);
        $this->assertSame('test_bot', $result['username']);
    }

    public function testGetMeReturnsNullOnFailure(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['ok' => false]),
        ];

        $this->assertNull($this->client->getMe());
    }

    public function testGetMeReturnsNullOnWpError(): void
    {
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_error', 'DNS resolution failed');

        $this->assertNull($this->client->getMe());
    }
}
