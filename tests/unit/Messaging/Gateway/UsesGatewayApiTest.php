<?php

namespace WSms\Tests\Unit\Messaging\Gateway;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Gateway\GatewayApiClient;
use WSms\Messaging\Gateway\UsesGatewayApi;

class UsesGatewayApiTest extends TestCase
{
    private object $gateway;

    private static function setApiCache(?array $data): void
    {
        $ref = new \ReflectionProperty(GatewayApiClient::class, 'cache');
        $ref->setValue(null, $data);
    }

    protected function setUp(): void
    {
        self::setApiCache(null);

        $this->gateway = new class {
            use UsesGatewayApi;
            public function getId(): string { return 'twilio'; }
        };
    }

    protected function tearDown(): void
    {
        self::setApiCache(null);
    }

    // ------------------------------------------------------------------
    // getName
    // ------------------------------------------------------------------

    public function testGetNameReturnsApiName(): void
    {
        self::setApiCache([
            'twilio' => ['name' => 'Twilio'],
        ]);

        $this->assertSame('Twilio', $this->gateway->getName());
    }

    public function testGetNameFallsBackToHumanizedId(): void
    {
        self::setApiCache([]);

        $this->assertSame('Twilio', $this->gateway->getName());
    }

    public function testGetNameHumanizesHyphenatedId(): void
    {
        $gateway = new class {
            use UsesGatewayApi;
            public function getId(): string { return 'my-gateway'; }
        };

        self::setApiCache([]);

        $this->assertSame('My Gateway', $gateway->getName());
    }

    public function testGetNameHumanizesUnderscoredId(): void
    {
        $gateway = new class {
            use UsesGatewayApi;
            public function getId(): string { return 'my_gateway'; }
        };

        self::setApiCache([]);

        $this->assertSame('My Gateway', $gateway->getName());
    }

    public function testGetNameWhenApiCompletelyUnavailable(): void
    {
        // null cache = nothing available
        $this->assertSame('Twilio', $this->gateway->getName());
    }

    // ------------------------------------------------------------------
    // getMetadata
    // ------------------------------------------------------------------

    public function testGetMetadataReturnsApiData(): void
    {
        self::setApiCache([
            'twilio' => [
                'description' => 'Cloud SMS',
                'website' => 'https://twilio.com',
                'branding' => ['logo_square' => 'https://logo.png'],
                'coverage' => ['regions' => ['global']],
                'setup' => [
                    'dashboard' => 'https://console.twilio.com',
                    'notes' => ['Step 1', 'Step 2'],
                ],
                'status' => 'active',
                'tier' => 'premium',
                'recommended' => true,
            ],
        ]);

        $metadata = $this->gateway->getMetadata();

        $this->assertSame('Cloud SMS', $metadata['description']);
        $this->assertSame('https://twilio.com', $metadata['website']);
        $this->assertSame('https://logo.png', $metadata['icon']);
        $this->assertSame(['global'], $metadata['regions']);
        $this->assertSame('https://console.twilio.com', $metadata['setup_url']);
        $this->assertSame(['Step 1', 'Step 2'], $metadata['setup_notes']);
        $this->assertSame('active', $metadata['status']);
        $this->assertSame('premium', $metadata['tier']);
        $this->assertTrue($metadata['recommended']);
    }

    public function testGetMetadataReturnsDefaultsWhenApiUnavailable(): void
    {
        // No cache at all
        $metadata = $this->gateway->getMetadata();

        $this->assertSame('', $metadata['description']);
        $this->assertSame('', $metadata['website']);
        $this->assertSame('', $metadata['icon']);
        $this->assertSame([], $metadata['regions']);
        $this->assertSame('', $metadata['setup_url']);
        $this->assertSame([], $metadata['setup_notes']);
        $this->assertSame('active', $metadata['status']);
        $this->assertSame('free', $metadata['tier']);
        $this->assertFalse($metadata['recommended']);
        $this->assertSame([], $metadata['branding']);
        $this->assertSame([], $metadata['coverage']);
    }

    public function testGetMetadataReturnsDefaultsWhenGatewayNotInApi(): void
    {
        self::setApiCache([
            'vonage' => ['name' => 'Vonage'],
        ]);

        $metadata = $this->gateway->getMetadata();

        $this->assertSame('', $metadata['description']);
        $this->assertSame('active', $metadata['status']);
    }

    public function testGetMetadataReturnsConsistentStructure(): void
    {
        // With API data
        self::setApiCache(['twilio' => ['name' => 'Twilio']]);
        $withApi = $this->gateway->getMetadata();

        // Without API data
        self::setApiCache([]);
        $withoutApi = $this->gateway->getMetadata();

        // Same keys in both cases
        $this->assertSame(array_keys($withApi), array_keys($withoutApi));
    }

    public function testGetMetadataHandlesPartialApiData(): void
    {
        self::setApiCache([
            'twilio' => [
                'description' => 'Partial data',
                // no website, branding, coverage, setup, etc.
            ],
        ]);

        $metadata = $this->gateway->getMetadata();

        $this->assertSame('Partial data', $metadata['description']);
        $this->assertSame('', $metadata['website']);
        $this->assertSame('', $metadata['icon']);
        $this->assertSame([], $metadata['regions']);
    }

    // ------------------------------------------------------------------
    // getFeatures
    // ------------------------------------------------------------------

    public function testGetFeaturesReturnsBaseDefaultsWhenApiUnavailable(): void
    {
        $features = $this->gateway->getFeatures();

        $this->assertFalse($features['mms']);
        $this->assertFalse($features['flash_sms']);
        $this->assertFalse($features['delivery_receipt']);
        $this->assertFalse($features['incoming']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
    }

    public function testGetFeaturesMergesApiOverDefaults(): void
    {
        self::setApiCache([
            'twilio' => [
                'features' => [
                    'mms' => true,
                    'delivery_receipt' => true,
                ],
            ],
        ]);

        $features = $this->gateway->getFeatures();

        $this->assertTrue($features['mms']);
        $this->assertTrue($features['delivery_receipt']);
        // Defaults preserved for unspecified features
        $this->assertFalse($features['flash_sms']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
    }

    public function testGetFeaturesFiltersUnknownKeys(): void
    {
        self::setApiCache([
            'twilio' => [
                'features' => [
                    'mms' => true,
                    'some_future_feature' => true,
                    'another_unknown' => false,
                ],
            ],
        ]);

        $features = $this->gateway->getFeatures();

        $this->assertArrayNotHasKey('some_future_feature', $features);
        $this->assertArrayNotHasKey('another_unknown', $features);
        $this->assertTrue($features['mms']);
    }

    public function testGetFeaturesApiCanDisableDefaults(): void
    {
        self::setApiCache([
            'twilio' => [
                'features' => [
                    'unicode' => false,
                    'test_connection' => false,
                ],
            ],
        ]);

        $features = $this->gateway->getFeatures();

        $this->assertFalse($features['unicode']);
        $this->assertFalse($features['test_connection']);
    }

    public function testGetFeaturesReturnsDefaultsWhenGatewayNotInApi(): void
    {
        self::setApiCache([
            'vonage' => ['features' => ['mms' => true]],
        ]);

        $features = $this->gateway->getFeatures();

        $this->assertFalse($features['mms']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
    }

    public function testGetFeaturesHandlesMissingFeaturesKey(): void
    {
        self::setApiCache([
            'twilio' => ['name' => 'Twilio'],
            // no 'features' key
        ]);

        $features = $this->gateway->getFeatures();

        // All defaults
        $this->assertFalse($features['mms']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
    }
}
