<?php

namespace WSms\Tests\Unit\Messaging\Catalog;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateCatalogException;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsTemplateCatalog;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\GatewayRegistry;

class TemplateCatalogManagerTest extends TestCase
{
    private GatewayRegistry $registry;
    private TemplateCatalogManager $manager;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_transients'] = [];

        $this->registry = new GatewayRegistry();
        $this->manager = new TemplateCatalogManager($this->registry);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options'], $GLOBALS['_test_transients']);
    }

    private function registerCatalogGateway(string $id = 'twilio', array $templates = []): void
    {
        $gateway = new class($id, $templates) implements GatewayInterface, SupportsTemplateCatalog {
            public function __construct(
                private readonly string $id,
                private readonly array $templates,
            ) {
            }

            public function getId(): string { return $this->id; }
            public function getName(): string { return 'Test'; }
            public function getSupportedChannels(): array { return ['sms', 'whatsapp']; }
            public function getConfigSchema(): array { return []; }
            public function getMetadata(): array { return []; }
            public function getFeatures(): array { return []; }
            public function isConfigured(): bool { return true; }
            public function isConfiguredForChannel(string $channel): bool { return true; }
            public function send(MessageInterface $message): DeliveryResult { return DeliveryResult::sent(); }
            public function validateConfig(array $config): bool { return true; }
            public function getCredit(): ?string { return null; }
            public function testConnection(): TestConnectionResult { return TestConnectionResult::ok(); }

            public function fetchTemplates(): array
            {
                return $this->templates;
            }

            public function requiresTemplateForChannel(string $channel): bool
            {
                return $channel === 'whatsapp';
            }

            public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
            {
                return ['ContentSid' => $mapping->providerTemplateId];
            }
        };

        $this->registry->register($gateway);
    }

    private function makeTemplate(string $id = 'HX123', string $name = 'OTP', TemplateStatus $status = TemplateStatus::Approved): ProviderTemplate
    {
        return new ProviderTemplate(
            id: $id,
            name: $name,
            language: 'en',
            category: 'authentication',
            status: $status,
            bodyText: 'Code: {{1}}',
            variableCount: 1,
        );
    }

    // --- getTemplates ---

    public function testGetTemplatesFetchesFromGateway(): void
    {
        $this->registerCatalogGateway('twilio', [$this->makeTemplate()]);

        $templates = $this->manager->getTemplates('twilio');

        $this->assertCount(1, $templates);
        $this->assertSame('HX123', $templates[0]->id);
    }

    public function testGetTemplatesReturnsCachedOnSecondCall(): void
    {
        $templates = [$this->makeTemplate()];
        $this->registerCatalogGateway('twilio', $templates);

        // First call fetches
        $this->manager->getTemplates('twilio');

        // Replace gateway with one that returns empty — should still get cached
        $this->registerCatalogGateway('twilio', []);
        $result = $this->manager->getTemplates('twilio');

        $this->assertCount(1, $result);
    }

    public function testGetTemplatesForceRefreshBypassesCache(): void
    {
        $this->registerCatalogGateway('twilio', [$this->makeTemplate()]);
        $this->manager->getTemplates('twilio');

        // Replace with new templates
        $this->registerCatalogGateway('twilio', [$this->makeTemplate('HX999', 'New')]);
        $result = $this->manager->getTemplates('twilio', forceRefresh: true);

        $this->assertCount(1, $result);
        $this->assertSame('HX999', $result[0]->id);
    }

    public function testGetTemplatesThrowsForNonCatalogGateway(): void
    {
        $this->expectException(TemplateCatalogException::class);

        $this->manager->getTemplates('nonexistent');
    }

    // --- Mapping CRUD ---

    public function testSaveAndResolveMapping(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'HX123',
            gatewayId: 'twilio',
            language: 'en',
            variableMap: ['otp_code' => '1'],
        );

        $this->manager->saveMapping($mapping);
        $resolved = $this->manager->resolveMapping('otp', 'twilio');

        $this->assertNotNull($resolved);
        $this->assertSame('HX123', $resolved->providerTemplateId);
        $this->assertSame(['otp_code' => '1'], $resolved->variableMap);
    }

    public function testResolveMappingReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->manager->resolveMapping('otp', 'twilio'));
    }

    public function testRemoveMapping(): void
    {
        $mapping = new TemplateMapping('otp', 'HX123', 'twilio', 'en', []);
        $this->manager->saveMapping($mapping);

        $this->manager->removeMapping('otp', 'twilio');

        $this->assertNull($this->manager->resolveMapping('otp', 'twilio'));
    }

    public function testGetMappingsForGatewayReturnsOnlyMatchingGateway(): void
    {
        $this->manager->saveMapping(new TemplateMapping('otp', 'HX1', 'twilio', 'en', []));
        $this->manager->saveMapping(new TemplateMapping('welcome', 'HX2', 'twilio', 'en', []));
        $this->manager->saveMapping(new TemplateMapping('otp', 'META1', 'meta_cloud', 'en', []));

        $twilioMappings = $this->manager->getMappingsForGateway('twilio');
        $metaMappings = $this->manager->getMappingsForGateway('meta_cloud');

        $this->assertCount(2, $twilioMappings);
        $this->assertCount(1, $metaMappings);
    }

    // --- Gateway-scoped mappings persist across provider switches ---

    public function testMappingsAreScopedByGatewayId(): void
    {
        $this->manager->saveMapping(new TemplateMapping('otp', 'HX1', 'twilio', 'en', ['otp_code' => '1']));
        $this->manager->saveMapping(new TemplateMapping('otp', 'META1', 'meta_cloud', 'en', ['otp_code' => '1']));

        $twilio = $this->manager->resolveMapping('otp', 'twilio');
        $meta = $this->manager->resolveMapping('otp', 'meta_cloud');

        $this->assertSame('HX1', $twilio->providerTemplateId);
        $this->assertSame('META1', $meta->providerTemplateId);
    }

    // --- verifyMappings ---

    public function testVerifyMappingsIdentifiesValidAndStaleMappings(): void
    {
        $this->registerCatalogGateway('twilio', [
            $this->makeTemplate('HX1', 'Kept'),
        ]);

        $this->manager->saveMapping(new TemplateMapping('otp', 'HX1', 'twilio', 'en', []));
        $this->manager->saveMapping(new TemplateMapping('welcome', 'HX_DELETED', 'twilio', 'en', []));

        $result = $this->manager->verifyMappings('twilio');

        $this->assertCount(1, $result['valid']);
        $this->assertCount(1, $result['stale']);
        $this->assertSame('otp', $result['valid'][0]->templateType);
        $this->assertSame('welcome', $result['stale'][0]->templateType);
    }

    public function testVerifyMappingsUpdatesLastVerifiedTimestamp(): void
    {
        $this->registerCatalogGateway('twilio', [$this->makeTemplate('HX1')]);
        $this->manager->saveMapping(new TemplateMapping('otp', 'HX1', 'twilio', 'en', []));

        $result = $this->manager->verifyMappings('twilio');

        $this->assertNotNull($result['valid'][0]->lastVerifiedAt);
        $this->assertGreaterThan(0, $result['valid'][0]->lastVerifiedAt);
    }

    // --- gatewaySupportsTemplates ---

    public function testGatewaySupportsTemplatesReturnsTrueForCatalogGateway(): void
    {
        $this->registerCatalogGateway('twilio');

        $this->assertTrue($this->manager->gatewaySupportsTemplates('twilio'));
    }

    public function testGatewaySupportsTemplatesReturnsFalseForNonCatalogGateway(): void
    {
        $this->assertFalse($this->manager->gatewaySupportsTemplates('nonexistent'));
    }
}
