<?php

namespace WSms\Tests\Unit\Integration\Telegram\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\Telegram\Triggers\ChannelPostTrigger;

class ChannelPostTriggerTest extends TestCase
{
    private ChannelPostTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new ChannelPostTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram.channel_post', $this->trigger->getId());
        $this->assertSame('Telegram', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('chat_id', $schema);
        $this->assertArrayHasKey('message_id', $schema);
        $this->assertArrayHasKey('text', $schema);
        $this->assertArrayHasKey('chat', $schema);
        $this->assertArrayHasKey('date', $schema);
        $this->assertArrayHasKey('has_media', $schema);
    }

    public function testSubscribeProducesCorrectPayload(): void
    {
        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('wsms_telegram_channel_post', [
            'chat'       => ['id' => -100123, 'type' => 'channel', 'title' => 'My Channel'],
            'message_id' => 5,
            'text'       => 'Channel announcement',
            'date'       => 1700000000,
        ]);

        $this->assertNotNull($captured);
        $this->assertSame(-100123, $captured['chat_id']);
        $this->assertSame(5, $captured['message_id']);
        $this->assertSame('Channel announcement', $captured['text']);
        $this->assertSame('channel', $captured['chat']['type']);
        $this->assertFalse($captured['has_media']);
    }

    public function testMediaPost(): void
    {
        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('wsms_telegram_channel_post', [
            'chat'       => ['id' => -100123, 'type' => 'channel', 'title' => 'My Channel'],
            'message_id' => 6,
            'caption'    => 'Photo post',
            'photo'      => [['file_id' => 'abc']],
            'date'       => 1700000000,
        ]);

        $this->assertNotNull($captured);
        $this->assertSame('Photo post', $captured['text']);
        $this->assertTrue($captured['has_media']);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
