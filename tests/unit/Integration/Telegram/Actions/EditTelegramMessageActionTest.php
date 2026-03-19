<?php

namespace WSms\Tests\Unit\Integration\Telegram\Actions;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Integration\Telegram\Actions\EditTelegramMessageAction;
use WSms\Telegram\TelegramBotClient;

class EditTelegramMessageActionTest extends TestCase
{
    private EditTelegramMessageAction $action;
    private MockObject&TelegramBotClient $botClient;

    protected function setUp(): void
    {
        $this->botClient = $this->createMock(TelegramBotClient::class);
        $this->action = new EditTelegramMessageAction($this->botClient);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram.edit_message', $this->action->getId());
        $this->assertSame('Telegram', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('chat_id', $schema);
        $this->assertArrayHasKey('message_id', $schema);
        $this->assertArrayHasKey('text', $schema);
        $this->assertArrayHasKey('parse_mode', $schema);
        $this->assertArrayHasKey('reply_markup', $schema);
        $this->assertTrue($schema['message_id']['required']);
        $this->assertTrue($schema['text']['required']);
    }

    public function testExecuteSuccess(): void
    {
        $this->botClient->expects($this->once())
            ->method('editMessageText')
            ->with(12345, 42, 'Updated text', $this->anything())
            ->willReturn(['message_id' => 42]);

        $result = $this->action->execute([], [
            'chat_id'    => '12345',
            'message_id' => '42',
            'text'       => 'Updated text',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(42, $result->output['message_id']);
    }

    public function testExecuteFailsWithInvalidReplyMarkup(): void
    {
        $result = $this->action->execute([], [
            'chat_id'      => '12345',
            'message_id'   => '42',
            'text'         => 'Test',
            'reply_markup' => '{invalid}',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid reply markup JSON', $result->error);
    }

    public function testExecuteFailsWithMissingParams(): void
    {
        $result = $this->action->execute([], [
            'chat_id'    => '12345',
            'message_id' => '0',
            'text'       => 'Test',
        ]);

        $this->assertFalse($result->success);
    }

    public function testExecuteFailsOnApiError(): void
    {
        $this->botClient->method('editMessageText')->willReturn(null);

        $result = $this->action->execute([], [
            'chat_id'    => '12345',
            'message_id' => '42',
            'text'       => 'Updated',
        ]);

        $this->assertFalse($result->success);
    }

    public function testPlaceholdersForCallbackQuery(): void
    {
        $placeholders = $this->action->getPlaceholders('telegram.callback_query');
        $this->assertSame('{{chat_id}}', $placeholders['chat_id']);
        $this->assertSame('{{message_id}}', $placeholders['message_id']);
    }

    public function testPlaceholdersForOtherTelegramTrigger(): void
    {
        $placeholders = $this->action->getPlaceholders('telegram.message_received');
        $this->assertArrayHasKey('chat_id', $placeholders);
        $this->assertArrayNotHasKey('message_id', $placeholders);
    }

    public function testPlaceholdersForNonTelegramTrigger(): void
    {
        $this->assertSame([], $this->action->getPlaceholders('wordpress.user_register'));
    }
}
