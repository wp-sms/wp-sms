<?php

namespace WSms\Tests\Unit\Integration\Webhook\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\Webhook\Triggers\InboundWebhookTrigger;

class InboundWebhookTriggerTest extends TestCase
{
    private InboundWebhookTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new InboundWebhookTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('webhook.inbound', $this->trigger->getId());
        $this->assertSame('Inbound Webhook', $this->trigger->getName());
        $this->assertSame('Webhook', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('body', $schema);
        $this->assertArrayHasKey('headers', $schema);
        $this->assertSame('object', $schema['body']['type']);
        $this->assertArrayHasKey('example', $schema['body']);
    }

    public function testSubscribeRegistersCorrectHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('wsms_webhook_received', $GLOBALS['_test_actions']);
    }

    public function testPassesThroughData(): void
    {
        $captured = null;
        $this->trigger->subscribe(function ($data) use (&$captured) {
            $captured = $data;
        });

        $data = ['body' => ['key' => 'value'], 'headers' => ['content-type' => 'application/json']];
        $this->fireAction('wsms_webhook_received', $data);

        $this->assertSame($data, $captured);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
