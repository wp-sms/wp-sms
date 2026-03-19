<?php

namespace WSms\Tests\Unit\Integration\Telegram\Triggers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Integration\Telegram\Triggers\CallbackQueryTrigger;
use WSms\Telegram\TelegramBotClient;

class CallbackQueryTriggerTest extends TestCase
{
    private CallbackQueryTrigger $trigger;
    private MockObject&TelegramBotClient $botClient;

    protected function setUp(): void
    {
        $this->botClient = $this->createMock(TelegramBotClient::class);
        $this->trigger = new CallbackQueryTrigger($this->botClient);
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram.callback_query', $this->trigger->getId());
        $this->assertSame('Telegram', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('callback_id', $schema);
        $this->assertArrayHasKey('chat_id', $schema);
        $this->assertArrayHasKey('message_id', $schema);
        $this->assertArrayHasKey('data', $schema);
        $this->assertArrayHasKey('from', $schema);
    }

    public function testFilterSchemaHasData(): void
    {
        $filters = $this->trigger->getFilterSchema();
        $this->assertArrayHasKey('data', $filters);
    }

    public function testSubscribeProducesCorrectPayload(): void
    {
        $this->botClient->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('callback123');

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('wsms_telegram_callback_query', [
            'id'   => 'callback123',
            'from' => ['id' => 99, 'first_name' => 'John'],
            'data' => 'action:confirm',
            'message' => [
                'message_id' => 42,
                'chat'       => ['id' => 12345],
            ],
        ]);

        $this->assertNotNull($captured);
        $this->assertSame('callback123', $captured['callback_id']);
        $this->assertSame(12345, $captured['chat_id']);
        $this->assertSame(42, $captured['message_id']);
        $this->assertSame('action:confirm', $captured['data']);
        $this->assertSame(99, $captured['from']['id']);
    }

    public function testAutoAnswersCallbackQuery(): void
    {
        $this->botClient->expects($this->once())
            ->method('answerCallbackQuery')
            ->with('cb-id');

        $this->trigger->subscribe(function () {});

        $this->fireAction('wsms_telegram_callback_query', [
            'id'   => 'cb-id',
            'from' => ['id' => 99],
            'data' => 'test',
            'message' => [
                'message_id' => 1,
                'chat'       => ['id' => 123],
            ],
        ]);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
