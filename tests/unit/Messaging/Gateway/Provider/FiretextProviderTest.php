<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\FiretextProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class FiretextProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'firetext-api-key-abc';
    private const FROM    = 'WPSMS';

    protected function createProvider(): AbstractProvider
    {
        return new FiretextProvider();
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

    private function configureProvider(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $shared = array_merge([
            'api_key' => self::API_KEY,
        ], $sharedOverrides);

        $sms = array_merge([
            'from_number' => self::FROM,
            'unicode'     => false,
        ], $smsOverrides);

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'firetext' => [
                'shared'   => $shared,
                'channels' => ['sms' => $sms],
            ],
        ];
    }

    private function message(): Message
    {
        return new Message('sms', '447700900123', 'Hello Firetext');
    }

    private function mockSendResponse(string $body, int $statusCode = 200, array $headers = []): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
            'headers'  => array_change_key_case($headers, CASE_LOWER),
        ];
    }

    private function buildRequest(array $params = []): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/firetext/status');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        return $request;
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(FiretextProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $provider = $this->createProvider();
        $this->assertSame('firetext', $provider->getId());
        $this->assertSame(['sms'], $provider->getSupportedChannels());
    }

    public function testConfigSchemaShapeAndRequiredFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['callback_token']['type']);
        $this->assertFalse($schema['shared']['callback_token']['required']);

        $this->assertArrayHasKey('from_number', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from_number']['required']);
        $this->assertArrayHasKey('unicode', $schema['channels']['sms']);
        $this->assertSame('boolean', $schema['channels']['sms']['unicode']['type']);
    }

    public function testIsConfiguredFalseWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'firetext' => [
                'shared'   => [],
                'channels' => ['sms' => ['from_number' => self::FROM]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testSendSuccessReturnsSentWithMessageId(): void
    {
        $this->configureProvider();
        $this->mockSendResponse("0:1 Test", 200, ['X-Message' => 'ft-msg-99']);

        $result = $this->createProvider()->send($this->message());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('ft-msg-99', $result->providerId);
    }

    public function testSendPostsFormEncodedBodyToCorrectUrl(): void
    {
        $this->configureProvider(smsOverrides: ['unicode' => true]);
        $this->mockSendResponse("0:1 OK", 200, ['X-Message' => 'id-1']);

        $this->createProvider()->send($this->message());

        $this->assertSame('https://www.firetext.co.uk/api/sendsms', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $form);
        $this->assertSame(self::API_KEY, $form['apiKey']);
        $this->assertSame(self::FROM, $form['from']);
        $this->assertSame('447700900123', $form['to']);
        $this->assertSame('Hello Firetext', $form['message']);
        $this->assertSame('1', $form['unicode']);
    }

    public function testSendDefaultsUnicodeToZeroWhenDisabled(): void
    {
        $this->configureProvider();
        $this->mockSendResponse("0:1 OK", 200, ['X-Message' => 'id-2']);

        $this->createProvider()->send($this->message());

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('0', $form['unicode']);
    }

    public function testSendFailsOnAuthError(): void
    {
        $this->configureProvider();
        $this->mockSendResponse("1:0 Authentication failed");

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API key', $result->error);
    }

    public function testSendFailsOnInsufficientCredit(): void
    {
        $this->configureProvider();
        $this->mockSendResponse("7:0 Insufficient credit");

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient credit', $result->error);
    }

    public function testSendFailsOnUnknownErrorCode(): void
    {
        $this->configureProvider();
        $this->mockSendResponse("99:0 Some new failure mode");

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('99', $result->error);
        $this->assertStringContainsString('Some new failure mode', $result->error);
    }

    public function testSendFailsOnNon2xxResponse(): void
    {
        $this->configureProvider();
        $this->mockSendResponse('Internal error', 500);

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertSame(500, $result->meta['firetext_http_code']);
    }

    public function testSendFailsWhenChannelFromMissing(): void
    {
        $this->configureProvider(smsOverrides: ['from_number' => '']);
        $this->mockSendResponse("0:1 OK", 200, ['X-Message' => 'never']);

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    public function testSendFailsWhenSharedApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'firetext' => [
                'shared'   => [],
                'channels' => ['sms' => ['from_number' => self::FROM]],
            ],
        ];

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key', $result->error);
    }

    public function testGetCreditReturnsBalanceWhenConfigured(): void
    {
        $this->configureProvider();
        $this->mockSendResponse('0:42');

        $this->assertSame('42 credits', $this->createProvider()->getCredit());
        $this->assertSame('https://www.firetext.co.uk/api/credit', $GLOBALS['_test_wp_remote_post_last_url']);
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configureProvider();
        $this->mockSendResponse('1:0 Auth failed');

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionOkWhenAuthValid(): void
    {
        $this->configureProvider();
        $this->mockSendResponse('0:42');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('42', $result->details['balance']);
        $this->assertStringContainsString('42', $result->message);
    }

    public function testTestConnectionErrorWhenApiKeyInvalid(): void
    {
        $this->configureProvider();
        $this->mockSendResponse('1:0 Auth failed');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Firetext API Key', $result->message);
    }

    public function testTestConnectionOkWhenAuthValidButOutOfCredit(): void
    {
        $this->configureProvider();
        $this->mockSendResponse('7:0 Insufficient credit');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('insufficient credit', $result->message);
    }

    public function testValidateStatusCallbackRejectsWhenTokenAbsent(): void
    {
        $this->configureProvider(sharedOverrides: ['callback_token' => 'shh']);
        $provider = $this->createProvider();

        $this->assertFalse($provider->validateStatusCallback($this->buildRequest()));
        $this->assertFalse($provider->validateStatusCallback($this->buildRequest(['token' => 'wrong'])));
    }

    public function testValidateStatusCallbackRejectsByDefaultWhenNoTokenConfigured(): void
    {
        $this->configureProvider();
        $provider = $this->createProvider();

        $this->assertFalse($provider->validateStatusCallback($this->buildRequest(['token' => 'any'])));
    }

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configureProvider(sharedOverrides: ['callback_token' => 'shh']);
        $provider = $this->createProvider();

        $this->assertTrue($provider->validateStatusCallback($this->buildRequest(['token' => 'shh'])));
        $this->assertStringContainsString('token=shh', $provider->getStatusCallbackUrl());
    }

    public function testParseStatusCallbackMapsDeliveredAndFailedStatuses(): void
    {
        $this->configureProvider();
        $provider = $this->createProvider();

        $delivered = $provider->parseStatusCallback($this->buildRequest([
            'id'     => 'msg-1',
            'status' => 'DELIVERED',
        ]));
        $this->assertCount(1, $delivered);
        $this->assertSame('delivered', $delivered[0]->status);
        $this->assertFalse($delivered[0]->permanent);

        $failed = $provider->parseStatusCallback($this->buildRequest([
            'id'     => 'msg-2',
            'status' => 'rejected',
            'reason' => 'blacklisted',
        ]));
        $this->assertCount(1, $failed);
        $this->assertSame('failed', $failed[0]->status);
        $this->assertTrue($failed[0]->permanent);
        $this->assertSame('blacklisted', $failed[0]->errorCode);
    }

    public function testParseStatusCallbackReturnsEmptyOnMissingFields(): void
    {
        $this->configureProvider();
        $this->assertSame([], $this->createProvider()->parseStatusCallback($this->buildRequest([])));
        $this->assertSame([], $this->createProvider()->parseStatusCallback($this->buildRequest(['id' => 'x'])));
    }

    public function testParseInboundCallbackExtractsFromToBody(): void
    {
        $this->configureProvider();

        $messages = $this->createProvider()->parseInboundCallback($this->buildRequest([
            'id'       => 'mo-1',
            'from'     => '447700900123',
            'to'       => self::FROM,
            'message'  => 'STOP',
            'received' => '2026-05-09 12:00:00',
        ]));

        $this->assertCount(1, $messages);
        $this->assertSame('447700900123', $messages[0]->from);
        $this->assertSame(self::FROM, $messages[0]->to);
        $this->assertSame('STOP', $messages[0]->body);
        $this->assertSame('mo-1', $messages[0]->providerId);
        $this->assertSame('2026-05-09 12:00:00', $messages[0]->meta['received']);
    }

    public function testInboundCallbackTokenValidation(): void
    {
        $this->configureProvider(sharedOverrides: ['callback_token' => 'shh']);
        $provider = $this->createProvider();

        $this->assertFalse($provider->validateInboundCallback($this->buildRequest(['token' => 'wrong'])));
        $this->assertTrue($provider->validateInboundCallback($this->buildRequest(['token' => 'shh'])));
        $this->assertStringContainsString('token=shh', $provider->getInboundCallbackUrl());
    }
}
