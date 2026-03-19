<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\Message;
use WSms\Mfa\Channels\TelegramChannel;
use WSms\Rest\TelegramController;

class TelegramControllerTest extends TestCase
{
    private TelegramController $controller;
    private MockObject&TelegramChannel $telegramChannel;
    private MockObject&MessageDispatcher $messageDispatcher;

    protected function setUp(): void
    {
        $this->telegramChannel = $this->createMock(TelegramChannel::class);
        $this->messageDispatcher = $this->createMock(MessageDispatcher::class);
        $this->messageDispatcher->method('sendImmediate')->willReturn(DeliveryResult::sent());
        $this->controller = new TelegramController($this->telegramChannel, $this->messageDispatcher);

        $GLOBALS['_test_options'] = [
            'wsms_auth_settings' => [
                'telegram' => [
                    'bot_token'      => '123:ABC',
                    'webhook_secret' => 'valid-secret-token',
                ],
            ],
        ];
        $GLOBALS['_test_do_action_calls'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options'], $GLOBALS['_test_do_action_calls']);
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

    public function testWebhookSendsConfirmationViaDispatcher(): void
    {
        $token = str_repeat('ab', 16);

        $request = new \WP_REST_Request();
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', 'valid-secret-token');
        $request->set_body(json_encode([
            'message' => [
                'text' => "/start {$token}",
                'chat' => ['id' => 99999],
                'from' => ['username' => 'newuser'],
            ],
        ]));

        $this->telegramChannel->method('completeLinking')->willReturn(true);

        $this->messageDispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with($this->callback(fn($msg) => $msg instanceof Message
                && $msg->getRecipient() === '99999'
            ))
            ->willReturn(DeliveryResult::sent());

        $this->controller->handleWebhook($request);
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

    public function testWebhookDispatchesMessageAction(): void
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

        $this->controller->handleWebhook($request);

        $hooks = array_column($GLOBALS['_test_do_action_calls'], 'hook');
        $this->assertContains('wsms_telegram_message', $hooks);
        $this->assertContains('wsms_telegram_update', $hooks);
    }

    public function testWebhookDispatchesCallbackQueryAction(): void
    {
        $request = new \WP_REST_Request();
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', 'valid-secret-token');
        $request->set_body(json_encode([
            'callback_query' => [
                'id'   => 'cb1',
                'from' => ['id' => 99],
                'data' => 'test',
                'message' => ['message_id' => 1, 'chat' => ['id' => 123]],
            ],
        ]));

        $this->controller->handleWebhook($request);

        $hooks = array_column($GLOBALS['_test_do_action_calls'], 'hook');
        $this->assertContains('wsms_telegram_callback_query', $hooks);
    }

    public function testWebhookDispatchesChannelPostAction(): void
    {
        $request = new \WP_REST_Request();
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', 'valid-secret-token');
        $request->set_body(json_encode([
            'channel_post' => [
                'chat' => ['id' => -100123, 'type' => 'channel'],
                'text' => 'Announcement',
                'message_id' => 5,
            ],
        ]));

        $this->controller->handleWebhook($request);

        $hooks = array_column($GLOBALS['_test_do_action_calls'], 'hook');
        $this->assertContains('wsms_telegram_channel_post', $hooks);
    }

    public function testWebhookAcceptsIntegrationConfigSecret(): void
    {
        // Only integration config secret, no auth settings secret
        $GLOBALS['_test_options'] = [
            'wsms_auth_settings' => [],
            'wsms_integration_configs' => [
                'telegram' => [
                    'credentials' => ['webhook_secret' => 'apps-secret'],
                ],
            ],
        ];

        $request = new \WP_REST_Request();
        $request->set_header('X-Telegram-Bot-Api-Secret-Token', 'apps-secret');
        $request->set_body(json_encode([
            'message' => [
                'text' => 'Hello!',
                'chat' => ['id' => 12345],
                'from' => ['username' => 'testuser'],
            ],
        ]));

        $response = $this->controller->handleWebhook($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testMfaStartReturnsEarlyWithoutDispatch(): void
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

        $this->telegramChannel->method('completeLinking')->willReturn(true);

        $this->controller->handleWebhook($request);

        // MFA start should NOT dispatch wsms_telegram_message
        $hooks = array_column($GLOBALS['_test_do_action_calls'], 'hook');
        $this->assertNotContains('wsms_telegram_message', $hooks);
    }
}
