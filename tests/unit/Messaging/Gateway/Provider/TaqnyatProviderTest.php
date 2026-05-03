<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\TaqnyatProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class TaqnyatProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'taqnyat-test-bearer-token';
    private const SENDER  = 'WSMS';
    private const SEND_URL    = 'https://api.taqnyat.sa/v1/messages';
    private const BALANCE_URL = 'https://api.taqnyat.sa/account/balance';
    private const SENDERS_URL = 'https://api.taqnyat.sa/v1/messages/senders';

    protected function createProvider(): AbstractProvider
    {
        return new TaqnyatProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get'],
        );
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'taqnyat' => [
                'shared'   => array_merge(['api_key' => self::API_KEY], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '966555000111', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
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
        $this->assertFalse(TaqnyatProvider::TESTED);
    }

    public function testGetIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('taqnyat', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testGetConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue((bool) ($schema['shared']['api_key']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['dynamic'] ?? false));
    }

    // --- Send ---

    public function testSendSmsHitsV1MessagesWithBearer(): void
    {
        $this->configure();
        $this->mockHttpPost(['messageId' => 'abc-123']);

        $this->createProvider()->send($this->createMessage('966555000111', 'Hi there'));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::API_KEY, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(['966555000111'], $body['recipients']);
        $this->assertSame(self::SENDER, $body['sender']);
        $this->assertSame('Hi there', $body['body']);
    }

    public function testSendSmsReturnsProviderIdFromResponse(): void
    {
        $this->configure();
        $this->mockHttpPost(['messageId' => 'msg-789']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('msg-789', $result->providerId);
    }

    public function testSendSmsFailsOnNonSuccessStatusCode(): void
    {
        $this->configure();
        $this->mockHttpPost(['message' => 'Invalid sender', 'code' => 14], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid sender', $result->error);
    }

    public function testSendSmsFailsOnInvalidBearerToken(): void
    {
        $this->configure();
        $this->mockHttpPost(['message' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Taqnyat bearer token', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditHitsBalanceEndpoint(): void
    {
        $this->configure();
        $captured = ['url' => null, 'args' => null];
        $this->mockHttpGet(
            ['balance' => '125.50'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url'] = $url;
                $captured['args'] = $args;
            },
        );

        $credit = $this->createProvider()->getCredit();

        $this->assertSame('125.50', $credit);
        $this->assertSame(self::BALANCE_URL, $captured['url']);
        $this->assertSame('Bearer ' . self::API_KEY, $captured['args']['headers']['Authorization']);
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpGet(['message' => 'Invalid bearer token'], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => '500']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500', $result->message);
        $this->assertSame('500', $result->details['balance']);
    }

    public function testTestConnectionMapsInvalidTokenTo401(): void
    {
        $this->configure();
        $this->mockHttpGet(['message' => 'Unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Taqnyat bearer token', $result->message);
    }

    public function testTestConnectionMapsErrorCode104InBody(): void
    {
        $this->configure();
        // Some Taqnyat endpoints embed error code 104 in a 200 OK envelope.
        $this->mockHttpGet(['statusCode' => 104, 'message' => 'Invalid bearer token'], 200);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Taqnyat bearer token', $result->message);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- getConfigOptions ---

    public function testGetConfigOptionsForFromHitsSendersEndpoint(): void
    {
        $config = [
            'shared'   => ['api_key' => self::API_KEY],
            'channels' => ['sms' => []],
        ];
        $captured = ['url' => null];
        $this->mockHttpGet(
            ['senders' => [
                ['sender' => 'BrandA'],
                ['sender' => 'BrandB'],
            ]],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url'] = $url;
            },
        );

        $options = $this->createProvider()->getConfigOptions('from', 'sms', $config);

        $this->assertSame(self::SENDERS_URL, $captured['url']);
        $this->assertSame([
            ['value' => 'BrandA', 'label' => 'BrandA'],
            ['value' => 'BrandB', 'label' => 'BrandB'],
        ], $options);
    }

    public function testGetConfigOptionsAcceptsPlainStringRows(): void
    {
        $config = [
            'shared'   => ['api_key' => self::API_KEY],
            'channels' => ['sms' => []],
        ];
        $this->mockHttpGet(['data' => ['SenderOne', 'SenderTwo']]);

        $options = $this->createProvider()->getConfigOptions('from', 'sms', $config);

        $this->assertSame([
            ['value' => 'SenderOne', 'label' => 'SenderOne'],
            ['value' => 'SenderTwo', 'label' => 'SenderTwo'],
        ], $options);
    }

    public function testGetConfigOptionsReturnsEmptyOnApiError(): void
    {
        $config = [
            'shared'   => ['api_key' => self::API_KEY],
            'channels' => ['sms' => []],
        ];
        $this->mockHttpGet(['message' => 'Unauthorized'], 401);

        $this->assertSame([], $this->createProvider()->getConfigOptions('from', 'sms', $config));
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('something_else', 'sms', []));
    }

    public function testGetConfigOptionsReturnsEmptyWithoutApiKey(): void
    {
        $config = ['shared' => [], 'channels' => ['sms' => []]];
        $this->assertSame([], $this->createProvider()->getConfigOptions('from', 'sms', $config));
    }
}
