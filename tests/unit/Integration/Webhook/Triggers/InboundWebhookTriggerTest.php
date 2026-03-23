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
        $this->assertArrayHasKey('webhook_id', $schema);
        $this->assertArrayHasKey('method', $schema);
        $this->assertArrayHasKey('body', $schema);
        $this->assertArrayHasKey('headers', $schema);
        $this->assertSame('string', $schema['webhook_id']['type']);
        $this->assertSame('string', $schema['method']['type']);
        $this->assertSame('object', $schema['body']['type']);
        $this->assertArrayHasKey('example', $schema['body']);
    }

    public function testFilterSchemaHasWebhookId(): void
    {
        $schema = $this->trigger->getFilterSchema();
        $this->assertArrayHasKey('webhook_id', $schema);
        $this->assertSame('string', $schema['webhook_id']['type']);
        $this->assertTrue($schema['webhook_id']['dynamic']);
    }

    public function testFilterOptionsReturnsEndpoints(): void
    {
        $GLOBALS['_test_options']['wsms_webhook_secrets'] = [
            'abc123' => ['secret' => 's', 'label' => 'Stripe', 'created_at' => '2026-01-01T00:00:00+00:00'],
            'def456' => ['secret' => 's', 'label' => 'GitHub', 'created_at' => '2026-01-01T00:00:00+00:00'],
        ];

        $options = $this->trigger->getFilterOptions('webhook_id');
        $this->assertCount(2, $options);
        $this->assertSame('abc123', $options[0]['value']);
        $this->assertSame('Stripe', $options[0]['label']);
        $this->assertSame('def456', $options[1]['value']);

        unset($GLOBALS['_test_options']['wsms_webhook_secrets']);
    }

    public function testFilterOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->trigger->getFilterOptions('unknown'));
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
