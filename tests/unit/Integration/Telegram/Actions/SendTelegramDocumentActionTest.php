<?php

namespace WSms\Tests\Unit\Integration\Telegram\Actions;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Integration\Telegram\Actions\SendTelegramDocumentAction;
use WSms\Telegram\TelegramBotClient;

class SendTelegramDocumentActionTest extends TestCase
{
    private SendTelegramDocumentAction $action;
    private MockObject&TelegramBotClient $botClient;

    protected function setUp(): void
    {
        $this->botClient = $this->createMock(TelegramBotClient::class);
        $this->action = new SendTelegramDocumentAction($this->botClient);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram.send_document', $this->action->getId());
        $this->assertSame('Telegram', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('chat_id', $schema);
        $this->assertArrayHasKey('document', $schema);
        $this->assertArrayHasKey('caption', $schema);
        $this->assertTrue($schema['document']['required']);
    }

    public function testExecuteSuccess(): void
    {
        $this->botClient->expects($this->once())
            ->method('sendDocument')
            ->with(12345, 'https://example.com/report.pdf', $this->anything())
            ->willReturn(['message_id' => 45]);

        $result = $this->action->execute([], [
            'chat_id'  => '12345',
            'document' => 'https://example.com/report.pdf',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(45, $result->output['message_id']);
    }

    public function testExecuteFailsWithEmptyDocument(): void
    {
        $result = $this->action->execute([], [
            'chat_id'  => '12345',
            'document' => '',
        ]);

        $this->assertFalse($result->success);
    }

    public function testExecuteFailsOnApiError(): void
    {
        $this->botClient->method('sendDocument')->willReturn(null);

        $result = $this->action->execute([], [
            'chat_id'  => '12345',
            'document' => 'https://example.com/report.pdf',
        ]);

        $this->assertFalse($result->success);
    }
}
