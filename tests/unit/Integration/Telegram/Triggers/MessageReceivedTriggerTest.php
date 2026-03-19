<?php

namespace WSms\Tests\Unit\Integration\Telegram\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\Telegram\Triggers\MessageReceivedTrigger;

class MessageReceivedTriggerTest extends TestCase
{
    private MessageReceivedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new MessageReceivedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram.message_received', $this->trigger->getId());
        $this->assertSame('Message Received', $this->trigger->getName());
        $this->assertSame('Telegram', $this->trigger->getGroup());
        $this->assertInstanceOf(AbstractTrigger::class, $this->trigger);
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('chat_id', $schema);
        $this->assertArrayHasKey('message_id', $schema);
        $this->assertArrayHasKey('text', $schema);
        $this->assertArrayHasKey('from', $schema);
        $this->assertArrayHasKey('chat', $schema);
        $this->assertArrayHasKey('date', $schema);
        $this->assertArrayHasKey('has_media', $schema);
        $this->assertArrayHasKey('media_type', $schema);
    }

    public function testFilterSchemaHasChatType(): void
    {
        $filters = $this->trigger->getFilterSchema();
        $this->assertArrayHasKey('chat.type', $filters);
        $this->assertSame(['private', 'group', 'supergroup'], $filters['chat.type']['enum']);
    }

    public function testSubscribeRegistersHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('wsms_telegram_message', $GLOBALS['_test_actions']);
    }

    public function testSubscribeProducesCorrectPayload(): void
    {
        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => 12345, 'type' => 'private'],
            'message_id' => 42,
            'text'       => 'Hello there!',
            'from'       => ['id' => 99, 'first_name' => 'John', 'username' => 'johndoe'],
            'date'       => 1700000000,
        ]);

        $this->assertNotNull($captured);
        $this->assertSame(12345, $captured['chat_id']);
        $this->assertSame(42, $captured['message_id']);
        $this->assertSame('Hello there!', $captured['text']);
        $this->assertSame(99, $captured['from']['id']);
        $this->assertSame('private', $captured['chat']['type']);
        $this->assertFalse($captured['has_media']);
        $this->assertSame('', $captured['media_type']);
    }

    public function testSkipsCommands(): void
    {
        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => 12345, 'type' => 'private'],
            'message_id' => 1,
            'text'       => '/start',
            'from'       => ['id' => 99],
            'date'       => 1700000000,
        ]);

        $this->assertFalse($fired);
    }

    public function testSkipsNewChatMembers(): void
    {
        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'             => ['id' => 12345, 'type' => 'supergroup'],
            'message_id'       => 1,
            'new_chat_members' => [['id' => 99, 'first_name' => 'John']],
            'from'             => ['id' => 99],
            'date'             => 1700000000,
        ]);

        $this->assertFalse($fired);
    }

    public function testMediaMessageUsesCaption(): void
    {
        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => 12345, 'type' => 'private'],
            'message_id' => 1,
            'caption'    => 'Photo caption',
            'photo'      => [['file_id' => 'abc']],
            'from'       => ['id' => 99],
            'date'       => 1700000000,
        ]);

        $this->assertNotNull($captured);
        $this->assertSame('Photo caption', $captured['text']);
        $this->assertTrue($captured['has_media']);
        $this->assertSame('photo', $captured['media_type']);
    }

    public function testEmptyTextMessage(): void
    {
        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => 12345, 'type' => 'private'],
            'message_id' => 1,
            'from'       => ['id' => 99],
            'date'       => 1700000000,
        ]);

        $this->assertNotNull($captured);
        $this->assertSame('', $captured['text']);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
