<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\OneWaySmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class OneWaySmsProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'panel-user';
    private const PASSWORD = 'panel-pass';
    private const FROM = 'COMPANY';
    private const SEND_URL_MY = 'http://gateway.onewaysms.com.my:10001/api.aspx';
    private const SEND_URL_AU = 'http://gateway.onewaysms.com.au:10001/api.aspx';
    private const BALANCE_URL_MY = 'http://gateway.onewaysms.com.my:10001/bulkcredit.aspx';

    protected function createProvider(): AbstractProvider
    {
        return new OneWaySmsProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_get']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'onewaysms' => [
                'shared'   => array_merge(
                    [
                        'region'      => 'my',
                        'apiusername' => self::USERNAME,
                        'apipassword' => self::PASSWORD,
                    ],
                    $sharedOverrides,
                ),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+60123456789', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    private function mockHttpGet(string $body, int $statusCode, ?string &$capturedUrl = null): void
    {
        $payload = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];

        $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($payload, &$capturedUrl) {
            $capturedUrl = $url;
            return $payload;
        };
    }

    private function parseQuery(string $url): array
    {
        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $query);
        return $query;
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(OneWaySmsProvider::TESTED);
    }

    public function testGetId(): void
    {
        $this->assertSame('onewaysms', $this->createProvider()->getId());
    }

    public function testGetSupportedChannels(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('region', $schema['shared']);
        $this->assertSame('select', $schema['shared']['region']['type']);
        $this->assertTrue((bool) ($schema['shared']['region']['required'] ?? false));

        $regionValues = array_column($schema['shared']['region']['options'] ?? [], 'value');
        $this->assertSame(['my', 'au', 'custom'], $regionValues);

        $this->assertArrayHasKey('apiusername', $schema['shared']);
        $this->assertSame('string', $schema['shared']['apiusername']['type']);
        $this->assertTrue((bool) ($schema['shared']['apiusername']['required'] ?? false));

        $this->assertArrayHasKey('apipassword', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['apipassword']['type']);
        $this->assertTrue((bool) ($schema['shared']['apipassword']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    public function testIsConfiguredFalseWhenMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'onewaysms' => [
                'shared'   => ['region' => 'my', 'apiusername' => self::USERNAME], // password missing
                'channels' => ['sms' => ['from' => self::FROM]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testValidateConfigRequiresCustomUrlsWhenRegionIsCustom(): void
    {
        $provider = $this->createProvider();

        // Custom region without URLs → invalid
        $this->assertFalse($provider->validateConfig([
            'shared' => [
                'region'      => 'custom',
                'apiusername' => self::USERNAME,
                'apipassword' => self::PASSWORD,
            ],
        ]));

        // Custom region with both URLs → valid
        $this->assertTrue($provider->validateConfig([
            'shared' => [
                'region'             => 'custom',
                'apiusername'        => self::USERNAME,
                'apipassword'        => self::PASSWORD,
                'custom_send_url'    => 'http://example.com/api.aspx',
                'custom_balance_url' => 'http://example.com/bulkcredit.aspx',
            ],
        ]));

        // Non-custom region without custom URLs → still valid
        $this->assertTrue($provider->validateConfig([
            'shared' => [
                'region'      => 'my',
                'apiusername' => self::USERNAME,
                'apipassword' => self::PASSWORD,
            ],
        ]));
    }

    // --- Send ---

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $GLOBALS['_test_wp_remote_get'] = function () {
            throw new \RuntimeException('wp_remote_get must not be called when unconfigured');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendUsesMalaysianEndpointForRegionMy(): void
    {
        $this->configure(['region' => 'my']);
        $url = null;
        $this->mockHttpGet('123456789', 200, $url);

        $this->createProvider()->send($this->createMessage());

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::SEND_URL_MY, $url);
    }

    public function testSendUsesAustralianEndpointForRegionAu(): void
    {
        $this->configure(['region' => 'au']);
        $url = null;
        $this->mockHttpGet('123456789', 200, $url);

        $this->createProvider()->send($this->createMessage());

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::SEND_URL_AU, $url);
    }

    public function testSendUsesCustomUrlWhenRegionIsCustom(): void
    {
        $custom = 'http://custom.example.com:10001/api.aspx';
        $this->configure([
            'region'             => 'custom',
            'custom_send_url'    => $custom,
            'custom_balance_url' => 'http://custom.example.com:10001/bulkcredit.aspx',
        ]);
        $url = null;
        $this->mockHttpGet('123456789', 200, $url);

        $this->createProvider()->send($this->createMessage());

        $this->assertNotNull($url);
        $this->assertStringStartsWith($custom, $url);
    }

    public function testSendComposesQueryString(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('123456789', 200, $url);

        $this->createProvider()->send($this->createMessage('+60123456789', 'Hello there'));

        $query = $this->parseQuery($url);
        $this->assertSame(self::USERNAME, $query['apiusername']);
        $this->assertSame(self::PASSWORD, $query['apipassword']);
        $this->assertSame(self::FROM, $query['senderid']);
        $this->assertSame('60123456789', $query['mobileno']);
        $this->assertSame('Hello there', $query['message']);
        $this->assertSame('1', $query['languagetype']);
    }

    public function testSendUsesLanguageType2ForNonAsciiBody(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('123', 200, $url);

        $this->createProvider()->send($this->createMessage('+60123456789', 'مرحبا'));

        $this->assertSame('2', $this->parseQuery($url)['languagetype']);
    }

    public function testSendUsesLanguageType2WhenUnicodeMetaTrue(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('123', 200, $url);

        $this->createProvider()->send(
            $this->createMessage('+60123456789', 'Plain ASCII', ['unicode' => true]),
        );

        $this->assertSame('2', $this->parseQuery($url)['languagetype']);
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('123', 200, $url);

        $this->createProvider()->send($this->createMessage('+60123456789'));

        $this->assertSame('60123456789', $this->parseQuery($url)['mobileno']);
    }

    public function testSendReturnsSentWithMtIdAsProviderId(): void
    {
        $this->configure();
        $this->mockHttpGet('123456789', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('123456789', $result->providerId);
    }

    public function testSendFailsOnNegativeNumericBody(): void
    {
        $this->configure();
        $this->mockHttpGet('-100', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('-100', $result->error);
    }

    public function testSendFailsOnNonNumericBody(): void
    {
        $this->configure();
        $this->mockHttpGet('Authentication failed', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication failed', $result->error);
    }

    public function testSendFailsOnNon2xxStatus(): void
    {
        $this->configure();
        $this->mockHttpGet('', 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    public function testSendFailsOnNetworkError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'cURL timeout');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('cURL timeout', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsBalanceOnSuccess(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('1500.50', 200, $url);

        $credit = $this->createProvider()->getCredit();

        $this->assertSame('1500.50', $credit);
        $this->assertStringStartsWith(self::BALANCE_URL_MY, $url);
    }

    public function testGetCreditReturnsNullOnNegativeBody(): void
    {
        $this->configure();
        $this->mockHttpGet('-1', 200);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenCustomRegionLacksBalanceUrl(): void
    {
        $this->configure([
            'region'             => 'custom',
            'custom_send_url'    => 'http://custom.example.com:10001/api.aspx',
            'custom_balance_url' => '',
        ]);
        $GLOBALS['_test_wp_remote_get'] = function () {
            throw new \RuntimeException('wp_remote_get must not be called when balance URL is missing');
        };

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionSucceedsOnPositiveBalance(): void
    {
        $this->configure();
        $this->mockHttpGet('250', 200);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('250', $result->message);
    }

    public function testTestConnectionFailsOnNegativeBalance(): void
    {
        $this->configure();
        $this->mockHttpGet('-1', 200);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('-1', $result->message);
    }
}
