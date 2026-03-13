<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Mfa\Channels\TelegramChannel;
use WSms\Rest\TelegramController;

class TelegramControllerTest extends TestCase
{
    private TelegramController $controller;
    private MockObject&TelegramChannel $telegramChannel;

    protected function setUp(): void
    {
        $this->telegramChannel = $this->createMock(TelegramChannel::class);
        $this->controller = new TelegramController($this->telegramChannel);

        $GLOBALS['_test_options'] = [
            'wsms_auth_settings' => [
                'telegram' => [
                    'bot_token'      => '123:ABC',
                    'webhook_secret' => 'valid-secret-token',
                ],
            ],
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options']);
    }

    public function testWebhookRejectsInvalidSecret(): void
    {
        $request = new \WP_REST_Request();
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret');

        $response = $this->controller->handleWebhook($request);

        $this->assertSame(403, $response->get_status());
    }

    public function testWebhookRejectsMissingSecret(): void
    {
        $request = new \WP_REST_Request();

        $response = $this->controller->handleWebhook($request);

        $this->assertSame(403, $response->get_status());
    }

    public function testWebhookIgnoresNonStartMessages(): void
    {
        $request = new \WP_REST_Request();
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', 'valid-secret-token');
        $request->set_body(json_encode([
            'message' => [
                'text' => 'Hello!',
                'chat' => ['id' => 12345],
                'from' => ['username' => 'testuser'],
            ],
        ]));

        $this->telegramChannel->expects($this->never())->method('completeLinking');

        $response = $this->controller->handleWebhook($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testWebhookProcessesStartCommand(): void
    {
        $token = str_repeat('ab', 16); // 32 hex chars.

        $request = new \WP_REST_Request();
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', 'valid-secret-token');
        $request->set_body(json_encode([
            'message' => [
                'text' => "/start {$token}",
                'chat' => ['id' => 99999],
                'from' => ['username' => 'newuser'],
            ],
        ]));

        $this->telegramChannel->expects($this->once())
            ->method('completeLinking')
            ->with($token, 99999, 'newuser')
            ->willReturn(true);

        $response = $this->controller->handleWebhook($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testWebhookIgnoresInvalidStartTokenFormat(): void
    {
        $request = new \WP_REST_Request();
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', 'valid-secret-token');
        $request->set_body(json_encode([
            'message' => [
                'text' => '/start invalid!token',
                'chat' => ['id' => 12345],
                'from' => ['username' => 'testuser'],
            ],
        ]));

        $this->telegramChannel->expects($this->never())->method('completeLinking');

        $response = $this->controller->handleWebhook($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testWebhookHandlesEmptyMessage(): void
    {
        $request = new \WP_REST_Request();
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', 'valid-secret-token');
        $request->set_body(json_encode(['update_id' => 123])); // No message.

        $response = $this->controller->handleWebhook($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testSetupRejectsMissingBotToken(): void
    {
        $request = new \WP_REST_Request();

        $response = $this->controller->handleSetup($request);

        $this->assertSame(400, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('missing_bot_token', $data['error']);
    }

    public function testSetupRejectsInvalidBotToken(): void
    {
        $request = new \WP_REST_Request();
        $request->set_param('bot_token', 'invalid-token');

        // Stub wp_remote_post to simulate Telegram returning an error.
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['ok' => false, 'description' => 'Unauthorized']),
        ];

        $response = $this->controller->handleSetup($request);

        $this->assertSame(400, $response->get_status());
        $data = $response->get_data();
        $this->assertSame('invalid_bot_token', $data['error']);
    }
}
