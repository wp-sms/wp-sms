<?php

namespace WSms\Tests\Unit\Integration;

use PHPUnit\Framework\TestCase;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\IntegrationRegistry;

class IntegrationRegistryTest extends TestCase
{
    private IntegrationRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new IntegrationRegistry();
    }

    public function testRegisterAndGet(): void
    {
        $integration = $this->makeIntegration('test', true, 'cms');
        $this->registry->register($integration);

        $this->assertSame($integration, $this->registry->get('test'));
    }

    public function testGetAvailableFiltersUnavailable(): void
    {
        $this->registry->register($this->makeIntegration('available', true, 'cms'));
        $this->registry->register($this->makeIntegration('unavailable', false, 'cms'));

        $available = $this->registry->getAvailable();
        $this->assertCount(1, $available);
        $this->assertArrayHasKey('available', $available);
    }

    public function testGetAllReturnsAll(): void
    {
        $this->registry->register($this->makeIntegration('a', true, 'cms'));
        $this->registry->register($this->makeIntegration('b', false, 'ecommerce'));

        $this->assertCount(2, $this->registry->getAll());
    }

    public function testGetByCategory(): void
    {
        $this->registry->register($this->makeIntegration('wp', true, 'cms'));
        $this->registry->register($this->makeIntegration('woo', true, 'ecommerce'));
        $this->registry->register($this->makeIntegration('slack', true, 'communication'));

        $ecommerce = $this->registry->getByCategory('ecommerce');
        $this->assertCount(1, $ecommerce);
        $this->assertArrayHasKey('woo', $ecommerce);
    }

    private function makeIntegration(string $id, bool $available, string $category): IntegrationInterface
    {
        return new class($id, $available, $category) implements IntegrationInterface {
            public function __construct(private string $id, private bool $available, private string $category) {}
            public function getId(): string { return $this->id; }
            public function getName(): string { return $this->id; }
            public function getDescription(): string { return ''; }
            public function getCategory(): string { return $this->category; }
            public function getIcon(): string { return ''; }
            public function isAvailable(): bool { return $this->available; }
            public function getAuthType(): string { return 'none'; }
            public function getAuthSchema(): array { return []; }
            public function getTriggers(): array { return []; }
            public function getActions(): array { return []; }
            public function boot(): void {}
            public function connect(array $credentials): array { return $credentials; }
            public function disconnect(): void {}
            public function isConnected(): bool { return $this->available; }
        };
    }
}
