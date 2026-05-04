<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\LiveAllProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class LiveAllProviderTest extends AbstractProviderTestCase
{
    private const API_TOKEN    = 'test-api-token-xyz';
    private const FROM         = 'MyBrand';
    private const SEND_URL     = 'https://sms.liveall.eu/apiext/Sendout/SendJSMS';
    private const BALANCE_URL  = 'https://sms.liveall.eu/apiext/Sendout/GetAccountBalance';

    protected function createProvider(): AbstractProvider
    {
        return new LiveAllProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_post']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'liveall' => [
                'shared'   => array_merge([
                    'api_token' => self::API_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge([
                        'from' => self::FROM,
                    ], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+306912345678', string $body = 'Hello'): Message
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
        $this->assertFalse(LiveAllProvider::TESTED);
    }

    public function testGetIdReturnsExpectedSlug(): void
    {
        $this->assertSame('liveall', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSms(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue((bool) ($schema['shared']['api_token']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    // --- Send ---

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $called = false;
        $GLOBALS['_test_wp_remote_post'] = function () use (&$called) {
            $called = true;
            return ['body' => '{}', 'response' => ['code' => 200]];
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', strtolower($result->error));
        $this->assertFalse($called, 'No HTTP call should be made when unconfigured');
    }

    public function testSendComposesRequestCorrectly(): void
    {
        $this->configure();
        $captured = ['url' => null, 'args' => null];
        $this->mockHttpPost(
            ['success' => true, 'data' => [['smsid' => 12345, 'destination' => '306912345678']]],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url']  = $url;
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage('+306912345678', 'Hello world'));

        $this->assertSame(self::SEND_URL, $captured['url']);
        $this->assertStringContainsString(
            'application/json',
            $captured['args']['headers']['Content-Type'] ?? '',
        );

        $payload = json_decode($captured['args']['body'], true);
        $this->assertSame(self::API_TOKEN, $payload['apitoken']);
        $this->assertSame(self::FROM, $payload['senderid']);
        $this->assertSame('306912345678', $payload['messages'][0]['destination']);
        $this->assertSame('Hello world', $payload['messages'][0]['message']);
    }

    public function testSendReturnsSentWithMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success' => true,
            'data'    => [['smsid' => 20818588, 'destination' => '306912345678']],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('20818588', $result->providerId);
    }

    public function testSendFailsOnApiError(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success'         => false,
            'OperationErrors' => [['errorCode' => 13, 'errorMessage' => 'Invalid sender ID']],
            'data'            => null,
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid sender ID', $result->error);
    }

    public function testSendFailsOnNon2xx(): void
    {
        $this->configure();
        $this->mockHttpPost([], 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsFormattedBalance(): void
    {
        $this->configure();
        $captured = ['url' => null];
        $this->mockHttpPost(
            ['success' => true, 'balance' => 12.34, 'sms_remaining' => 200],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url'] = $url;
            },
        );

        $credit = $this->createProvider()->getCredit();

        $this->assertSame(self::BALANCE_URL, $captured['url']);
        $this->assertSame('€12.34', $credit);
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => false, 'OperationErrors' => [['errorCode' => 1, 'errorMessage' => 'Invalid token']]]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionSucceedsWithValidCredentials(): void
    {
        $this->configure();
        $captured = ['url' => null];
        $this->mockHttpPost(
            ['success' => true, 'balance' => 5.55],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url'] = $url;
            },
        );

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5.55', $result->message);
        $this->assertSame(self::BALANCE_URL, $captured['url']);
    }

    public function testTestConnectionFailsOn401(): void
    {
        $this->configure();
        $this->mockHttpPost([], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
    }
}
