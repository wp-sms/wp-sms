<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsApiProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsApiProviderTest extends AbstractProviderTestCase
{
    private const API_TOKEN      = 'smsapi-test-bearer-token';
    private const CALLBACK_TOKEN = 'webhook-secret-xyz';
    private const SENDER         = 'TestSND';

    protected function createProvider(): AbstractProvider
    {
        return new SmsApiProvider();
    }

    private function configure(string $region = 'com', array $sharedOverrides = [], array $smsChannelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsapi' => [
                'shared'   => array_merge([
                    'region'         => $region,
                    'api_token'      => self::API_TOKEN,
                    'callback_token' => self::CALLBACK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsChannelOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+48500111222', string $body = 'Hello world', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
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

    /**
     * Build a WP_REST_Request that overrides get_method() so providers reading
     * the HTTP method (GET vs POST) see the value the test set up.
     */
    private function buildRequest(string $method, array $params, array $headers = []): \WP_REST_Request
    {
        return new class($method, $params, $headers) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, array $params, array $headers)
            {
                parent::__construct($method, '/');
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

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smsapi', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmsApiProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('select', $schema['shared']['region']['type']);
        $this->assertSame('com', $schema['shared']['region']['default']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue($schema['shared']['callback_token']['required']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from']['dynamic']);
        $this->assertFalse($schema['channels']['sms']['from']['required']);
    }

    // --- Send: SMS ---

    public function testSmsSendQueuedReturnsMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'count'  => 1,
            'list'   => [['id' => 'msg-123', 'points' => 0.06, 'number' => '+48500111222', 'status' => 'QUEUE', 'idx' => '']],
            'length' => 1,
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-123', $result->providerId);
    }

    public function testSmsSendDeliveredReturnsSentWithPointsAsCost(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'list' => [['id' => 'msg-321', 'points' => 0.12, 'status' => 'DELIVERED']],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame(0.12, $result->cost);
        $this->assertSame('DELIVERED', $result->meta['smsapi_status']);
    }

    public function testSmsSendPostsToComEndpointWithBearerAuthAndFormBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['list' => [['id' => 'msg-1', 'status' => 'QUEUE']]]);

        $this->createProvider()->send($this->createMessage('+48500111222', 'Hi there'));

        $this->assertSame(
            'https://api.smsapi.com/sms.do',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::API_TOKEN, $args['headers']['Authorization']);
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $body);
        $this->assertSame('+48500111222', $body['to']);
        $this->assertSame('Hi there', $body['message']);
        $this->assertSame(self::SENDER, $body['from']);
        $this->assertSame('json', $body['format']);
        $this->assertSame('utf-8', $body['encoding']);
        $this->assertStringContainsString('callbacks/smsapi/status', $body['notify_url']);
        $this->assertStringContainsString('token=' . self::CALLBACK_TOKEN, $body['notify_url']);
    }

    public function testSmsSendUsesPolishHostWhenRegionPl(): void
    {
        $this->configure('pl');
        $this->mockHttpPost(['list' => [['id' => 'msg-pl', 'status' => 'QUEUE']]]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('https://api.smsapi.pl/sms.do', $GLOBALS['_test_wp_remote_post_last_url']);
    }

    public function testSmsSendOmitsFromWhenNotConfigured(): void
    {
        $this->configure(smsChannelOverrides: ['from' => '']);
        $this->mockHttpPost(['list' => [['id' => 'msg-1', 'status' => 'QUEUE']]]);

        $this->createProvider()->send($this->createMessage());

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertArrayNotHasKey('from', $body);
    }

    public function testSmsSendOmitsNotifyUrlWhenCallbackTokenMissing(): void
    {
        $this->configure(sharedOverrides: ['callback_token' => '']);
        $this->mockHttpPost(['list' => [['id' => 'msg-1', 'status' => 'QUEUE']]]);

        $this->createProvider()->send($this->createMessage());

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertArrayNotHasKey('notify_url', $body);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 101, 'message' => 'unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedOnSmsApiErrorEnvelope(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 13, 'message' => 'No correct phone numbers']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('No correct phone numbers', $result->error);
        $this->assertSame('13', $result->meta['smsapi_error']);
    }

    public function testSendReturnsFailedWhenApiTokenMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSmsSendBlacklistMarkedAsFailedAndOptOut(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'list' => [['id' => 'msg-bl', 'status' => 'BLACKLIST', 'number' => '+48500111222']],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('BLACKLIST', $result->meta['smsapi_status']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testSmsSendRejectedStatusMappedToFailed(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'list' => [['id' => 'msg-x', 'status' => 'REJECTED']],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('REJECTED', $result->meta['smsapi_status']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    // --- Templates ---

    public function testRequiresTemplateForChannelReturnsFalse(): void
    {
        $this->assertFalse($this->createProvider()->requiresTemplateForChannel('sms'));
    }

    public function testVariableStyleIsPositional(): void
    {
        $this->assertSame(VariableStyle::Positional, $this->createProvider()->getVariableStyle());
    }

    public function testBuildTemplatePayloadFormatsParam1ToParam4(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'auth_otp_v1',
            gatewayId: 'smsapi',
            language: 'en',
            variableMap: [],
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, ['1' => '482916', '2' => 'Acme']);

        $this->assertSame('auth_otp_v1', $payload['template']);
        $this->assertSame('482916', $payload['param1']);
        $this->assertSame('Acme', $payload['param2']);
    }

    public function testBuildTemplatePayloadCapsAtFourVariables(): void
    {
        $mapping = new TemplateMapping(
            templateType: 't',
            providerTemplateId: 'tpl',
            gatewayId: 'smsapi',
            language: 'en',
            variableMap: [],
        );
        $payload = $this->createProvider()->buildTemplatePayload(
            $mapping,
            ['1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd', '5' => 'IGNORED'],
        );

        $this->assertSame('a', $payload['param1']);
        $this->assertSame('d', $payload['param4']);
        $this->assertArrayNotHasKey('param5', $payload);
    }

    public function testSmsSendWithDirectTemplateModeUsesTemplateBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['list' => [['id' => 'msg-tpl', 'status' => 'QUEUE']]]);

        $this->createProvider()->send($this->createMessage('+48500111222', 'fallback body', [
            'template_mode'        => true,
            'provider_template_id' => 'auth_otp_v1',
            'template_variables'   => ['1' => '999111'],
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertSame('auth_otp_v1', $body['template']);
        $this->assertSame('999111', $body['param1']);
        $this->assertArrayNotHasKey('message', $body);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsFormattedPoints(): void
    {
        $this->configure();
        $this->mockHttpGet(['points' => 124.567]);

        $this->assertSame('124.57 points', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['points' => 50.5]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('50.5', $result->message);
        $this->assertSame(50.5, $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 101, 'message' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresApiToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsReturnsSenderNamesWithStatus(): void
    {
        $this->mockHttpGet([
            'collection' => [
                ['name' => 'TestSND', 'status' => 'ACTIVE',  'default' => true],
                ['name' => 'BetaSND', 'status' => 'PENDING', 'default' => false],
            ],
            'size' => 2,
        ]);

        $config = [
            'shared'   => ['region' => 'com', 'api_token' => self::API_TOKEN],
            'channels' => [],
        ];

        $options = $this->createProvider()->getConfigOptions('from', 'sms', $config);

        $this->assertCount(2, $options);
        $this->assertSame('TestSND', $options[0]['value']);
        $this->assertStringContainsString('ACTIVE', $options[0]['label']);
        $this->assertSame('BetaSND', $options[1]['value']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('unknown', 'sms', []));
    }

    public function testGetConfigOptionsReturnsEmptyWithoutToken(): void
    {
        $config = ['shared' => ['region' => 'com', 'api_token' => ''], 'channels' => []];
        $this->assertSame([], $this->createProvider()->getConfigOptions('from', 'sms', $config));
    }

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['token' => self::CALLBACK_TOKEN, 'MsgId' => 'm-1']);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['MsgId' => 'm-1']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['token' => 'totally-wrong', 'MsgId' => 'm-1']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenCallbackTokenUnset(): void
    {
        $this->configure(sharedOverrides: ['callback_token' => '']);
        $request = $this->buildRequest('GET', ['token' => 'anything', 'MsgId' => 'm-1']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsStatusNames(): void
    {
        $cases = [
            'QUEUE'                => ['queued',    false, false],
            'SENT'                 => ['sent',      false, false],
            'DELIVERED'            => ['delivered', false, false],
            'NOT_DELIVERED'        => ['failed',    false, false],
            'REJECTED'             => ['failed',    true,  false],
            'INVALID_PHONE_NUMBER' => ['failed',    true,  false],
            'BLACKLIST'            => ['failed',    true,  true],
        ];

        $p = $this->createProvider();
        foreach ($cases as $raw => [$expected, $permanent, $unsubscribe]) {
            $request = $this->buildRequest('GET', ['MsgId' => 'm-' . $raw, 'status_name' => $raw]);
            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expected, $updates[0]->status, "wrong mapping for {$raw}");
            $this->assertSame($permanent, $updates[0]->permanent, "wrong permanent flag for {$raw}");
            $this->assertSame($unsubscribe, $updates[0]->unsubscribe, "wrong unsubscribe flag for {$raw}");
        }
    }

    public function testParseStatusCallbackReadsLegacyStatusField(): void
    {
        $request = $this->buildRequest('GET', ['MsgId' => 'm-1', 'status' => 'DELIVERED']);
        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('delivered', $update->status);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('GET', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testCallbackResponseBodyIsOk(): void
    {
        $request = $this->buildRequest('GET', []);
        $body = $this->createProvider()->getCallbackResponseBody('status', $request);
        $this->assertSame('OK', $body);

        $bodyInbound = $this->createProvider()->getCallbackResponseBody('inbound', $request);
        $this->assertSame('OK', $bodyInbound);
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = $this->buildRequest('GET', [
            'sms_from' => '+48500999111',
            'sms_to'   => '+48500111222',
            'sms_text' => 'STOP',
            'sms_date' => '1700000000',
            'MsgId'    => 'in-1',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+48500999111', $messages[0]->from);
        $this->assertSame('+48500111222', $messages[0]->to);
        $this->assertSame('STOP', $messages[0]->body);
        $this->assertSame('in-1', $messages[0]->providerId);
        $this->assertSame('1700000000', $messages[0]->meta['sms_date']);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildRequest('GET', []);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- SupportsOptOutDetection ---

    public function testIsOptOutErrorTrueForBlacklistMeta(): void
    {
        $result = DeliveryResult::failed('blacklisted', ['smsapi_status' => 'BLACKLIST']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForOtherStatuses(): void
    {
        $result = DeliveryResult::failed('rejected', ['smsapi_status' => 'REJECTED']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseWhenNoStatus(): void
    {
        $result = DeliveryResult::failed('boom');
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }
}
