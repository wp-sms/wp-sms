<?php

namespace WSms\Tests\Unit\Integration\Webhook;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\TriggerInterface;
use WSms\Integration\Webhook\WebhookIntegration;

class WebhookIntegrationTest extends TestCase
{
    private WebhookIntegration $integration;

    protected function setUp(): void
    {
        $this->integration = new WebhookIntegration();
    }

    public function testMetadata(): void
    {
        $this->assertSame('webhook', $this->integration->getId());
        $this->assertSame('Webhook', $this->integration->getName());
        $this->assertSame('communication', $this->integration->getCategory());
        $this->assertTrue($this->integration->isAvailable());
    }

    public function testReturnsOneTrigger(): void
    {
        $triggers = $this->integration->getTriggers();
        $this->assertCount(1, $triggers);
        $this->assertInstanceOf(TriggerInterface::class, $triggers[0]);
        $this->assertSame('webhook.inbound', $triggers[0]->getId());
    }

    public function testReturnsNoActions(): void
    {
        $this->assertEmpty($this->integration->getActions());
    }
}
