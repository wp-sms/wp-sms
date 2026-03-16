<?php

namespace WSms\Tests\Unit\Messaging\Gateway;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\GatewayRegistry;

class GatewayRegistryTest extends TestCase
{
    private GatewayRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new GatewayRegistry();
        $GLOBALS['_test_options'] = [];
    }

    public function testRegisterAndGet(): void
    {
        $gateway = $this->makeGateway('test', ['sms']);
        $this->registry->register($gateway);

        $this->assertSame($gateway, $this->registry->get('test'));
    }

    public function testGetReturnsNullForUnknown(): void
    {
        $this->assertNull($this->registry->get('nonexistent'));
    }

    public function testGetByChannel(): void
    {
        $sms = $this->makeGateway('sms_gw', ['sms']);
        $email = $this->makeGateway('email_gw', ['email']);
        $multi = $this->makeGateway('multi_gw', ['sms', 'email']);

        $this->registry->register($sms);
        $this->registry->register($email);
        $this->registry->register($multi);

        $smsGateways = $this->registry->getByChannel('sms');
        $this->assertCount(2, $smsGateways);
        $this->assertArrayHasKey('sms_gw', $smsGateways);
        $this->assertArrayHasKey('multi_gw', $smsGateways);
    }

    public function testGetAvailableChannels(): void
    {
        $this->registry->register($this->makeGateway('a', ['sms', 'whatsapp']));
        $this->registry->register($this->makeGateway('b', ['email']));

        $channels = $this->registry->getAvailableChannels();
        sort($channels);

        $this->assertSame(['email', 'sms', 'whatsapp'], $channels);
    }

    public function testGetConfigured(): void
    {
        $configured = $this->makeGateway('configured', ['sms'], true);
        $unconfigured = $this->makeGateway('unconfigured', ['sms'], false);

        $this->registry->register($configured);
        $this->registry->register($unconfigured);

        $result = $this->registry->getConfigured();
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('configured', $result);
    }

    public function testAll(): void
    {
        $this->registry->register($this->makeGateway('a', ['sms']));
        $this->registry->register($this->makeGateway('b', ['email']));

        $this->assertCount(2, $this->registry->all());
    }

    private function makeGateway(string $id, array $channels, bool $configured = true): GatewayInterface
    {
        return new class($id, $channels, $configured) implements GatewayInterface {
            public function __construct(
                private string $id,
                private array $channels,
                private bool $configured,
            ) {}
            public function getId(): string { return $this->id; }
            public function getName(): string { return $this->id; }
            public function getSupportedChannels(): array { return $this->channels; }
            public function send(MessageInterface $message): DeliveryResult { return DeliveryResult::sent(); }
            public function getConfigSchema(): array { return []; }
            public function validateConfig(array $config): bool { return true; }
            public function isConfigured(): bool { return $this->configured; }
        };
    }
}
