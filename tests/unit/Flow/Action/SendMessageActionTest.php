<?php

namespace WSms\Tests\Unit\Flow\Action;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Action\SendMessageAction;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\MessageDispatcher;

class SendMessageActionTest extends TestCase
{
    private SendMessageAction $action;

    protected function setUp(): void
    {
        $dispatcher = $this->createMock(MessageDispatcher::class);
        $registry = $this->createMock(GatewayRegistry::class);
        $this->action = new SendMessageAction($dispatcher, $registry);
    }

    public function testMetadata(): void
    {
        $this->assertSame('send_message', $this->action->getId());
        $this->assertSame('Send Message', $this->action->getName());
        $this->assertSame('Messaging', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('gateway', $schema);
        $this->assertArrayHasKey('channel', $schema);
        $this->assertArrayHasKey('to', $schema);
        $this->assertArrayHasKey('body', $schema);
        $this->assertArrayHasKey('subject', $schema);
        $this->assertTrue($schema['to']['template']);
        $this->assertTrue($schema['body']['template']);
        $this->assertTrue($schema['channel']['dynamic']);
        $this->assertTrue($schema['gateway']['dynamic']);
        $this->assertSame(['channel'], $schema['gateway']['dependsOn']);
    }

    public function testGetPlaceholdersForOrderCreated(): void
    {
        $placeholders = $this->action->getPlaceholders('woocommerce.order_created');
        $this->assertArrayHasKey('to', $placeholders);
        $this->assertArrayHasKey('body', $placeholders);
        $this->assertStringContainsString('{{customer.phone}}', $placeholders['to']);
    }

    public function testGetPlaceholdersForUserRegister(): void
    {
        $placeholders = $this->action->getPlaceholders('wordpress.user_register');
        $this->assertArrayHasKey('to', $placeholders);
        $this->assertArrayHasKey('body', $placeholders);
        $this->assertStringContainsString('{{user.email}}', $placeholders['to']);
    }

    public function testGetPlaceholdersForPostPublished(): void
    {
        $placeholders = $this->action->getPlaceholders('wordpress.post_published');
        $this->assertArrayHasKey('body', $placeholders);
        $this->assertStringContainsString('{{post_title}}', $placeholders['body']);
    }

    public function testGetPlaceholdersForUnknownTrigger(): void
    {
        $this->assertSame([], $this->action->getPlaceholders('unknown.trigger'));
    }
}
