<?php

namespace WSms\Tests\Unit\Messaging\Gateway;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Gateway\TestGateway;
use WSms\Messaging\Message\Message;

class TestGatewayTest extends TestCase
{
    private TestGateway $gateway;

    protected function setUp(): void
    {
        TestGateway::reset();
        $this->gateway = new TestGateway();
    }

    protected function tearDown(): void
    {
        TestGateway::reset();
    }

    public function testGetId(): void
    {
        $this->assertSame('test', $this->gateway->getId());
    }

    public function testSupportsMultipleChannels(): void
    {
        $channels = $this->gateway->getSupportedChannels();
        $this->assertContains('sms', $channels);
        $this->assertContains('email', $channels);
        $this->assertContains('whatsapp', $channels);
        $this->assertContains('telegram', $channels);
    }

    public function testSendStoresMessage(): void
    {
        $message = new Message('sms', '+1234', 'Hello');
        $result = $this->gateway->send($message);

        $this->assertTrue($result->success);
        $this->assertStringStartsWith('test-', $result->providerId);
        $this->assertSame(1, TestGateway::sentCount());
    }

    public function testLastMessage(): void
    {
        $msg1 = new Message('sms', '+1234', 'First');
        $msg2 = new Message('sms', '+5678', 'Second');

        $this->gateway->send($msg1);
        $this->gateway->send($msg2);

        $last = TestGateway::lastMessage();
        $this->assertSame('+5678', $last->getRecipient());
        $this->assertSame('Second', $last->getBody());
    }

    public function testReset(): void
    {
        $this->gateway->send(new Message('sms', '+1234', 'Hello'));
        $this->assertSame(1, TestGateway::sentCount());

        TestGateway::reset();
        $this->assertSame(0, TestGateway::sentCount());
        $this->assertNull(TestGateway::lastMessage());
    }

    public function testIsAlwaysConfigured(): void
    {
        $this->assertTrue($this->gateway->isConfigured());
        $this->assertTrue($this->gateway->isConfiguredForChannel('sms'));
        $this->assertTrue($this->gateway->isConfiguredForChannel('whatsapp'));
    }

    public function testGetCreditReturnsDummyValue(): void
    {
        $this->assertSame('999', $this->gateway->getCredit());
    }
}
