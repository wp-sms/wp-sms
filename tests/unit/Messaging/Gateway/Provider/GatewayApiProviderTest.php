<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\GatewayApiProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class GatewayApiProviderTest extends AbstractProviderTestCase
{
    private const API_TOKEN     = 'test-api-token';
    private const SMS_SENDER    = 'WSms';
    private const RCS_SENDER    = 'WSmsBrand';
    private const WEBHOOK_KEY   = 'webhook-shared-secret';
    private const RECIPIENT_E164 = '+15551234567';
    private const RECIPIENT_INT  = 15551234567;

    protected function createProvider(): AbstractProvider
    {
        return new GatewayApiProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gatewayapi' => [
                'shared' => array_merge([
                    'api_token'      => self::API_TOKEN,
                    'region'         => 'com',
                    'webhook_secret' => self::WEBHOOK_KEY,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms' => ['sender' => self::SMS_SENDER, 'priority' => 'normal'],
                    'rcs' => ['sender' => self::RCS_SENDER],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = self::RECIPIENT_E164, string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 202): void
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

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('gatewayapi', $p->getId());
        $this->assertSame(['sms', 'rcs'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(GatewayApiProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue($schema['shared']['api_token']['required']);

        $this->assertArrayHasKey('region', $schema['shared']);
        $this->assertSame('select', $schema['shared']['region']['type']);
        $this->assertSame('com', $schema['shared']['region']['default']);

        $this->assertArrayHasKey('webhook_secret', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['webhook_secret']['type']);

        $this->assertArrayHasKey('sender', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender']['required']);
        $this->assertArrayHasKey('priority', $schema['channels']['sms']);

        $this->assertArrayHasKey('sender', $schema['channels']['rcs']);
        $this->assertTrue($schema['channels']['rcs']['sender']['required']);
    }

    public function testIsConfiguredRequiresTokenAndChannelSender(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gatewayapi' => [
                'shared'   => ['api_token' => '', 'region' => 'com'],
                'channels' => ['sms' => ['sender' => self::SMS_SENDER]],
            ],
        ];
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gatewayapi' => [
                'shared'   => ['api_token' => self::API_TOKEN, 'region' => 'com'],
                'channels' => ['sms' => []],
            ],
        ];
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('rcs'));
    }

    // --- Send: SMS ---

    public function testSmsSendBuildsExpectedBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['msg_id' => '01JNN696A9E0WS89FPYGT15NBX', 'recipient' => self::RECIPIENT_INT, 'reference' => null]);

        $this->createProvider()->send($this->createMessage('sms', self::RECIPIENT_E164, 'Hi there'));

        $this->assertSame(
            'https://messaging.gatewayapi.com/mobile/single',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Token ' . self::API_TOKEN, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::SMS_SENDER, $body['sender']);
        $this->assertSame(self::RECIPIENT_INT, $body['recipient']);
        $this->assertIsInt($body['recipient']);
        $this->assertSame('Hi there', $body['message']);
        $this->assertArrayNotHasKey('priority', $body);
    }

    public function testSmsSendIncludesPriorityWhenUrgent(): void
    {
        $this->configure(channelOverrides: ['sms' => ['sender' => self::SMS_SENDER, 'priority' => 'urgent']]);
        $this->mockHttpPost(['msg_id' => 'm1', 'recipient' => self::RECIPIENT_INT, 'reference' => null]);

        $this->createProvider()->send($this->createMessage('sms'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('urgent', $body['priority']);
    }

    public function testRcsChannelUsesRcsSenderAndOmitsPriority(): void
    {
        $this->configure();
        $this->mockHttpPost(['msg_id' => 'rcs-1', 'recipient' => self::RECIPIENT_INT, 'reference' => null]);

        $this->createProvider()->send($this->createMessage('rcs', self::RECIPIENT_E164, 'Rich hello'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::RCS_SENDER, $body['sender']);
        $this->assertSame('Rich hello', $body['message']);
        $this->assertArrayNotHasKey('priority', $body);
    }

    public function testRegionEuChangesBaseUrl(): void
    {
        $this->configure(sharedOverrides: ['region' => 'eu']);
        $this->mockHttpPost(['msg_id' => 'eu-1', 'recipient' => self::RECIPIENT_INT, 'reference' => null]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://messaging.gatewayapi.eu/mobile/single',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testSendStripsNonDigitsFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost(['msg_id' => 'm1', 'recipient' => 4512345678, 'reference' => null]);

        $this->createProvider()->send($this->createMessage('sms', '+45 12 34 56 78'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(4512345678, $body['recipient']);
    }

    public function testSendQueuedReturnsMsgIdAsProviderId(): void
    {
        $this->configure();
        $this->mockHttpPost(['msg_id' => '01JNN696A9E0WS89FPYGT15NBX', 'recipient' => self::RECIPIENT_INT, 'reference' => null]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('01JNN696A9E0WS89FPYGT15NBX', $result->providerId);
    }

    public function testSendIncludesReferenceFromFlowExecutionId(): void
    {
        $this->configure();
        $this->mockHttpPost(['msg_id' => 'm1', 'recipient' => self::RECIPIENT_INT, 'reference' => null]);

        $msg = new Message('sms', self::RECIPIENT_E164, 'Body', 'flow-exec-99', []);
        $this->createProvider()->send($msg);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('flow-exec-99', $body['reference']);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['detail' => 'unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedOnValidationError(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'detail' => [
                ['loc' => ['body', 'sender'], 'msg' => 'String should have at least 3 characters', 'type' => 'string_too_short'],
            ],
        ], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sender', $result->error);
        $this->assertStringContainsString('at least 3', $result->error);
    }

    public function testSendReturnsFailedWhenTokenMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Token', $result->error);
    }

    public function testSendReturnsFailedWhenSenderMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gatewayapi' => [
                'shared'   => ['api_token' => self::API_TOKEN, 'region' => 'com'],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditUsesLegacyMeEndpointRegardlessOfRegion(): void
    {
        $this->configure(sharedOverrides: ['region' => 'eu']);

        $captured = [];
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode(['credit' => '1234.56', 'currency' => 'DKK', 'id' => 1]),
                'response' => ['code' => 200],
            ];
        };

        $credit = $this->createProvider()->getCredit();

        $this->assertSame('1234.56 DKK', $credit);
        $this->assertSame('https://gatewayapi.com/rest/me', $captured['url']);
        $this->assertSame('Token ' . self::API_TOKEN, $captured['args']['headers']['Authorization']);
    }

    public function testGetCreditReturnsNullWhenNoToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnHttpError(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauth'], 401);
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $this->mockHttpGet(['credit' => '500.00', 'currency' => 'EUR', 'id' => 42]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500.00', $result->message);
        $this->assertStringContainsString('EUR', $result->message);
        $this->assertSame('500.00', $result->details['balance']);
    }

    public function testTestConnectionInvalidToken(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'invalid'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback signature ---

    public function testValidateStatusCallbackAcceptsCorrectHmac(): void
    {
        $this->configure();
        $body = json_encode(['event_type' => 'message.status.sms', 'event' => ['msg_id' => 'x']]);
        $hex  = hash_hmac('sha256', $body, self::WEBHOOK_KEY);

        $request = $this->buildRequest($body, ['signature' => 'v1=' . $hex]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongSignature(): void
    {
        $this->configure();
        $request = $this->buildRequest('{"foo":"bar"}', ['signature' => 'v1=deadbeef']);
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingV1Prefix(): void
    {
        $this->configure();
        $body = '{"foo":"bar"}';
        $hex  = hash_hmac('sha256', $body, self::WEBHOOK_KEY);
        $request = $this->buildRequest($body, ['signature' => $hex]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenSecretMissing(): void
    {
        $this->configure(sharedOverrides: ['webhook_secret' => '']);
        $body = '{"foo":"bar"}';
        // Even a structurally valid signature is rejected when no secret is set.
        $request = $this->buildRequest($body, ['signature' => 'v1=' . hash_hmac('sha256', $body, 'anything')]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingHeader(): void
    {
        $this->configure();
        $request = $this->buildRequest('{"foo":"bar"}', []);
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    // --- Status callback parsing ---

    public function testParseStatusCallbackMapsAllSmsStatuses(): void
    {
        $cases = [
            'ACCEPTED'      => ['sent',      false],
            'ENROUTE'       => ['sent',      false],
            'DELIVERED'     => ['delivered', false],
            'EXPIRED'       => ['failed',    false],
            'UNDELIVERABLE' => ['failed',    true],
            'REJECTED'      => ['failed',    true],
            'DELETED'       => ['failed',    true],
            'UNKNOWN'       => ['unknown',   false],
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => [$expected, $expectedPermanent]) {
            $body = json_encode([
                'event_type' => 'message.status.sms',
                'event'      => ['msg_id' => 'msg-' . $raw, 'recipient' => 1, 'status' => $raw],
            ]);
            $request = $this->buildRequest($body, []);

            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expected, $updates[0]->status, "wrong mapping for {$raw}");
            $this->assertSame($expectedPermanent, $updates[0]->permanent, "wrong permanent for {$raw}");
        }
    }

    public function testParseStatusCallbackMapsAllRcsStatuses(): void
    {
        $cases = [
            'ENROUTE'   => 'sent',
            'DELIVERED' => 'delivered',
            'READ'      => 'delivered',
            'EXPIRED'   => 'failed',
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => $expected) {
            $body = json_encode([
                'event_type' => 'message.status.rcs',
                'event'      => ['msg_id' => 'rcs-' . $raw, 'recipient' => 1, 'status' => $raw],
            ]);
            $request = $this->buildRequest($body, []);

            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for RCS {$raw}");
            $this->assertSame($expected, $updates[0]->status, "wrong RCS mapping for {$raw}");
        }
    }

    public function testParseStatusCallbackPropagatesErrorHexCodeAndDetails(): void
    {
        $body = json_encode([
            'event_type' => 'message.status.sms',
            'event'      => [
                'msg_id'    => '01JQRJWK259Y1YEECJZB50908V',
                'recipient' => self::RECIPIENT_INT,
                'status'    => 'REJECTED',
                'error'     => ['hex_code' => '0x1905', 'details' => 'Unsupported sender'],
            ],
        ]);
        $request = $this->buildRequest($body, []);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertTrue($updates[0]->permanent);
        $this->assertSame('0x1905', $updates[0]->errorCode);
        $this->assertSame('Unsupported sender', $updates[0]->errorMessage);
        $this->assertSame('01JQRJWK259Y1YEECJZB50908V', $updates[0]->providerId);
    }

    public function testParseStatusCallbackEmptyForMalformedPayload(): void
    {
        $request = $this->buildRequest('not json', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackEmptyWhenMsgIdMissing(): void
    {
        $body = json_encode([
            'event_type' => 'message.status.sms',
            'event'      => ['status' => 'DELIVERED'],
        ]);
        $request = $this->buildRequest($body, []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Helpers ---

    /**
     * Build a WP_REST_Request whose get_body() returns the raw bytes WSMS
     * needs for HMAC verification (the bootstrap stub doesn't expose this).
     */
    private function buildRequest(string $body, array $headers): \WP_REST_Request
    {
        return new class('POST', '/wsms/v1/callbacks/gatewayapi/status', $body, $headers) extends \WP_REST_Request {
            private string $rawBody;

            public function __construct(string $method, string $route, string $body, array $headers)
            {
                parent::__construct($method, $route);
                $this->rawBody = $body;
                foreach ($headers as $k => $v) {
                    $this->set_header($k, $v);
                }
            }

            public function get_body(): string
            {
                return $this->rawBody;
            }

            public function get_method(): string
            {
                return 'POST';
            }
        };
    }
}
