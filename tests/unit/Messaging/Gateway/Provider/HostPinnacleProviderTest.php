<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\HostPinnacleProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class HostPinnacleProviderTest extends AbstractProviderTestCase
{
    private const USERNAME  = 'foo_user';
    private const PASSWORD  = 'sekret';
    private const API_KEY   = 'API-KEY-XYZ';
    private const SENDER_ID = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new HostPinnacleProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $defaultChannels = ['sms' => ['sender_id' => self::SENDER_ID]];
        foreach ($channelOverrides as $channel => $overrides) {
            $defaultChannels[$channel] = array_merge($defaultChannels[$channel] ?? [], $overrides);
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'hostpinnacle' => [
                'shared' => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                    'api_key'  => self::API_KEY,
                ], $sharedOverrides),
                'channels' => $defaultChannels,
            ],
        ];
    }

    private function createMessage(string $recipient = '+254712345678', string $body = 'Hello there'): Message
    {
        return new Message('sms', $recipient, $body, null, []);
    }

    private function mockPostJson(array $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($body),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockPostRaw(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $provider = $this->createProvider();
        $this->assertSame('hostpinnacle', $provider->getId());
        $this->assertSame(['sms'], $provider->getSupportedChannels());
    }

    public function testTestedFlagIsTrueAfterManualVerification(): void
    {
        $this->assertTrue(HostPinnacleProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertFalse($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertSame('string', $schema['channels']['sms']['sender_id']['type']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
    }

    // --- isConfiguredForChannel ---

    public function testIsConfiguredForChannelSmsOk(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testIsConfiguredForChannelOkWithoutApiKey(): void
    {
        // apiKey is an alternative auth method per the HostPinnacle docs;
        // a config with username + password and no API key is still complete.
        $this->configure(['api_key' => '']);
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testIsConfiguredForChannelMissingPassword(): void
    {
        $this->configure(['password' => '']);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testIsConfiguredForChannelMissingSenderId(): void
    {
        $this->configure([], ['sms' => ['sender_id' => '']]);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send: SMS ---

    public function testSmsSendBuildsExpectedFormPayload(): void
    {
        $this->configure();
        $this->mockPostJson(['transactionId' => 'tx-1', 'statusCode' => '200']);

        $this->createProvider()->send($this->createMessage('+254712345678', 'Hello there'));

        $this->assertSame(
            'https://smsportal.hostpinnacle.co.ke/SMSApi/send',
            $GLOBALS['_test_wp_remote_post_last_url']
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['apiKey']);

        $body = $args['body'];
        $this->assertIsArray($body);
        $this->assertSame(self::USERNAME, $body['userid']);
        $this->assertSame(self::PASSWORD, $body['password']);
        $this->assertSame('quick', $body['sendMethod']);
        $this->assertSame('254712345678', $body['mobile']);
        $this->assertSame('Hello there', $body['msg']);
        $this->assertSame(self::SENDER_ID, $body['senderid']);
        $this->assertSame('text', $body['msgType']);
        $this->assertSame('json', $body['output']);
    }

    public function testSmsSendOmitsApiKeyHeaderWhenNotConfigured(): void
    {
        $this->configure(['api_key' => '']);
        $this->mockPostJson(['transactionId' => 'tx-noheader', 'statusCode' => '200']);

        $this->createProvider()->send($this->createMessage());

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertArrayNotHasKey('apiKey', $args['headers'] ?? []);
        $this->assertSame(self::USERNAME, $args['body']['userid']);
        $this->assertSame(self::PASSWORD, $args['body']['password']);
    }

    public function testSmsSendUnicodeBodyFlipsMsgType(): void
    {
        $this->configure();
        $this->mockPostJson(['transactionId' => 'tx-2', 'statusCode' => '200']);

        $this->createProvider()->send($this->createMessage('+254712345678', 'مرحبا 👋'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('unicode', $body['msgType']);
        $this->assertSame('مرحبا 👋', $body['msg']);
    }

    public function testSmsSendSuccessReturnsQueuedWithTransactionId(): void
    {
        $this->configure();
        $this->mockPostJson(['transactionId' => 'abc123', 'statusCode' => '200']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('abc123', $result->providerId);
    }

    public function testSmsSendBodyErrorReturnsFailed(): void
    {
        $this->configure();
        $this->mockPostJson([
            'status'     => 'error',
            'reason'     => 'Insufficient balance',
            'statusCode' => '402',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient balance', $result->error);
        $this->assertSame('402', $result->meta['hostpinnacle_status_code'] ?? null);
    }

    public function testSmsSend401ReturnsCredentialError(): void
    {
        $this->configure();
        $this->mockPostRaw('Unauthorized', 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid HostPinnacle credentials', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsBalanceFromReadstatus(): void
    {
        $this->configure();
        $this->mockPostJson([
            'response' => [
                'code'    => '200',
                'account' => ['smsBalance' => '1500'],
            ],
        ]);

        $credit = $this->createProvider()->getCredit();

        $this->assertSame('1500', $credit);
        $this->assertSame(
            'https://smsportal.hostpinnacle.co.ke/SMSApi/account/readstatus',
            $GLOBALS['_test_wp_remote_post_last_url']
        );
    }

    public function testGetCreditReturnsNullOnNonOkCode(): void
    {
        $this->configure();
        $this->mockPostJson([
            'response' => ['code' => '401', 'msg' => 'Invalid credentials'],
        ]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionOkOnReadstatusSuccess(): void
    {
        $this->configure();
        $this->mockPostJson([
            'response' => [
                'code'    => '200',
                'account' => ['smsBalance' => '1500'],
            ],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('1500', $result->message);
    }

    public function testTestConnectionErrorOnNon200ResponseCode(): void
    {
        $this->configure();
        $this->mockPostJson([
            'response' => ['code' => '401', 'msg' => 'Auth fail'],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Auth fail', $result->message);
    }
}
