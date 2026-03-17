<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\TwilioProvider;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class TwilioProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(): AbstractProvider
    {
        return new TwilioProvider();
    }

    public function testSupportsMultipleChannels(): void
    {
        $provider = $this->createProvider();
        $channels = $provider->getSupportedChannels();

        $this->assertContains('sms', $channels);
        $this->assertContains('whatsapp', $channels);
    }

    public function testConfigSchemaHasPerChannelFields(): void
    {
        $provider = $this->createProvider();
        $schema = $provider->getConfigSchema();

        $this->assertArrayHasKey('channels', $schema);
        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertArrayHasKey('whatsapp', $schema['channels']);
        $this->assertArrayHasKey('from_number', $schema['channels']['sms']);
        $this->assertArrayHasKey('from_number', $schema['channels']['whatsapp']);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'twilio' => [
                'shared' => [
                    'account_sid' => 'AC123',
                    'auth_token'  => 'tok123',
                ],
                'channels' => [
                    'sms' => ['from_number' => '+14155551234'],
                ],
            ],
        ];

        $provider = $this->createProvider();
        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfiguredForChannelSmsButNotWhatsApp(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'twilio' => [
                'shared' => [
                    'account_sid' => 'AC123',
                    'auth_token'  => 'tok123',
                ],
                'channels' => [
                    'sms' => ['from_number' => '+14155551234'],
                    'whatsapp' => [],
                ],
            ],
        ];

        $provider = $this->createProvider();
        $this->assertTrue($provider->isConfiguredForChannel('sms'));
        $this->assertFalse($provider->isConfiguredForChannel('whatsapp'));
    }

    public function testMetadataHasExpectedKeys(): void
    {
        $provider = $this->createProvider();
        $metadata = $provider->getMetadata();

        $this->assertArrayHasKey('description', $metadata);
        $this->assertArrayHasKey('website', $metadata);
        $this->assertArrayHasKey('regions', $metadata);
    }

    public function testFeaturesIncludesBulkAndMms(): void
    {
        $provider = $this->createProvider();
        $features = $provider->getFeatures();

        $this->assertTrue($features['bulk_send']);
        $this->assertTrue($features['mms']);
        $this->assertTrue($features['delivery_receipt']);
    }
}
