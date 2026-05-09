<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsApiBgProvider;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsApiBgProviderTest extends AbstractProviderTestCase
{
    private const API_TOKEN      = 'smsapi-bg-test-bearer-token';
    private const CALLBACK_TOKEN = 'webhook-secret-bg';

    protected function createProvider(): AbstractProvider
    {
        return new SmsApiBgProvider();
    }

    private function configure(array $sharedOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsapi-bg' => [
                'shared'   => array_merge([
                    'api_token'      => self::API_TOKEN,
                    'callback_token' => self::CALLBACK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => ['from' => 'TestSND'],
                ],
            ],
        ];
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmsApiBgProvider::TESTED);
    }

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smsapi-bg', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaOmitsRegion(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayNotHasKey('region', $schema['shared']);
    }

    public function testConfigSchemaInheritsApiTokenAndCallbackToken(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue((bool) $schema['shared']['api_token']['required']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertTrue((bool) $schema['shared']['callback_token']['required']);
    }

    public function testEndpointUsesBgHost(): void
    {
        $this->configure();
        $captured = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode(['points' => 12.34]),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->testConnection();

        $this->assertNotNull($captured);
        $this->assertStringStartsWith('https://api.smsapi.bg', $captured['url']);
        $this->assertSame('Bearer ' . self::API_TOKEN, $captured['args']['headers']['Authorization']);
    }

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $this->mockHttpGet(['points' => 12.34]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('12.34', $result->message);
        $this->assertSame(12.34, $result->details['balance']);
    }

    public function testTestConnectionRejects401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 101, 'message' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }
}
