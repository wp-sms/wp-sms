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

    public function testRegisterDeferredResolvesOnGet(): void
    {
        $this->registry->registerDeferred('lazy', fn() => $this->makeGateway('lazy', ['sms']));

        $gateway = $this->registry->get('lazy');

        $this->assertNotNull($gateway);
        $this->assertSame('lazy', $gateway->getId());
    }

    public function testRegisterDeferredWithClassName(): void
    {
        $this->registry->registerDeferred('test', \WSms\Messaging\Gateway\TestGateway::class);

        $gateway = $this->registry->get('test');

        $this->assertNotNull($gateway);
        $this->assertSame('test', $gateway->getId());
    }

    public function testDeferredNotResolvedUntilAccessed(): void
    {
        $resolved = false;
        $this->registry->registerDeferred('lazy', function () use (&$resolved) {
            $resolved = true;
            return $this->makeGateway('lazy', ['sms']);
        });

        $this->assertFalse($resolved);
        $this->assertContains('lazy', $this->registry->allIds());

        $this->registry->get('lazy');
        $this->assertTrue($resolved);
    }

    public function testAllResolvesDeferred(): void
    {
        $this->registry->register($this->makeGateway('eager', ['sms']));
        $this->registry->registerDeferred('lazy', fn() => $this->makeGateway('lazy', ['email']));

        $all = $this->registry->all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('lazy', $all);
    }

    public function testAllIds(): void
    {
        $this->registry->register($this->makeGateway('eager', ['sms']));
        $this->registry->registerDeferred('lazy', fn() => $this->makeGateway('lazy', ['email']));

        $ids = $this->registry->allIds();
        sort($ids);
        $this->assertSame(['eager', 'lazy'], $ids);
    }

    public function testGetDefaultUsesConfigOptimization(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'twilio' => ['is_default' => ['sms' => true]],
        ];

        $this->registry->registerDeferred('twilio', fn() => $this->makeGateway('twilio', ['sms']));
        $this->registry->registerDeferred('vonage', fn() => $this->makeGateway('vonage', ['sms']));

        $default = $this->registry->getDefault('sms');

        $this->assertNotNull($default);
        $this->assertSame('twilio', $default->getId());
    }

    public function testGetDefaultFallsBackToFirstConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $unconfigured = $this->makeGateway('unconfigured', ['sms'], false);
        $configured = $this->makeGateway('configured', ['sms'], true);

        $this->registry->register($unconfigured);
        $this->registry->register($configured);

        $default = $this->registry->getDefault('sms');
        $this->assertNotNull($default);
        $this->assertSame('configured', $default->getId());
    }

    public function testEagerRegistrationOverridesDeferred(): void
    {
        $this->registry->registerDeferred('gw', fn() => $this->makeGateway('gw', ['sms'], false));

        $eager = $this->makeGateway('gw', ['sms'], true);
        $this->registry->register($eager);

        $gateway = $this->registry->get('gw');
        $this->assertTrue($gateway->isConfigured());
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
            public function getConfigSchema(): array { return ['shared' => [], 'channels' => []]; }
            public function validateConfig(array $config): bool { return true; }
            public function isConfigured(): bool { return $this->configured; }
            public function isConfiguredForChannel(string $channel): bool { return $this->configured && in_array($channel, $this->channels); }
            public function getMetadata(): array { return []; }
            public function getFeatures(): array { return []; }
            public function getCredit(): ?string { return null; }
            public function testConnection(): \WSms\Messaging\Contracts\TestConnectionResult { return \WSms\Messaging\Contracts\TestConnectionResult::error('Not supported'); }
        };
    }
}
