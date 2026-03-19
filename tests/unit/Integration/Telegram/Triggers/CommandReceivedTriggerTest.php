<?php

namespace WSms\Tests\Unit\Integration\Telegram\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\Telegram\Triggers\CommandReceivedTrigger;

class CommandReceivedTriggerTest extends TestCase
{
    private CommandReceivedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new CommandReceivedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram.command_received', $this->trigger->getId());
        $this->assertSame('Telegram', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('command', $schema);
        $this->assertArrayHasKey('args', $schema);
        $this->assertArrayHasKey('chat_id', $schema);
    }

    public function testFilterSchemaHasCommand(): void
    {
        $filters = $this->trigger->getFilterSchema();
        $this->assertArrayHasKey('command', $filters);
    }

    public function testSimpleCommand(): void
    {
        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => 12345, 'type' => 'private'],
            'message_id' => 1,
            'text'       => '/help',
            'from'       => ['id' => 99, 'first_name' => 'John'],
            'date'       => 1700000000,
        ]);

        $this->assertNotNull($captured);
        $this->assertSame('help', $captured['command']);
        $this->assertSame('', $captured['args']);
        $this->assertSame(12345, $captured['chat_id']);
    }

    public function testCommandWithArguments(): void
    {
        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => 12345, 'type' => 'private'],
            'message_id' => 1,
            'text'       => '/start referral123',
            'from'       => ['id' => 99],
            'date'       => 1700000000,
        ]);

        $this->assertNotNull($captured);
        $this->assertSame('start', $captured['command']);
        $this->assertSame('referral123', $captured['args']);
    }

    public function testCommandWithBotUsername(): void
    {
        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => -100123, 'type' => 'group'],
            'message_id' => 1,
            'text'       => '/order@mybot some args',
            'from'       => ['id' => 99],
            'date'       => 1700000000,
        ]);

        $this->assertNotNull($captured);
        $this->assertSame('order', $captured['command']);
        $this->assertSame('some args', $captured['args']);
    }

    public function testNonCommandDoesNotFire(): void
    {
        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => 12345, 'type' => 'private'],
            'message_id' => 1,
            'text'       => 'Hello!',
            'from'       => ['id' => 99],
            'date'       => 1700000000,
        ]);

        $this->assertFalse($fired);
    }

    public function testEmptyTextDoesNotFire(): void
    {
        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => 12345, 'type' => 'private'],
            'message_id' => 1,
            'from'       => ['id' => 99],
            'date'       => 1700000000,
        ]);

        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
