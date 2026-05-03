<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\AltiriaProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class AltiriaProviderTest extends AbstractProviderTestCase
{
    private const USERNAME     = 'user@example.com';
    private const PASSWORD     = 'altiria-password';
    private const FROM         = 'MyBrand';
    private const SEND_URL     = 'https://www.altiria.net:8443/apirest/ws/sendSms';
    private const CREDIT_URL   = 'https://www.altiria.net:8443/apirest/ws/getCredit';

    protected function createProvider(): AbstractProvider
    {
        return new AltiriaProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_post']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'altiria' => [
                'shared'   => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge([], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+34612345678', string $body = 'Hola'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200, ?callable $capture = null): void
    {
        $payload = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];

        if ($capture) {
            $GLOBALS['_test_wp_remote_post'] = function ($url, $args) use ($payload, $capture) {
                $capture($url, $args);
                return $payload;
            };
        } else {
            $GLOBALS['_test_wp_remote_post'] = $payload;
        }
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(AltiriaProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('altiria', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
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
        $this->assertFalse((bool) ($schema['channels']['sms']['from']['required'] ?? false));

        $this->assertArrayHasKey('unicode', $schema['channels']['sms']);
        $this->assertSame('boolean', $schema['channels']['sms']['unicode']['type']);
    }

    // --- Send ---

    public function testSendBuildsCorrectJsonBody(): void
    {
        $this->configure();
        $captured = ['url' => null, 'args' => null];
        $this->mockHttpPost(
            ['status' => '000'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url']  = $url;
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage('+34612345678', 'Hola mundo'));

        $payload = json_decode($captured['args']['body'], true);

        $this->assertSame(self::USERNAME, $payload['credentials']['login']);
        $this->assertSame(self::PASSWORD, $payload['credentials']['passwd']);
        $this->assertSame(['34612345678'], $payload['destination']);
        $this->assertSame('Hola mundo', $payload['message']['msg']);
        $this->assertTrue($payload['message']['concat']);
        $this->assertArrayHasKey('source', $payload);
        $this->assertNotEmpty($payload['source']);
    }

    public function testSendUsesHttpPostWithJsonContentType(): void
    {
        $this->configure();
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['status' => '000'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage());

        $contentType = $captured['args']['headers']['Content-Type'] ?? '';
        $this->assertStringContainsString('application/json', $contentType);
    }

    public function testSendCapturesEndpointUrl(): void
    {
        $this->configure();
        $captured = ['url' => null];
        $this->mockHttpPost(
            ['status' => '000'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url'] = $url;
            },
        );

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(self::SEND_URL, $captured['url']);
    }

    public function testSendIncludesSenderIdWhenConfigured(): void
    {
        $this->configure([], ['from' => self::FROM]);
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['status' => '000'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage());

        $payload = json_decode($captured['args']['body'], true);
        $this->assertSame(self::FROM, $payload['message']['senderId']);
    }

    public function testSendOmitsSenderIdWhenBlank(): void
    {
        $this->configure([], ['from' => '']);
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['status' => '000'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage());

        $payload = json_decode($captured['args']['body'], true);
        $this->assertArrayNotHasKey('senderId', $payload['message']);
    }

    public function testSendUsesUnicodeEncodingWhenChannelFlagEnabled(): void
    {
        $this->configure([], ['unicode' => true]);
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['status' => '000'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage('+34612345678', 'Hola ñoño 😀'));

        $payload = json_decode($captured['args']['body'], true);
        $this->assertSame('unicode', $payload['message']['encoding']);
    }

    public function testSendOmitsEncodingWhenUnicodeFlagDisabled(): void
    {
        $this->configure([], ['unicode' => false]);
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['status' => '000'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage());

        $payload = json_decode($captured['args']['body'], true);
        $this->assertArrayNotHasKey('encoding', $payload['message']);
    }

    public function testSendStripsPlusPrefixFromRecipient(): void
    {
        $this->configure();
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['status' => '000'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage('+34612345678'));

        $payload = json_decode($captured['args']['body'], true);
        $this->assertSame(['34612345678'], $payload['destination']);
    }

    public function testSendReturnsSentOnStatus000(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => '000']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
    }

    public function testSendReturnsFailedOnAuthError(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => '020']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication', $result->error);
        $this->assertSame('020', $result->meta['altiria_status']);
    }

    public function testSendReturnsFailedOnInvalidNumber(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => '010']);

        $result = $this->createProvider()->send($this->createMessage('+10000'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('recipient', strtolower($result->error));
    }

    public function testSendReturnsFailedOnHttpError(): void
    {
        $this->configure();
        $this->mockHttpPost([], 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    public function testSendReturnsFailedOnNetworkError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'cURL error 28: timed out');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('timed out', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsCreditOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => '000', 'credit' => '12.5']);

        $this->assertSame('12.5', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnApiError(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => '020']);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $captured = ['url' => null];
        $this->mockHttpPost(
            ['status' => '000', 'credit' => '12.5'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url'] = $url;
            },
        );

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('12.5', $result->message);
        $this->assertSame('12.5', $result->details['balance']);
        $this->assertSame(self::CREDIT_URL, $captured['url']);
    }

    public function testTestConnectionReturnsErrorOnAuthFailure(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => '020']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', strtolower($result->message));
    }
}
