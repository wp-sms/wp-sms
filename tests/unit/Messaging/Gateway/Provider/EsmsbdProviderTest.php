<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\EsmsbdProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class EsmsbdProviderTest extends AbstractProviderTestCase
{
    private const API_SEND = 'https://login.esms.com.bd/api/v3/sms/send';
    private const API_LIST = 'https://login.esms.com.bd/api/v3/sms/';
    private const API_TOKEN = 'esmsbd-token-1234';
    private const SENDER_ID = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new EsmsbdProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'esmsbd' => [
                'shared'   => array_merge([
                    'api_token' => self::API_TOKEN,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms' => ['sender_id' => self::SENDER_ID],
                ], $channelOverrides),
            ],
        ];
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(EsmsbdProvider::TESTED);
    }

    public function testGetIdReturnsExpectedSlug(): void
    {
        $this->assertSame('esmsbd', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue((bool) $schema['shared']['api_token']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['sender_id']['type']);
        $this->assertTrue((bool) $schema['channels']['sms']['sender_id']['required']);
    }

    public function testIsConfiguredRequiresApiToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'esmsbd' => [
                'shared'   => [],
                'channels' => ['sms' => ['sender_id' => self::SENDER_ID]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredForChannelRequiresSenderId(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'esmsbd' => [
                'shared'   => ['api_token' => self::API_TOKEN],
                'channels' => ['sms' => []],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testIsConfiguredForChannelTrueWhenComplete(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send ---

    public function testSendBuildsCorrectFormBodyAndAuthHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'success', 'data' => ['uid' => 'abc-123']]);

        $this->createProvider()->send(new Message('sms', '+8801712345678', 'Hello there'));

        $this->assertSame(self::API_SEND, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::API_TOKEN, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Accept']);

        $this->assertSame('+8801712345678', $args['body']['recipient']);
        $this->assertSame(self::SENDER_ID, $args['body']['sender_id']);
        $this->assertSame('plain', $args['body']['type']);
        $this->assertSame('Hello there', $args['body']['message']);
    }

    public function testSendQueuedOnSuccessStatus(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'success', 'data' => ['uid' => 'msg-99']]);

        $result = $this->createProvider()->send(new Message('sms', '+8801712345678', 'hi'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-99', $result->providerId);
    }

    public function testSendQueuedFallsBackToIdFieldWhenUidMissing(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'success', 'data' => ['id' => 42]]);

        $result = $this->createProvider()->send(new Message('sms', '+8801712345678', 'hi'));

        $this->assertTrue($result->success);
        $this->assertSame('42', $result->providerId);
    }

    public function testSendFailedOnErrorStatus(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'error', 'message' => 'Insufficient balance']);

        $result = $this->createProvider()->send(new Message('sms', '+8801712345678', 'hi'));

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient balance', $result->error);
    }

    public function testSendFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'error', 'message' => 'Unauthenticated.'], 401);

        $result = $this->createProvider()->send(new Message('sms', '+8801712345678', 'hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendFailedWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send(new Message('sms', '+8801712345678', 'hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailedWhenSenderIdMissing(): void
    {
        $this->configure(channelOverrides: ['sms' => []]);

        $result = $this->createProvider()->send(new Message('sms', '+8801712345678', 'hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    // --- Test connection ---

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $this->mockHttpGet(['status' => 'success', 'data' => []]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Connected', $result->message);
    }

    public function testTestConnectionFailsOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['status' => 'error', 'message' => 'Unauthenticated.'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
