<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\_4jawalyProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class _4jawalyProviderTest extends AbstractProviderTestCase
{
    private const API_KEY    = 'fjk-api-key';
    private const API_SECRET = 'fjk-api-secret';
    private const SENDER     = 'WSMS';

    private const SEND_URL    = 'https://api-sms.4jawaly.com/api/v1/account/area/sms/send';
    private const BALANCE_URL = 'https://api-sms.4jawaly.com/api/v1/account/area/me/packages';
    private const SENDERS_URL = 'https://api-sms.4jawaly.com/api/v1/account/area/senders';

    protected function createProvider(): AbstractProvider
    {
        return new _4jawalyProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            '4jawaly' => [
                'shared'   => array_merge([
                    'api_key'    => self::API_KEY,
                    'api_secret' => self::API_SECRET,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_name' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+966500112233', string $body = 'Hello'): Message
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

    private function mockHttpGet(array $responseBody, int $statusCode = 200, ?string &$capturedUrl = null): void
    {
        $payload = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];

        $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($payload, &$capturedUrl) {
            $capturedUrl = $url;
            return $payload;
        };
    }

    private function expectedBasicAuth(): string
    {
        return 'Basic ' . base64_encode(self::API_KEY . ':' . self::API_SECRET);
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(_4jawalyProvider::TESTED);
    }

    public function testGetIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('4jawaly', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertArrayHasKey('api_secret', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertSame('secret', $schema['shared']['api_secret']['type']);

        $this->assertArrayHasKey('sender_name', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender_name']['dynamic']);
    }

    // --- Send ---

    public function testDoSendBuildsBasicAuthAndPayload(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success' => true,
            'job_id'  => 'abc',
        ]);

        $result = $this->createProvider()->send($this->createMessage('+966500112233', 'Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('abc', $result->providerId);

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedBasicAuth(), $args['headers']['Authorization']);

        $body = json_decode($args['body'], true);
        $this->assertCount(1, $body['messages']);
        $entry = $body['messages'][0];
        $this->assertSame('Hi', $entry['text']);
        $this->assertSame(['+966500112233'], $entry['numbers']);
        $this->assertSame(self::SENDER, $entry['sender']);
    }

    public function testDoSendFailsOnApiFailure(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success' => false,
            'errors'  => ['error_type' => ['phone_numbers']],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('phone_numbers', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsTotalBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['total_balance' => 1234.5]);

        $this->assertSame('1234.5', $this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet(['total_balance' => 50], 200, $url);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::BALANCE_URL, $url);
    }

    public function testTestConnectionFailureOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credential', strtolower($result->message));
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsReturnsApprovedSenders(): void
    {
        $config = [
            'shared'   => ['api_key' => self::API_KEY, 'api_secret' => self::API_SECRET],
            'channels' => ['sms' => []],
        ];

        $url = null;
        $this->mockHttpGet([
            'items' => [
                ['sender_name' => 'WSMS'],
                ['sender_name' => 'OTP'],
            ],
        ], 200, $url);

        $options = $this->createProvider()->getConfigOptions('sender_name', 'sms', $config);

        $this->assertSame([
            ['value' => 'WSMS', 'label' => 'WSMS'],
            ['value' => 'OTP',  'label' => 'OTP'],
        ], $options);

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::SENDERS_URL, $url);
    }

    public function testGetConfigOptionsReturnsEmptyOnApiError(): void
    {
        $config = [
            'shared'   => ['api_key' => self::API_KEY, 'api_secret' => self::API_SECRET],
            'channels' => ['sms' => []],
        ];

        $this->mockHttpGet(['error' => 'oops'], 500);

        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_name', 'sms', $config));
    }
}
