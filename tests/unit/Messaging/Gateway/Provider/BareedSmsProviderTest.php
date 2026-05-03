<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BareedSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BareedSmsProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'panel-user';
    private const PASSWORD = 'panel-pass';
    private const FROM = 'COMPANY';
    private const SEND_URL_PREFIX = 'https://bareedsms.com/RemoteAPI/SendSMS.aspx';

    protected function createProvider(): AbstractProvider
    {
        return new BareedSmsProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_get']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bareedsms' => [
                'shared'   => array_merge(
                    ['username' => self::USERNAME, 'password' => self::PASSWORD],
                    $sharedOverrides,
                ),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+97333112233', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    /**
     * Mock wp_remote_get with a fixed response and capture the URL it was
     * invoked with into &$capturedUrl. Mirrors InstantalertsProviderTest's
     * callable-mock pattern so we can assert the composed query string.
     */
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
        $this->assertFalse(BareedSmsProvider::TESTED);
    }

    public function testGetId(): void
    {
        $this->assertSame('bareedsms', $this->createProvider()->getId());
    }

    public function testGetSupportedChannels(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue((bool) ($schema['shared']['username']['required'] ?? false));

        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue((bool) ($schema['shared']['password']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    public function testIsConfiguredFalseWhenMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bareedsms' => [
                'shared'   => ['username' => self::USERNAME], // password missing
                'channels' => ['sms' => ['from' => self::FROM]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
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

    public function testSendComposesAsciiSmsWithType0(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('msg-12345', 200, $url);

        $this->createProvider()->send($this->createMessage('+97333112233', 'Hello there'));

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::SEND_URL_PREFIX, $url);

        $query = $this->parseQuery($url);
        $this->assertSame('url', $query['encoding']);
        $this->assertSame(self::USERNAME, $query['username']);
        $this->assertSame(self::PASSWORD, $query['password']);
        $this->assertSame('0', $query['type']);
        $this->assertSame('97333112233', $query['receiver']);
        $this->assertSame(self::FROM, $query['source']);
        $this->assertSame('Hello there', $query['messagedata']);
    }

    public function testSendUsesType2ForNonAsciiBody(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('msg-1', 200, $url);

        $this->createProvider()->send($this->createMessage('+97333112233', 'مرحبا'));

        $this->assertSame('2', $this->parseQuery($url)['type']);
    }

    public function testSendUsesType1ForFlashMeta(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('msg-1', 200, $url);

        $this->createProvider()->send(
            $this->createMessage('+97333112233', 'Plain', ['flash' => true]),
        );

        $this->assertSame('1', $this->parseQuery($url)['type']);
    }

    public function testSendUsesType6ForFlashMetaWithNonAsciiBody(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('msg-1', 200, $url);

        $this->createProvider()->send(
            $this->createMessage('+97333112233', 'مرحبا', ['flash' => true]),
        );

        $this->assertSame('6', $this->parseQuery($url)['type']);
    }

    public function testSendUsesExplicitUnicodeMetaWhenSet(): void
    {
        // Even with an Arabic body, an explicit unicode=false override forces type=0.
        // Proves the meta override path takes precedence over the auto-detect fallback.
        $this->configure();
        $url = null;
        $this->mockHttpGet('msg-1', 200, $url);

        $this->createProvider()->send(
            $this->createMessage('+97333112233', 'مرحبا', ['unicode' => false]),
        );

        $this->assertSame('0', $this->parseQuery($url)['type']);
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('msg-1', 200, $url);

        $this->createProvider()->send($this->createMessage('+96598112233'));

        $this->assertSame('96598112233', $this->parseQuery($url)['receiver']);
    }

    public function testSendReturnsSentWithBodyAsProviderId(): void
    {
        $this->configure();
        $this->mockHttpGet('123456789', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('123456789', $result->providerId);
    }

    public function testSendFailsOnErrorPrefixedBody(): void
    {
        // Provider returns HTTP 200 but the body contains the literal "Error" substring.
        $this->configure();
        $this->mockHttpGet('Error: invalid sender', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Error: invalid sender', $result->error);
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
}
