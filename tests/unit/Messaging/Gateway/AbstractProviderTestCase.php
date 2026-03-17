<?php

namespace WSms\Tests\Unit\Messaging\Gateway;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Gateway\AbstractProvider;

abstract class AbstractProviderTestCase extends TestCase
{
    abstract protected function createProvider(): AbstractProvider;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options']);
    }

    public function testGetIdReturnsNonEmptyString(): void
    {
        $provider = $this->createProvider();
        $id = $provider->getId();

        $this->assertNotEmpty($id);
        $this->assertMatchesRegularExpression('/^[a-z_]+$/', $id);
    }

    public function testGetNameReturnsNonEmptyString(): void
    {
        $provider = $this->createProvider();
        $this->assertNotEmpty($provider->getName());
    }

    public function testGetSupportedChannelsReturnsNonEmptyArray(): void
    {
        $provider = $this->createProvider();
        $channels = $provider->getSupportedChannels();

        $this->assertNotEmpty($channels);
        foreach ($channels as $channel) {
            $this->assertIsString($channel);
        }
    }

    public function testGetConfigSchemaHasSharedKey(): void
    {
        $provider = $this->createProvider();
        $schema = $provider->getConfigSchema();

        $this->assertArrayHasKey('shared', $schema);
    }

    public function testGetMetadataReturnsArray(): void
    {
        $provider = $this->createProvider();
        $metadata = $provider->getMetadata();

        $this->assertIsArray($metadata);
    }

    public function testGetFeaturesReturnsArray(): void
    {
        $provider = $this->createProvider();
        $features = $provider->getFeatures();

        $this->assertIsArray($features);
        foreach ($features as $key => $value) {
            $this->assertIsString($key);
            $this->assertIsBool($value);
        }
    }

    public function testIsConfiguredReturnsFalseWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $provider = $this->createProvider();

        $this->assertFalse($provider->isConfigured());
    }

    public function testIsConfiguredForChannelReturnsFalseForUnsupportedChannel(): void
    {
        $provider = $this->createProvider();
        $this->assertFalse($provider->isConfiguredForChannel('nonexistent_channel_xyz'));
    }

    public function testValidateConfigRejectsEmptyConfig(): void
    {
        $provider = $this->createProvider();
        $schema = $provider->getConfigSchema();

        // If there are required shared fields, empty config should fail
        $hasRequired = false;
        foreach ($schema['shared'] ?? [] as $field) {
            if (!empty($field['required'])) {
                $hasRequired = true;
                break;
            }
        }

        if ($hasRequired) {
            $this->assertFalse($provider->validateConfig(['shared' => []]));
        } else {
            $this->assertTrue($provider->validateConfig(['shared' => []]));
        }
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $provider = $this->createProvider();

        $this->assertNull($provider->getCredit());
    }
}
