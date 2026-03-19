<?php

namespace WSms\Tests\Unit\Integration\Telegram\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\Telegram\Triggers\MemberJoinedTrigger;

class MemberJoinedTriggerTest extends TestCase
{
    private MemberJoinedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new MemberJoinedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram.member_joined', $this->trigger->getId());
        $this->assertSame('Telegram', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('chat_id', $schema);
        $this->assertArrayHasKey('chat', $schema);
        $this->assertArrayHasKey('user', $schema);
    }

    public function testFiresOncePerNewMember(): void
    {
        $payloads = [];
        $this->trigger->subscribe(function (array $payload) use (&$payloads) {
            $payloads[] = $payload;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat' => ['id' => -100123, 'type' => 'supergroup', 'title' => 'Test Group'],
            'message_id' => 1,
            'new_chat_members' => [
                ['id' => 10, 'first_name' => 'Alice'],
                ['id' => 20, 'first_name' => 'Bob'],
            ],
            'from' => ['id' => 10],
            'date' => 1700000000,
        ]);

        $this->assertCount(2, $payloads);
        $this->assertSame(-100123, $payloads[0]['chat_id']);
        $this->assertSame('Alice', $payloads[0]['user']['first_name']);
        $this->assertSame('Bob', $payloads[1]['user']['first_name']);
    }

    public function testDoesNotFireWithoutNewMembers(): void
    {
        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('wsms_telegram_message', [
            'chat'       => ['id' => 12345, 'type' => 'private'],
            'message_id' => 1,
            'text'       => 'Hello',
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
