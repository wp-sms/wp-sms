<?php

namespace WSms\Tests\Unit\Integration\Telegram;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\TriggerInterface;
use WSms\Integration\Telegram\TelegramIntegration;
use WSms\Telegram\TelegramBotClient;

class TelegramIntegrationTest extends TestCase
{
    private TelegramIntegration $integration;
    private TelegramBotClient $botClient;

    protected function setUp(): void
    {
        $this->botClient = $this->createMock(TelegramBotClient::class);
        $this->integration = new TelegramIntegration($this->botClient);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options'], $GLOBALS['_test_wp_remote_post']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('telegram', $this->integration->getId());
        $this->assertSame('Telegram', $this->integration->getName());
        $this->assertSame('communication', $this->integration->getCategory());
        $this->assertSame('send', $this->integration->getIcon());
        $this->assertTrue($this->integration->isAvailable());
        $this->assertSame('api_key', $this->integration->getAuthType());
    }

    public function testAuthSchemaHasBotToken(): void
    {
        $schema = $this->integration->getAuthSchema();
        $this->assertArrayHasKey('bot_token', $schema);
        $this->assertTrue($schema['bot_token']['required']);
    }

    public function testReturnsFiveTriggers(): void
    {
        $GLOBALS['_test_actions'] = [];
        $triggers = $this->integration->getTriggers();
        $this->assertCount(5, $triggers);

        foreach ($triggers as $trigger) {
            $this->assertInstanceOf(TriggerInterface::class, $trigger);
        }
    }

    public function testTriggerIdsAreCorrect(): void
    {
        $GLOBALS['_test_actions'] = [];
        $ids = array_map(fn($t) => $t->getId(), $this->integration->getTriggers());

        $expected = [
            'telegram.message_received',
            'telegram.command_received',
            'telegram.callback_query',
            'telegram.member_joined',
            'telegram.channel_post',
        ];

        foreach ($expected as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function testReturnsFourActions(): void
    {
        $actions = $this->integration->getActions();
        $this->assertCount(4, $actions);

        foreach ($actions as $action) {
            $this->assertInstanceOf(ActionInterface::class, $action);
        }
    }

    public function testActionIdsAreCorrect(): void
    {
        $ids = array_map(fn($a) => $a->getId(), $this->integration->getActions());

        $this->assertContains('telegram.send_message', $ids);
        $this->assertContains('telegram.send_photo', $ids);
        $this->assertContains('telegram.send_document', $ids);
        $this->assertContains('telegram.edit_message', $ids);
    }

    public function testConnectThrowsOnEmptyToken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->integration->connect(['bot_token' => '']);
    }

    public function testConnectThrowsOnInvalidToken(): void
    {
        // Simulate Telegram API failure
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['ok' => false, 'description' => 'Unauthorized']),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid bot token.');
        $this->integration->connect(['bot_token' => 'bad-token']);
    }

    public function testIsConnectedChecksIntegrationConfigs(): void
    {
        $GLOBALS['_test_options'] = [
            'wsms_integration_configs' => [
                'telegram' => ['credentials' => ['bot_token' => '123:ABC']],
            ],
        ];

        $this->assertTrue($this->integration->isConnected());
    }

    public function testIsNotConnectedWhenNoToken(): void
    {
        $GLOBALS['_test_options'] = [
            'wsms_integration_configs' => [],
        ];

        $this->assertFalse($this->integration->isConnected());
    }

    public function testDisconnectDeletesWebhook(): void
    {
        $this->botClient->expects($this->once())->method('deleteWebhook');

        $this->integration->disconnect();
    }
}
