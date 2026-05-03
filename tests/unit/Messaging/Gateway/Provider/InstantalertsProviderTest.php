<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\InstantalertsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class InstantalertsProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'instantalerts-test-api-key';
    private const FROM = 'SEDEMO';
    private const SEND_URL_PREFIX = 'https://instantalerts.co/api/web/send/';
    private const CREDIT_URL_PREFIX = 'https://instantalerts.co/api/status/credit';

    protected function createProvider(): AbstractProvider
    {
        return new InstantalertsProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_get']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'instantalerts' => [
                'shared'   => array_merge(['api_key' => self::API_KEY], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+919999988888', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200, ?callable $capture = null): void
    {
        $payload = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];

        if ($capture) {
            $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($payload, $capture) {
                $capture($url, $args);
                return $payload;
            };
        } else {
            $GLOBALS['_test_wp_remote_get'] = $payload;
        }
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(InstantalertsProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('instantalerts', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue((bool) ($schema['shared']['api_key']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    // --- Send ---

    public function testSendIncludesAllQueryParamsInGet(): void
    {
        $this->configure();
        $captured = ['url' => null];
        $this->mockHttpGet(
            ['groupID' => 1, 'MessageIDs' => 'msg-1', 'status' => 'AWAITED-DLR'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url'] = $url;
            },
        );

        $this->createProvider()->send($this->createMessage('+919999988888', 'Hi there'));

        $this->assertNotNull($captured['url']);
        $this->assertStringStartsWith(self::SEND_URL_PREFIX, $captured['url']);

        $parsed = parse_url($captured['url']);
        parse_str($parsed['query'], $query);

        $this->assertSame(self::API_KEY, $query['apikey']);
        $this->assertSame(self::FROM, $query['sender']);
        $this->assertSame('919999988888', $query['to']);
        $this->assertSame('Hi there', $query['message']);
        $this->assertSame('json', $query['format']);
    }

    public function testSendReturnsSentWithMessageIdOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'groupID'    => 12345,
            'MessageIDs' => '987654321',
            'status'     => 'AWAITED-DLR',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('987654321', $result->providerId);
    }

    public function testSendReturnsFailedOnInvalidApiKey(): void
    {
        $this->configure();
        $this->mockHttpGet(
            ['status' => false, 'error' => 'Invalid API Key '],
            403,
        );

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API Key', $result->error);
    }

    public function testSendReturnsFailedOnInvalidMobile(): void
    {
        $this->configure();
        $this->mockHttpGet(
            ['status' => false, 'error' => 'Invalid Mobile Numbers'],
            200,
        );

        $result = $this->createProvider()->send($this->createMessage('+10000'));

        $this->assertFalse($result->success);
        $this->assertSame('Invalid Mobile Numbers', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsNumericString(): void
    {
        $this->configure();
        $this->mockHttpGet(['status' => true, 'credits' => 1234]);

        $this->assertSame('1234', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpGet(['status' => false, 'error' => 'Invalid API Key '], 403);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionPassesOn2xx(): void
    {
        $this->configure();
        $captured = ['url' => null];
        $this->mockHttpGet(
            ['status' => true, 'credits' => 1234],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url'] = $url;
            },
        );

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('1234', $result->message);
        $this->assertSame('1234', $result->details['balance']);
        $this->assertNotNull($captured['url']);
        $this->assertStringStartsWith(self::CREDIT_URL_PREFIX, $captured['url']);
    }

    public function testTestConnectionReturnsErrorOn403WithJsonBody(): void
    {
        $this->configure();
        $this->mockHttpGet(['status' => false, 'error' => 'Invalid API Key '], 403);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API Key', $result->message);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
