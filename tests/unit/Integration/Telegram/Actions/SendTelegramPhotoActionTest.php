<?php

namespace WSms\Tests\Unit\Integration\Telegram\Actions;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Integration\Telegram\Actions\SendTelegramPhotoAction;
use WSms\Telegram\TelegramBotClient;

class SendTelegramPhotoActionTest extends TestCase
{
    private SendTelegramPhotoAction $action;
    private MockObject&TelegramBotClient $botClient;

    protected function setUp(): void
    {
        $this->botClient = $this->createMock(TelegramBotClient::class);
        $this->action = new SendTelegramPhotoAction($this->botClient);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram.send_photo', $this->action->getId());
        $this->assertSame('Telegram', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('chat_id', $schema);
        $this->assertArrayHasKey('photo', $schema);
        $this->assertArrayHasKey('caption', $schema);
        $this->assertArrayHasKey('parse_mode', $schema);
        $this->assertTrue($schema['photo']['required']);
    }

    public function testExecuteSuccess(): void
    {
        $this->botClient->expects($this->once())
            ->method('sendPhoto')
            ->with(12345, 'https://example.com/photo.jpg', $this->anything())
            ->willReturn(['message_id' => 44]);

        $result = $this->action->execute([], [
            'chat_id' => '12345',
            'photo'   => 'https://example.com/photo.jpg',
            'caption' => 'Nice photo',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(44, $result->output['message_id']);
    }

    public function testExecuteFailsWithEmptyPhoto(): void
    {
        $result = $this->action->execute([], [
            'chat_id' => '12345',
            'photo'   => '',
        ]);

        $this->assertFalse($result->success);
    }

    public function testExecuteFailsOnApiError(): void
    {
        $this->botClient->method('sendPhoto')->willReturn(null);

        $result = $this->action->execute([], [
            'chat_id' => '12345',
            'photo'   => 'https://example.com/photo.jpg',
        ]);

        $this->assertFalse($result->success);
    }

    public function testPlaceholdersForTelegramTrigger(): void
    {
        $placeholders = $this->action->getPlaceholders('telegram.command_received');
        $this->assertSame('{{chat_id}}', $placeholders['chat_id']);
    }
}
