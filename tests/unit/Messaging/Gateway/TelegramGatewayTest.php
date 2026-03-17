<?php

namespace WSms\Tests\Unit\Messaging\Gateway;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Messaging\Gateway\Telegram\TelegramGateway;
use WSms\Messaging\Message\Message;
use WSms\Telegram\TelegramBotClient;

class TelegramGatewayTest extends TestCase
{
    private MockObject&TelegramBotClient $botClient;
    private TelegramGateway $gateway;

    protected function setUp(): void
    {
        $this->botClient = $this->createMock(TelegramBotClient::class);
        $this->gateway = new TelegramGateway($this->botClient);

        $GLOBALS['_test_options'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options']);
    }

    public function testGetIdReturnsTelegram(): void
    {
        $this->assertSame('telegram', $this->gateway->getId());
    }

    public function testGetSupportedChannelsReturnsTelegram(): void
    {
        $this->assertSame(['telegram'], $this->gateway->getSupportedChannels());
    }

    public function testSendCallsBotClientAndReturnsSuccess(): void
    {
        $message = new Message('telegram','12345', 'Hello Telegram');

        $this->botClient->expects($this->once())
            ->method('sendMessage')
            ->with(12345, 'Hello Telegram')
            ->willReturn(true);

        $result = $this->gateway->send($message);

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
    }

    public function testSendReturnsFailedWhenBotReturnsFalse(): void
    {
        $message = new Message('telegram','12345', 'Hello');

        $this->botClient->expects($this->once())
            ->method('sendMessage')
            ->willReturn(false);

        $result = $this->gateway->send($message);

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
    }

    public function testSendFailsWithInvalidChatId(): void
    {
        $message = new Message('telegram','0', 'Hello');

        $this->botClient->expects($this->never())->method('sendMessage');

        $result = $this->gateway->send($message);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testIsConfiguredReturnsTrueWhenBotTokenSet(): void
    {
        $GLOBALS['_test_options'] = [
            'wsms_auth_settings' => ['telegram' => ['bot_token' => 'abc:123']],
        ];

        $this->assertTrue($this->gateway->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenNoBotToken(): void
    {
        $GLOBALS['_test_options'] = ['wsms_auth_settings' => []];

        $this->assertFalse($this->gateway->isConfigured());
    }
}
