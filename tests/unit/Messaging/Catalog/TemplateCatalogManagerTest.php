<?php

namespace WSms\Tests\Unit\Messaging\Catalog;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateCatalogException;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsTemplateFetch;
use WSms\Messaging\Contracts\SupportsTemplates;
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
        $gateway = new class($id, $templates) implements GatewayInterface, SupportsTemplateFetch {
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

            public function getVariableStyle(): VariableStyle { return VariableStyle::Positional; }

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

    private function registerTemplatesOnlyGateway(string $id = 'kavenegar'): void
    {
        $gateway = new class($id) implements GatewayInterface, SupportsTemplates {
            public function __construct(private readonly string $id) {}

            public function getId(): string { return $this->id; }
            public function getName(): string { return 'Test Templates-Only'; }
            public function getSupportedChannels(): array { return ['sms']; }
            public function getConfigSchema(): array { return []; }
            public function getMetadata(): array { return []; }
            public function getFeatures(): array { return []; }
            public function isConfigured(): bool { return true; }
            public function isConfiguredForChannel(string $channel): bool { return true; }
            public function send(MessageInterface $message): DeliveryResult { return DeliveryResult::sent(); }
            public function validateConfig(array $config): bool { return true; }
            public function getCredit(): ?string { return null; }
            public function testConnection(): TestConnectionResult { return TestConnectionResult::ok(); }

            public function getVariableStyle(): VariableStyle { return VariableStyle::Positional; }

            public function requiresTemplateForChannel(string $channel): bool { return false; }

            public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
            {
                return ['template' => $mapping->providerTemplateId, 'variables' => $resolvedVariables];
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

    // --- Templates-only gateway (no fetch API) ---

    public function testGatewaySupportsTemplatesReturnsTrueForTemplatesOnlyGateway(): void
    {
        $this->registerTemplatesOnlyGateway('kavenegar');

        $this->assertTrue($this->manager->gatewaySupportsTemplates('kavenegar'));
    }

    public function testGatewaySupportsTemplateFetchReturnsFalseForTemplatesOnlyGateway(): void
    {
        $this->registerTemplatesOnlyGateway('kavenegar');

        $this->assertFalse($this->manager->gatewaySupportsTemplateFetch('kavenegar'));
    }

    public function testGatewaySupportsTemplateFetchReturnsTrueForFetchGateway(): void
    {
        $this->registerCatalogGateway('twilio');

        $this->assertTrue($this->manager->gatewaySupportsTemplateFetch('twilio'));
    }

    // --- Manual template CRUD ---

    public function testSaveAndGetManualTemplate(): void
    {
        $this->registerTemplatesOnlyGateway('kavenegar');

        $template = new ProviderTemplate(
            id: 'otp_verify',
            name: 'OTP Verify',
            language: 'en',
            category: 'authentication',
            status: TemplateStatus::Approved,
            bodyText: 'Code: %token',
            variableCount: 1,
            providerMeta: [],
            variables: [['key' => '1', 'type' => 'positional']],
            source: 'manual',
        );

        $this->manager->saveManualTemplate('kavenegar', $template);
        $manuals = $this->manager->getManualTemplates('kavenegar');

        $this->assertCount(1, $manuals);
        $this->assertSame('otp_verify', $manuals[0]->id);
        $this->assertSame('manual', $manuals[0]->source);
    }

    public function testRemoveManualTemplate(): void
    {
        $this->registerTemplatesOnlyGateway('kavenegar');

        $template = new ProviderTemplate(
            id: 'otp_verify',
            name: 'OTP',
            language: 'en',
            category: 'authentication',
            status: TemplateStatus::Approved,
            bodyText: 'Code: %token',
            variableCount: 1,
            source: 'manual',
        );

        $this->manager->saveManualTemplate('kavenegar', $template);
        $this->manager->removeManualTemplate('kavenegar', 'otp_verify');

        $this->assertCount(0, $this->manager->getManualTemplates('kavenegar'));
    }

    public function testGetTemplatesMergesManualAndFetched(): void
    {
        $this->registerCatalogGateway('twilio', [$this->makeTemplate('HX1', 'Fetched')]);

        $manual = new ProviderTemplate(
            id: 'manual1',
            name: 'Manual',
            language: 'en',
            category: 'utility',
            status: TemplateStatus::Approved,
            bodyText: 'Hello',
            variableCount: 0,
            source: 'manual',
        );
        $this->manager->saveManualTemplate('twilio', $manual);

        $all = $this->manager->getTemplates('twilio');

        $this->assertCount(2, $all);
        $ids = array_map(fn($t) => $t->id, $all);
        $this->assertContains('HX1', $ids);
        $this->assertContains('manual1', $ids);
    }

    public function testGetTemplatesForTemplatesOnlyGatewayReturnsOnlyManuals(): void
    {
        $this->registerTemplatesOnlyGateway('kavenegar');

        $template = new ProviderTemplate(
            id: 'lookup1',
            name: 'Lookup',
            language: 'en',
            category: 'utility',
            status: TemplateStatus::Approved,
            bodyText: 'Token: %token',
            variableCount: 1,
            source: 'manual',
        );
        $this->manager->saveManualTemplate('kavenegar', $template);

        $all = $this->manager->getTemplates('kavenegar');

        $this->assertCount(1, $all);
        $this->assertSame('lookup1', $all[0]->id);
    }

    // --- verifyMappings skips manual-source ---

    public function testVerifyMappingsSkipsManualSourceMappings(): void
    {
        $this->registerCatalogGateway('twilio', [
            $this->makeTemplate('HX1', 'Kept'),
        ]);

        $this->manager->saveMapping(new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'HX1',
            gatewayId: 'twilio',
            language: 'en',
            variableMap: [],
        ));
        $this->manager->saveMapping(new TemplateMapping(
            templateType: 'welcome',
            providerTemplateId: 'MANUAL_TPL',
            gatewayId: 'twilio',
            language: 'en',
            variableMap: [],
            source: 'manual',
        ));

        $result = $this->manager->verifyMappings('twilio');

        $this->assertCount(2, $result['valid']);
        $this->assertCount(0, $result['stale']);
    }

    // --- Template capabilities ---

    public function testGetTemplateCapabilitiesForFetchableGateway(): void
    {
        $this->registerCatalogGateway('twilio');

        $capabilities = $this->manager->getTemplateCapabilities('twilio');

        $this->assertTrue($capabilities['supports_templates']);
        $this->assertTrue($capabilities['fetchable']);
        $this->assertSame('positional', $capabilities['variable_style']);
        $this->assertContains('whatsapp', $capabilities['required_channels']);
    }

    public function testGetTemplateCapabilitiesForTemplatesOnlyGateway(): void
    {
        $this->registerTemplatesOnlyGateway('kavenegar');

        $capabilities = $this->manager->getTemplateCapabilities('kavenegar');

        $this->assertTrue($capabilities['supports_templates']);
        $this->assertFalse($capabilities['fetchable']);
        $this->assertSame('positional', $capabilities['variable_style']);
        $this->assertEmpty($capabilities['required_channels']);
    }

    public function testGetTemplateCapabilitiesReturnsNullForNonTemplateGateway(): void
    {
        $this->assertNull($this->manager->getTemplateCapabilities('nonexistent'));
    }

    // --- ProviderTemplate variable descriptors ---

    public function testProviderTemplateGetVariablesAutoGeneratesFromCount(): void
    {
        $template = $this->makeTemplate('HX1');
        $variables = $template->getVariables();

        $this->assertCount(1, $variables);
        $this->assertSame('1', $variables[0]['key']);
        $this->assertSame('positional', $variables[0]['type']);
    }

    public function testProviderTemplateGetVariablesReturnsExplicitVariables(): void
    {
        $template = new ProviderTemplate(
            id: 'T1',
            name: 'Test',
            language: 'en',
            category: 'utility',
            status: TemplateStatus::Approved,
            bodyText: 'Hello {{name}}',
            variableCount: 1,
            variables: [['key' => 'name', 'type' => 'named', 'label' => 'Name']],
        );

        $variables = $template->getVariables();

        $this->assertCount(1, $variables);
        $this->assertSame('name', $variables[0]['key']);
        $this->assertSame('named', $variables[0]['type']);
        $this->assertSame('Name', $variables[0]['label']);
    }

    // --- TemplateMapping source and regulatoryMeta ---

    public function testTemplateMappingPreservesSourceAndRegulatoryMeta(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'DLT123',
            gatewayId: 'vonage',
            language: 'en',
            variableMap: ['otp_code' => '1'],
            source: 'manual',
            regulatoryMeta: ['dlt_template_id' => 'DLT_T1', 'dlt_entity_id' => 'DLT_E1'],
        );

        $array = $mapping->toArray();
        $restored = TemplateMapping::fromArray($array);

        $this->assertSame('manual', $restored->source);
        $this->assertSame('DLT_T1', $restored->regulatoryMeta['dlt_template_id']);
        $this->assertSame('DLT_E1', $restored->regulatoryMeta['dlt_entity_id']);
    }
}
