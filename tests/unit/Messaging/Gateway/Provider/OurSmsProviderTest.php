<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\OurSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class OurSmsProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'test-bearer-key-abc123';
    private const CALLBACK_TOKEN = 'test-webhook-token-xyz';
    private const SENDER_ID = 'OurSms';
    private const RECIPIENT = '966500000000';

    protected function createProvider(): AbstractProvider
    {
        return new OurSmsProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'oursms' => [
                'shared' => array_merge([
                    'api_key'        => self::API_KEY,
                    'callback_token' => self::CALLBACK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_id' => self::SENDER_ID], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', self::RECIPIENT, $body, null, $meta);
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
        $this->assertFalse(OurSmsProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('oursms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);
        $this->assertSame('secret', $schema['shared']['callback_token']['type']);
        $this->assertTrue($schema['shared']['callback_token']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['dynamic']);
    }

    // --- Send ---

    public function testSmsSendPostsToCorrectEndpointWithBearerAuth(): void
    {
        $this->configure();
        $this->mockHttpPost(['msgId' => 'msg-001']);

        $this->createProvider()->send($this->createMessage('Hi'));

        $this->assertSame('https://api.oursms.com/msgs/sms', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::API_KEY, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);
    }

    public function testSendBodyShapeMatchesOpenApiSpec(): void
    {
        $this->configure();
        $this->mockHttpPost(['msgId' => 'msg-001']);

        $this->createProvider()->send($this->createMessage('Hello world'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::SENDER_ID, $body['src']);
        $this->assertSame([self::RECIPIENT], $body['dests']);
        $this->assertSame('Hello world', $body['body']);
        $this->assertSame('transactional', $body['msgClass']);
        $this->assertSame(0, $body['prevDups']);
    }

    public function testDlrFlagIsTrueWhenCallbackTokenConfigured(): void
    {
        $this->configure();
        $this->mockHttpPost(['msgId' => 'msg-001']);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertTrue($body['dlr']);
    }

    public function testDlrFlagIsFalseWhenCallbackTokenMissing(): void
    {
        $this->configure(['callback_token' => '']);
        $this->mockHttpPost(['msgId' => 'msg-001']);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertFalse($body['dlr']);
    }

    public function testSendReturnsProviderIdFromMsgIdKey(): void
    {
        $this->configure();
        $this->mockHttpPost(['msgId' => 'oursms-msg-42']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('oursms-msg-42', $result->providerId);
    }

    public function testSendReturnsProviderIdFromStatusesArrayShape(): void
    {
        $this->configure();
        $this->mockHttpPost(['statuses' => [['msgId' => 'queued-7']]]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued-7', $result->providerId);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['message' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid OurSMS API key', $result->error);
    }

    public function testSendReturnsFailedOn4xxWithProviderMessage(): void
    {
        $this->configure();
        $this->mockHttpPost(['errorCode' => 'BAD_SRC', 'message' => 'Sender not approved'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Sender not approved', $result->error);
    }

    public function testSendFailsWhenApiKeyMissing(): void
    {
        $this->configure(['api_key' => '']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API key', $result->error);
    }

    public function testSendFailsWhenSenderMissing(): void
    {
        $this->configure([], ['sender_id' => '']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sender', strtolower($result->error));
    }

    // --- Credit ---

    public function testGetCreditReturnsCreditsValue(): void
    {
        $this->configure();
        $this->mockHttpGet(['credits' => 123.5]);

        $this->assertSame('123.5', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenApiUnreachable(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'down');

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'userId'   => 'u-1',
            'username' => 'foad',
            'email'    => 'foad@example.com',
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('foad', $result->details['username']);
        $this->assertSame('foad@example.com', $result->details['email']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = ['oursms' => ['shared' => []]];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key', $result->message);
    }

    // --- Status callback ---

    public function testStatusCallbackUrlEmbedsToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();

        $this->assertStringContainsString('callbacks/oursms/status', $url);
        $this->assertStringContainsString('token=' . self::CALLBACK_TOKEN, $url);
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();

        $request = new \WP_REST_Request('POST', '/x');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();

        $request = new \WP_REST_Request('POST', '/x');
        $request->set_param('token', 'wrong-token');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenSecretUnconfigured(): void
    {
        $this->configure(['callback_token' => '']);

        $request = new \WP_REST_Request('POST', '/x');
        $request->set_param('token', 'anything');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();

        $request = new \WP_REST_Request('POST', '/x');
        $request->set_param('token', self::CALLBACK_TOKEN);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsDeliveredAndUndelivered(): void
    {
        $this->configure();

        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode([
            'statuses' => [
                ['msgId' => 'm1', 'status' => 'delivered',   'src' => 'OurSms', 'dest' => '966500000001'],
                ['msgId' => 'm2', 'status' => 'undelivered', 'src' => 'OurSms', 'dest' => '966500000002'],
            ],
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(2, $updates);
        $this->assertSame('m1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);

        $this->assertSame('m2', $updates[1]->providerId);
        $this->assertSame('failed', $updates[1]->status);
        $this->assertTrue($updates[1]->permanent);
        $this->assertStringContainsString('undelivered', $updates[1]->errorMessage);
    }

    public function testParseStatusCallbackIgnoresMalformedRows(): void
    {
        $this->configure();

        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode([
            'statuses' => [
                ['msgId' => '', 'status' => 'delivered'],
                ['msgId' => 'm3', 'status' => ''],
                'not-an-object',
                ['msgId' => 'm4', 'status' => 'delivered'],
            ],
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('m4', $updates[0]->providerId);
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsReturnsSendersFromAddressesSrcs(): void
    {
        $config = [
            'shared'   => ['api_key' => self::API_KEY],
            'channels' => ['sms' => []],
        ];
        $GLOBALS['_test_wp_remote_get'] = function ($url) {
            $this->assertStringStartsWith('https://api.oursms.com/addresses/srcs', $url);
            $this->assertStringContainsString('count=200', $url);
            return [
                'body'     => json_encode(['srcs' => [['src' => 'BrandA'], ['src' => 'BrandB']]]),
                'response' => ['code' => 200],
            ];
        };

        $options = $this->createProvider()->getConfigOptions('sender_id', 'sms', $config);

        $this->assertSame([
            ['value' => 'BrandA', 'label' => 'BrandA'],
            ['value' => 'BrandB', 'label' => 'BrandB'],
        ], $options);
    }

    public function testGetConfigOptionsAcceptsPlainStringRows(): void
    {
        $config = ['shared' => ['api_key' => self::API_KEY], 'channels' => ['sms' => []]];
        $this->mockHttpGet(['data' => ['SenderOne', 'SenderTwo']]);

        $options = $this->createProvider()->getConfigOptions('sender_id', 'sms', $config);

        $this->assertSame([
            ['value' => 'SenderOne', 'label' => 'SenderOne'],
            ['value' => 'SenderTwo', 'label' => 'SenderTwo'],
        ], $options);
    }

    public function testGetConfigOptionsReturnsEmptyOnApiError(): void
    {
        $config = ['shared' => ['api_key' => self::API_KEY], 'channels' => ['sms' => []]];
        $this->mockHttpGet(['error' => 'unauthorized'], 401);

        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_id', 'sms', $config));
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('something_else', 'sms', []));
    }

    public function testGetConfigOptionsReturnsEmptyWithoutApiKey(): void
    {
        $config = ['shared' => [], 'channels' => ['sms' => []]];
        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_id', 'sms', $config));
    }
}
