<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsboxProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsboxProviderTest extends AbstractProviderTestCase
{
    private const API_KEY   = 'sk_smsbox_test_abcdef';
    private const SENDER_ID = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new SmsboxProvider();
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

    private function configure(array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsbox' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER_ID], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+32470123456', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    private function mockHttpPost(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmsboxProvider::TESTED);
    }

    public function testGetIdReturnsSlug(): void
    {
        $this->assertSame('smsbox', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsbox' => ['shared' => [], 'channels' => ['sms' => []]],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    // --- doSend ---

    public function testDoSendSuccessReturnsSentWithProviderId(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode([
            'code'    => 100,
            'message' => [
                ['number' => '32470123456', 'status' => 'ok', 'id' => 'msg-abc-123'],
            ],
        ]));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('msg-abc-123', $result->providerId);
    }

    public function testDoSendSuccessWithoutIdLeavesProviderIdNull(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode([
            'code'    => 100,
            'message' => [['number' => '32470123456', 'status' => 'ok']],
        ]));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertNull($result->providerId);
    }

    public function testDoSendBuildsExpectedPostBodyAndAuthHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['code' => 100, 'message' => [[]]]));

        $this->createProvider()->send($this->createMessage('+32470123456', 'Hello world'));

        $this->assertSame(
            'https://core.smsbox.be/api/v1/sendsms',
            $GLOBALS['_test_wp_remote_post_last_url']
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['X-Api-Key']);
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $body);
        $this->assertSame('32470123456', $body['numbers']);
        $this->assertSame('Hello world', $body['message']);
        $this->assertSame('1', $body['longsms']);
        $this->assertSame(self::SENDER_ID, $body['from']);
        $this->assertArrayNotHasKey('tts', $body);
    }

    public function testDoSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['code' => 100, 'message' => [[]]]));

        $this->createProvider()->send($this->createMessage('+32470123456', 'Hi'));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertSame('32470123456', $body['numbers']);
    }

    public function testDoSendOmitsLongsmsWhenDisabled(): void
    {
        $this->configure(['longsms' => false]);
        $this->mockHttpPost(json_encode(['code' => 100, 'message' => [[]]]));

        $this->createProvider()->send($this->createMessage());

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertArrayNotHasKey('longsms', $body);
    }

    public function testDoSendOmitsFromWhenNotConfigured(): void
    {
        $this->configure(['from' => '']);
        $this->mockHttpPost(json_encode(['code' => 100, 'message' => [[]]]));

        $this->createProvider()->send($this->createMessage());

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertArrayNotHasKey('from', $body);
    }

    public function testDoSendIncludesTtsWhenMessageMetaRequestsIt(): void
    {
        $this->configure(['tts_language' => 'NL']);
        $this->mockHttpPost(json_encode(['code' => 100, 'message' => [[]]]));

        $this->createProvider()->send($this->createMessage(
            '+32470123456',
            'Spoken alert',
            ['tts' => true],
        ));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertSame('1', $body['tts']);
        $this->assertSame('NL', $body['ttslng']);
    }

    /**
     * @dataProvider providerErrorCodes
     */
    public function testDoSendMapsKnownErrorCodes(int $code, string $expected): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['code' => $code, 'message' => 'rejected']));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame($expected, $result->error);
        $this->assertSame($code, $result->meta['smsbox_code']);
    }

    public static function providerErrorCodes(): array
    {
        return [
            'sender not ok'    => [1,  'Sender not OK'],
            'invalid api key'  => [4,  'Invalid API key'],
            'no prefix'        => [5,  'Phone number has no prefix'],
            'no message'       => [6,  'SMS has no message'],
            'too long'         => [7,  'SMS max characters reached'],
            'no valid phone'   => [9,  'No valid phone number'],
            'no credits'       => [10, 'Not enough credits'],
            'sender too long'  => [13, 'Sender phone number is too long'],
        ];
    }

    public function testDoSendUnknownCodeFallsBackToGenericMessage(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['code' => 99, 'message' => 'wat']));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('99', $result->error);
    }

    public function testDoSendNon2xxReturnsGenericFailure(): void
    {
        $this->configure();
        $this->mockHttpPost('Internal Server Error', 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid call to SmsBox', $result->error);
    }

    public function testDoSendFailsWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsBalanceAsFloatString(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['code' => 100, 'message' => '4.50']));

        $this->assertSame('4.5', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnErrorCode(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['code' => 4, 'message' => 'Invalid API key']));

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionSucceedsWithBalance(): void
    {
        $this->configure();
        // First call (auth) → code 10; second call (balance) → code 100. Use a callable.
        $responses = [
            ['body' => json_encode(['code' => 10, 'message' => 'OK']),     'response' => ['code' => 200]],
            ['body' => json_encode(['code' => 100, 'message' => '12.00']), 'response' => ['code' => 200]],
        ];
        $GLOBALS['_test_wp_remote_get'] = function () use (&$responses) {
            return array_shift($responses);
        };

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('12', $result->message);
        $this->assertSame('12', $result->details['balance']);
    }

    public function testTestConnectionFailsOnInvalidApiKey(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['code' => 21, 'message' => 'invalid']));

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid SmsBox API key', $result->message);
    }

    public function testTestConnectionFailsOnNetworkError(): void
    {
        $this->configure();
        // No _test_wp_remote_get → defaults to WP_Error → DeliveryResult error path

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach', $result->message);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
