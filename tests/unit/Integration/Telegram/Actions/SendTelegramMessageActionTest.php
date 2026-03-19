<?php

namespace WSms\Tests\Unit\Integration\Telegram\Actions;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Integration\Telegram\Actions\SendTelegramMessageAction;
use WSms\Telegram\TelegramBotClient;

class SendTelegramMessageActionTest extends TestCase
{
    private SendTelegramMessageAction $action;
    private MockObject&TelegramBotClient $botClient;

    protected function setUp(): void
    {
        $this->botClient = $this->createMock(TelegramBotClient::class);
        $this->action = new SendTelegramMessageAction($this->botClient);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram.send_message', $this->action->getId());
        $this->assertSame('Telegram', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('chat_id', $schema);
        $this->assertArrayHasKey('text', $schema);
        $this->assertArrayHasKey('parse_mode', $schema);
        $this->assertArrayHasKey('reply_markup', $schema);
        $this->assertArrayHasKey('reply_to_message_id', $schema);
        $this->assertTrue($schema['chat_id']['required']);
        $this->assertTrue($schema['text']['required']);
    }

    public function testExecuteSuccess(): void
    {
        $this->botClient->expects($this->once())
            ->method('sendMessageAdvanced')
            ->with(12345, 'Hello!', $this->callback(fn($opts) => $opts['parse_mode'] === 'HTML'))
            ->willReturn(['message_id' => 42]);

        $result = $this->action->execute([], [
            'chat_id' => '12345',
            'text'    => 'Hello!',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(42, $result->output['message_id']);
    }

    public function testExecuteWithReplyMarkup(): void
    {
        $markup = '{"inline_keyboard":[[{"text":"OK","callback_data":"ok"}]]}';

        $this->botClient->expects($this->once())
            ->method('sendMessageAdvanced')
            ->with(
                12345,
                'Choose:',
                $this->callback(fn($opts) => is_array($opts['reply_markup']))
            )
            ->willReturn(['message_id' => 43]);

        $result = $this->action->execute([], [
            'chat_id'      => '12345',
            'text'         => 'Choose:',
            'reply_markup' => $markup,
        ]);

        $this->assertTrue($result->success);
    }

    public function testExecuteFailsWithInvalidReplyMarkup(): void
    {
        $result = $this->action->execute([], [
            'chat_id'      => '12345',
            'text'         => 'Test',
            'reply_markup' => 'not-valid-json{',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid reply markup JSON', $result->error);
    }

    public function testExecuteFailsWithEmptyChatId(): void
    {
        $result = $this->action->execute([], [
            'chat_id' => '0',
            'text'    => 'Hello',
        ]);

        $this->assertFalse($result->success);
    }

    public function testExecuteFailsWithEmptyText(): void
    {
        $result = $this->action->execute([], [
            'chat_id' => '12345',
            'text'    => '',
        ]);

        $this->assertFalse($result->success);
    }

    public function testExecuteFailsOnApiError(): void
    {
        $this->botClient->method('sendMessageAdvanced')->willReturn(null);

        $result = $this->action->execute([], [
            'chat_id' => '12345',
            'text'    => 'Hello!',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Failed to send', $result->error);
    }

    public function testPlaceholdersForTelegramTrigger(): void
    {
        $placeholders = $this->action->getPlaceholders('telegram.message_received');
        $this->assertArrayHasKey('chat_id', $placeholders);
        $this->assertSame('{{chat_id}}', $placeholders['chat_id']);
    }

    public function testPlaceholdersForNonTelegramTrigger(): void
    {
        $this->assertSame([], $this->action->getPlaceholders('wordpress.user_register'));
    }
}
