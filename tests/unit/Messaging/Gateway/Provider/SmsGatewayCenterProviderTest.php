<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsGatewayCenterProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsGatewayCenterProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'test-api-key-xyz';
    private const USER_ID = 'demo-user';
    private const PASSWORD = 'demo-pass';
    private const SENDER_ID = 'SMSGAT';
    private const WEBHOOK_TOKEN = 'wt-secret';

    protected function createProvider(): AbstractProvider
    {
        return new SmsGatewayCenterProvider();
    }

    private function configureWithApiKey(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsgatewaycenter' => [
                'shared' => array_merge([
                    'auth_method'   => 'api_key',
                    'api_key'       => self::API_KEY,
                    'webhook_token' => self::WEBHOOK_TOKEN,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms' => ['sender_id' => self::SENDER_ID, 'duplicate_check' => true],
                    'rcs' => ['sender_id' => self::SENDER_ID],
                ], $channelOverrides),
            ],
        ];
    }

    private function configureWithCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsgatewaycenter' => [
                'shared' => [
                    'auth_method' => 'credentials',
                    'user_id'     => self::USER_ID,
                    'password'    => self::PASSWORD,
                ],
                'channels' => [
                    'sms' => ['sender_id' => self::SENDER_ID, 'duplicate_check' => true],
                ],
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '919999999999', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 200): void
    {
        $body = is_string($responseBody) ? $responseBody : json_encode($responseBody);
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    private function lastBody(): array
    {
        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'] ?? '', $parsed);
        return $parsed;
    }

    private function lastHeaders(): array
    {
        return $GLOBALS['_test_wp_remote_post_last_args']['headers'] ?? [];
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smsgatewaycenter', $p->getId());
        $this->assertSame(['sms', 'rcs'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmsGatewayCenterProvider::TESTED);
    }

    public function testConfigSchemaUsesShowIfForCredentialFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('select', $schema['shared']['auth_method']['type']);
        $this->assertSame(['field' => 'auth_method', 'equals' => 'api_key'], $schema['shared']['api_key']['show_if']);
        $this->assertSame(['field' => 'auth_method', 'equals' => 'credentials'], $schema['shared']['user_id']['show_if']);
        $this->assertSame(['field' => 'auth_method', 'equals' => 'credentials'], $schema['shared']['password']['show_if']);
        $this->assertArrayHasKey('webhook_token', $schema['shared']);
    }

    public function testIsConfiguredAcceptsCredentialsWithoutApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsgatewaycenter' => [
                'shared' => [
                    'auth_method' => 'credentials',
                    'user_id'     => self::USER_ID,
                    'password'    => self::PASSWORD,
                ],
                'channels' => ['sms' => ['sender_id' => self::SENDER_ID]],
            ],
        ];

        $this->assertTrue($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredAcceptsApiKeyWithoutUserIdPassword(): void
    {
        $this->configureWithApiKey();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredFalseWhenSelectedAuthMethodIncomplete(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsgatewaycenter' => [
                'shared'   => ['auth_method' => 'credentials', 'user_id' => self::USER_ID],
                'channels' => ['sms' => ['sender_id' => self::SENDER_ID]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testValidateConfigSkipsHiddenRequiredFields(): void
    {
        $p = $this->createProvider();

        $this->assertTrue($p->validateConfig([
            'shared' => [
                'auth_method' => 'credentials',
                'user_id'     => 'u',
                'password'    => 'p',
            ],
        ]));

        $this->assertTrue($p->validateConfig([
            'shared' => [
                'auth_method' => 'api_key',
                'api_key'     => 'k',
            ],
        ]));
    }

    public function testValidateConfigUsesDefaultAuthMethodForVisibility(): void
    {
        // Without an explicit auth_method the field's default ('api_key') drives
        // show_if: api_key is treated as visible/required while user_id and
        // password are correctly hidden and not required.
        $p = $this->createProvider();

        $this->assertTrue($p->validateConfig([
            'shared' => ['auth_method' => 'api_key', 'api_key' => 'k'],
        ]));
        $this->assertFalse($p->validateConfig(['shared' => ['auth_method' => 'api_key']]));
    }

    // --- Send: SMS with API key auth ---

    public function testSendSmsWithApiKeyPostsFormEncodedBodyAndApikeyHeader(): void
    {
        $this->configureWithApiKey();
        $this->mockHttpPost([
            'status'        => 'success',
            'transactionId' => 'txn-abc-001',
            'statusCode'    => '900',
            'reason'        => 'success',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('txn-abc-001', $result->providerId);
        $this->assertSame(
            'https://www.smsgateway.center/SMSApi/rest/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $this->assertSame('application/x-www-form-urlencoded', $this->lastHeaders()['Content-Type']);
        $this->assertSame(self::API_KEY, $this->lastHeaders()['apikey']);

        $body = $this->lastBody();
        $this->assertSame('simpleMsg', $body['sendMethod']);
        $this->assertSame('919999999999', $body['mobile']);
        $this->assertSame(self::SENDER_ID, $body['senderId']);
        $this->assertSame('text', $body['msgType']);
        $this->assertSame('Hello', $body['msg']);
        $this->assertSame('json', $body['format']);
        $this->assertSame('true', $body['duplicateCheck']);
        $this->assertArrayNotHasKey('flashMsg', $body);
        $this->assertArrayNotHasKey('userId', $body);
        $this->assertArrayNotHasKey('password', $body);
    }

    public function testSendSmsWithCredentialsPutsUserIdPasswordInBody(): void
    {
        $this->configureWithCredentials();
        $this->mockHttpPost([
            'status'        => 'success',
            'transactionId' => 'txn-cred-001',
            'statusCode'    => '900',
        ]);

        $this->createProvider()->send($this->createMessage());

        $headers = $this->lastHeaders();
        $this->assertArrayNotHasKey('apikey', $headers);

        $body = $this->lastBody();
        $this->assertSame(self::USER_ID, $body['userId']);
        $this->assertSame(self::PASSWORD, $body['password']);
    }

    public function testSendDetectsUnicodeBody(): void
    {
        $this->configureWithApiKey();
        $this->mockHttpPost(['status' => 'success', 'transactionId' => 't-1']);

        $this->createProvider()->send($this->createMessage('sms', '919999999999', 'नमस्ते - hello'));

        $this->assertSame('unicode', $this->lastBody()['msgType']);
    }

    public function testSendHonorsFlashSmsMeta(): void
    {
        $this->configureWithApiKey();
        $this->mockHttpPost(['status' => 'success', 'transactionId' => 't-2']);

        $this->createProvider()->send($this->createMessage('sms', '919999999999', 'Flash!', ['flash_sms' => true]));

        $this->assertSame('true', $this->lastBody()['flashMsg']);
    }

    public function testSendHonorsDuplicateCheckChannelOverride(): void
    {
        $this->configureWithApiKey([], ['sms' => ['sender_id' => self::SENDER_ID, 'duplicate_check' => false]]);
        $this->mockHttpPost(['status' => 'success', 'transactionId' => 't-3']);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('false', $this->lastBody()['duplicateCheck']);
    }

    public function testSendNormalizesMsisdn(): void
    {
        $this->configureWithApiKey();
        $this->mockHttpPost(['status' => 'success', 'transactionId' => 't-4']);

        $this->createProvider()->send($this->createMessage('sms', '+91 99999-99999'));

        $this->assertSame('919999999999', $this->lastBody()['mobile']);
    }

    public function testSendFailureReturnsReasonAndCapturesErrorCode(): void
    {
        $this->configureWithApiKey();
        $this->mockHttpPost([
            'status'     => 'error',
            'statusCode' => '37',
            'reason'     => 'Non Optin',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Non Optin', $result->error);
        $this->assertSame('37', $result->meta['sgc_error_code']);
    }

    public function testSendFailsWithoutApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsgatewaycenter' => [
                'shared'   => ['auth_method' => 'api_key'],
                'channels' => ['sms' => ['sender_id' => self::SENDER_ID]],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API key', $result->error);
    }

    public function testSendFailsWithoutSenderId(): void
    {
        $this->configureWithApiKey([], ['sms' => []]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    // --- Send: RCS ---

    public function testSendRcsPostsToRcsEndpointWithTextMode(): void
    {
        $this->configureWithApiKey();
        $this->mockHttpPost([
            'status'        => 'success',
            'transactionId' => 'rcs-1',
            'statusCode'    => '200',
        ]);

        $this->createProvider()->send($this->createMessage('rcs', '919999999999', 'Hi RCS'));

        $this->assertSame('https://www.smsgateway.center/RCSApi/send', $GLOBALS['_test_wp_remote_post_last_url']);
        $body = $this->lastBody();
        $this->assertSame('quick', $body['sendMethod']);
        $this->assertSame('text', $body['msgType']);
        $this->assertSame('Hi RCS', $body['msg']);
    }

    // --- Templates ---

    public function testRequiresTemplateForChannelReturnsFalse(): void
    {
        $p = $this->createProvider();
        $this->assertFalse($p->requiresTemplateForChannel('sms'));
        $this->assertFalse($p->requiresTemplateForChannel('rcs'));
    }

    public function testVariableStyleIsNamed(): void
    {
        $this->assertSame(VariableStyle::Named, $this->createProvider()->getVariableStyle());
    }

    public function testBuildTemplatePayloadInterpolatesBody(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'tpl-1',
            gatewayId: 'smsgatewaycenter',
            language: 'en',
            variableMap: ['otp_code' => 'code'],
            providerTemplateBody: 'Your OTP is {{code}}. Valid for 5 minutes.',
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, ['code' => '482916']);

        $this->assertSame('Your OTP is 482916. Valid for 5 minutes.', $payload['msg']);
    }

    // --- getCredit / testConnection ---

    public function testGetCreditReturnsSmsBalance(): void
    {
        $this->configureWithApiKey();
        $this->mockHttpPost(['smsBalance' => '125', 'status' => 'success']);

        $this->assertSame('125', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenAuthMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configureWithApiKey();
        $this->mockHttpPost(['status' => 'error', 'reason' => 'unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionReturnsOkForProfile200(): void
    {
        $this->configureWithApiKey();
        $this->mockHttpPost(['status' => 'success', 'data' => ['username' => 'demo']]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configureWithApiKey();
        $request = $this->buildRequest('POST', '/x', ['token' => self::WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configureWithApiKey();
        $request = $this->buildRequest('POST', '/x', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configureWithApiKey();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenNoTokenConfigured(): void
    {
        $this->configureWithApiKey(['webhook_token' => '']);
        $request = $this->buildRequest('POST', '/x', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsDeliveredCode(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'transId'   => 'txn-1',
            'msgId'     => 'msg-1',
            'errorCode' => '1',
            'mobile'    => '919999999999',
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(1, $updates);
        $this->assertSame('msg-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testParseStatusCallbackMarksOptOutForCode37(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'transId'   => 'txn-2',
            'msgId'     => 'msg-2',
            'errorCode' => '37',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertTrue($update->unsubscribe);
    }

    public function testParseStatusCallbackMarksDndCode7AsOptOut(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'msgId'     => 'msg-3',
            'errorCode' => '7',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertTrue($update->permanent);
        $this->assertTrue($update->unsubscribe);
    }

    public function testParseStatusCallbackEmptyForMissingIds(): void
    {
        $request = $this->buildRequest('POST', '/x', ['errorCode' => '1']);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'phonecode' => '12345',
            'keyword'   => 'STOP',
            'phoneno'   => '919999999999',
            'content'   => 'STOP please',
            'location'  => 'Mumbai',
            'carrier'   => 'Airtel',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);
        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('919999999999', $msg->from);
        $this->assertSame('12345', $msg->to);
        $this->assertSame('STOP please', $msg->body);
        $this->assertSame('STOP', $msg->meta['keyword']);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildRequest('POST', '/x', []);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorTrueForCode37(): void
    {
        $result = \WSms\Messaging\Contracts\DeliveryResult::failed('Non Optin', ['sgc_error_code' => '37']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorTrueForDndCode7(): void
    {
        $result = \WSms\Messaging\Contracts\DeliveryResult::failed('NCPR Fail', ['sgc_error_code' => '7']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForOther(): void
    {
        $result = \WSms\Messaging\Contracts\DeliveryResult::failed('boom', ['sgc_error_code' => '17']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    // --- Dynamic options: sender ID list ---

    public function testGetConfigOptionsReturnsApprovedSenderIds(): void
    {
        $this->mockHttpPost([
            'status' => 'success',
            'data'   => [
                ['senderName' => 'SMSGAT', 'status' => 'approved'],
                ['senderName' => 'BANKXX', 'status' => 'approved'],
            ],
        ]);

        $config = [
            'shared'   => ['auth_method' => 'api_key', 'api_key' => self::API_KEY],
            'channels' => [],
        ];

        $options = $this->createProvider()->getConfigOptions('sender_id', 'sms', $config);

        $this->assertCount(2, $options);
        $this->assertSame('SMSGAT', $options[0]['value']);
        $this->assertSame('BANKXX', $options[1]['value']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('foo', 'sms', []));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = []): \WP_REST_Request
    {
        return new class($method, $route, $params, $headers) extends \WP_REST_Request {
            private string $methodOverride;

            public function __construct(string $method, string $route, array $params, array $headers)
            {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) {
                    $this->set_param($k, $v);
                }
                foreach ($headers as $k => $v) {
                    $this->set_header($k, $v);
                }
            }

            public function get_method(): string
            {
                return $this->methodOverride;
            }
        };
    }
}
