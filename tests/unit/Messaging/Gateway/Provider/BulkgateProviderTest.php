<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BulkgateProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BulkgateProviderTest extends AbstractProviderTestCase
{
    private const APP_ID    = 'bulkgate-app-id';
    private const APP_TOKEN = 'bulkgate-app-token';
    private const TOKEN     = 'callback-shared-token';

    protected function createProvider(): AbstractProvider
    {
        return new BulkgateProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulkgate' => [
                'shared' => array_merge([
                    'application_id'    => self::APP_ID,
                    'application_token' => self::APP_TOKEN,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms'      => ['sender_id' => 'gSystem'],
                    'viber'    => ['sender' => 'WSMSViber'],
                    'rcs'      => ['sender' => 'WSMSRcs'],
                    'whatsapp' => ['sender' => '420777123456'],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '420777999000', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('bulkgate', $p->getId());
        $this->assertSame(['sms', 'viber', 'rcs', 'whatsapp'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsTrueAfterManualVerification(): void
    {
        $this->assertTrue(BulkgateProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('string', $schema['shared']['application_id']['type']);
        $this->assertTrue($schema['shared']['application_id']['required']);
        $this->assertSame('secret', $schema['shared']['application_token']['type']);
        $this->assertTrue($schema['shared']['application_token']['required']);
        $this->assertSame('secret', $schema['shared']['callback_token']['type']);
        $this->assertFalse($schema['shared']['callback_token']['required'] ?? true);

        $this->assertSame('select', $schema['channels']['sms']['sender_id']['type']);
        $senderTypes = array_column($schema['channels']['sms']['sender_id']['options'], 'value');
        $this->assertSame(['gSystem', 'gShort', 'gText', 'gOwn', 'gProfile', 'gMobile', 'gPush'], $senderTypes);
        $this->assertSame('boolean', $schema['channels']['sms']['unicode']['type']);

        $this->assertTrue($schema['channels']['viber']['sender']['required']);
        $this->assertTrue($schema['channels']['rcs']['sender']['required']);
        $this->assertTrue($schema['channels']['whatsapp']['sender']['required']);
    }

    public function testFeaturesAdvertiseDlrAndIncoming(): void
    {
        $features = $this->createProvider()->getFeatures();
        $this->assertTrue($features['delivery_receipt']);
        $this->assertTrue($features['incoming']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
    }

    // --- Send: SMS ---

    public function testSmsSendQueuedReturnsSmsId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'data' => ['response' => [['id' => 'sms-id-1', 'status' => 'accepted']]],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('sms-id-1', $result->providerId);
    }

    public function testSmsSendQueuedFromSingleRecipientShape(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'sms-id-single', 'status' => 'accepted']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertSame('sms-id-single', $result->providerId);
    }

    public function testSmsSendPostsToTransactionalEndpointWithAuthInBody(): void
    {
        $this->configure(
            [],
            ['sms' => ['sender_id' => 'gText', 'sender_id_value' => 'WSMS', 'unicode' => true]],
        );
        $this->mockHttpPost(['data' => ['id' => 'x']]);

        $this->createProvider()->send($this->createMessage('sms', '420777999000', 'Hi'));

        $this->assertSame(
            'https://portal.bulkgate.com/api/2.0/advanced/transactional',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::APP_ID, $body['application_id']);
        $this->assertSame(self::APP_TOKEN, $body['application_token']);
        $this->assertSame('wsms', $body['application_product']);
        $this->assertSame('420777999000', $body['number']);
        $this->assertSame('Hi', $body['text']);
        $this->assertFalse($body['duplicates_check']);

        $this->assertSame('gText', $body['channel']['sms']['sender_id']);
        $this->assertSame('WSMS', $body['channel']['sms']['sender_id_value']);
        $this->assertTrue($body['channel']['sms']['unicode']);
    }

    public function testSmsSendOmitsSenderValueWhenNotSet(): void
    {
        $this->configure([], ['sms' => ['sender_id' => 'gSystem']]);
        $this->mockHttpPost(['data' => ['id' => 'x']]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('gSystem', $body['channel']['sms']['sender_id']);
        $this->assertArrayNotHasKey('sender_id_value', $body['channel']['sms']);
        $this->assertFalse($body['channel']['sms']['unicode']);
    }

    public function testSmsSendDefaultsToGSystemWhenSenderIdMissing(): void
    {
        $this->configure([], ['sms' => []]);
        $this->mockHttpPost(['data' => ['id' => 'x']]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('gSystem', $body['channel']['sms']['sender_id']);
    }

    // --- Send: Viber / RCS / WhatsApp ---

    public function testViberSendIncludesViberChannelBlock(): void
    {
        $this->configure([], ['viber' => ['sender' => 'BrandViber', 'expiration' => 60]]);
        $this->mockHttpPost(['data' => ['id' => 'viber-1']]);

        $this->createProvider()->send($this->createMessage('viber', '420777999000', 'Hi Viber'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayHasKey('viber', $body['channel']);
        $this->assertSame('BrandViber', $body['channel']['viber']['sender']);
        $this->assertSame(60, $body['channel']['viber']['expiration']);
        $this->assertSame('Hi Viber', $body['channel']['viber']['text']);
    }

    public function testRcsSendWrapsTextInsideMessageObject(): void
    {
        $this->configure([], ['rcs' => ['sender' => 'BrandRcs']]);
        $this->mockHttpPost(['data' => ['id' => 'rcs-1']]);

        $this->createProvider()->send($this->createMessage('rcs', '420777999000', 'Hi RCS'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('BrandRcs', $body['channel']['rcs']['sender']);
        $this->assertSame(120, $body['channel']['rcs']['expiration']);
        $this->assertSame(['text' => 'Hi RCS'], $body['channel']['rcs']['message']);
        $this->assertArrayNotHasKey('text', $body['channel']['rcs']);
    }

    public function testWhatsappSendWrapsTextInsideMessageObject(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'wa-1']]);

        $this->createProvider()->send($this->createMessage('whatsapp', '420777999000', 'Hi WA'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('420777123456', $body['channel']['whatsapp']['sender']);
        $this->assertSame(['text' => 'Hi WA'], $body['channel']['whatsapp']['message']);
    }

    public function testSendFailsWhenViberSenderMissing(): void
    {
        $this->configure([], ['viber' => []]);

        $result = $this->createProvider()->send($this->createMessage('viber'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('VIBER', $result->error);
    }

    public function testSendFailsWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'authentication_failed', 'code' => 401], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendCapturesBlacklistedNumberError(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'blacklisted_number', 'type' => 'blacklisted_number', 'code' => 400], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('blacklisted_number', $result->meta['bulkgate_error_type']);
        $this->assertSame('400', $result->meta['bulkgate_error_code']);
    }

    public function testSendBubblesUpProviderError(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'invalid_phone_number', 'type' => 'invalid_phone_number', 'code' => 400], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('invalid_phone_number', $result->error);
        $this->assertSame('invalid_phone_number', $result->meta['bulkgate_error_type']);
    }

    // --- SupportsOptOutDetection ---

    public function testIsOptOutErrorTrueForBlacklistedNumber(): void
    {
        $result = DeliveryResult::failed('blacklisted_number', ['bulkgate_error_type' => 'blacklisted_number']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForOtherTypes(): void
    {
        $result = DeliveryResult::failed('invalid', ['bulkgate_error_type' => 'invalid_phone_number']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseWhenNoMeta(): void
    {
        $this->assertFalse($this->createProvider()->isOptOutError(DeliveryResult::failed('boom')));
    }

    // --- Credit / Test connection ---

    public function testGetCreditFormatsBalanceWithCurrency(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'data' => ['credit' => 215.8138, 'currency' => 'credits', 'wallet' => 'bg1'],
        ]);

        $this->assertSame('215.8138 credits', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnAuthFailure(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'authentication_failed', 'code' => 401], 401);
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'data' => ['credit' => 42.5, 'currency' => 'credits', 'wallet' => 'bg1'],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42.5', $result->message);
        $this->assertSame('42.5', $result->details['balance']);
        $this->assertSame('credits', $result->details['currency']);
    }

    public function testTestConnectionErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'authentication_failed'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackRejectsWhenTokenNotConfigured(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('POST', '/x', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMismatchedToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('POST', '/x', ['token' => self::TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackHandlesBatchArray(): void
    {
        $request = $this->buildRequestWithJson([
            ['status' => '1', 'smsID' => 'sms-1', 'price' => '0.71', 'date' => '1904111007;1904111011'],
            ['status' => '2', 'smsID' => 'sms-2'],
            ['status' => '3', 'smsID' => 'sms-3'],
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(3, $updates);
        $this->assertSame('sms-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
        $this->assertSame('sms-2', $updates[1]->providerId);
        $this->assertSame('queued', $updates[1]->status);
        $this->assertSame('sms-3', $updates[2]->providerId);
        $this->assertSame('failed', $updates[2]->status);
        $this->assertTrue($updates[2]->permanent);
        $this->assertSame('3', $updates[2]->errorCode);
    }

    public function testParseStatusCallbackIgnoresStatus10AndStatus13(): void
    {
        $request = $this->buildRequestWithJson([
            ['status' => '1', 'smsID' => 'sms-1'],
            ['status' => '10', 'from' => '420777111111', 'message' => 'STOP'],
            ['status' => '13', 'smsID' => 'viber-seen'],
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('sms-1', $updates[0]->providerId);
    }

    public function testParseStatusCallbackEmptyForMissingPayload(): void
    {
        $request = $this->buildRequestWithJson(null);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackUsesSameToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $accept = $this->buildRequest('POST', '/x', ['token' => self::TOKEN]);
        $reject = $this->buildRequest('POST', '/x', ['token' => 'no']);

        $p = $this->createProvider();
        $this->assertTrue($p->validateInboundCallback($accept));
        $this->assertFalse($p->validateInboundCallback($reject));
    }

    public function testParseInboundCallbackKeepsOnlyStatus10(): void
    {
        $request = $this->buildRequestWithJson([
            ['status' => '1', 'smsID' => 'sms-1'],
            ['status' => '10', 'from' => '420777111111', 'to' => '420900000000', 'message' => 'STOP', 'date' => '1904111007', 'channel' => 'sms', 'smsID' => 'mo-1'],
            ['status' => '13', 'smsID' => 'viber-seen'],
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('420777111111', $msg->from);
        $this->assertSame('420900000000', $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('mo-1', $msg->providerId);
        $this->assertSame('1904111007', $msg->meta['date']);
        $this->assertSame('sms', $msg->meta['channel']);
    }

    public function testParseInboundCallbackEmptyForDlrOnlyPayload(): void
    {
        $request = $this->buildRequestWithJson([
            ['status' => '1', 'smsID' => 'sms-1'],
            ['status' => '2', 'smsID' => 'sms-2'],
        ]);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testParseInboundCallbackSkipsStatus10WithoutFrom(): void
    {
        $request = $this->buildRequestWithJson([
            ['status' => '10', 'message' => 'orphan'],
        ]);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = []): \WP_REST_Request
    {
        return new class($method, $route, $params, $headers) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, array $params, array $headers) {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) $this->set_param($k, $v);
                foreach ($headers as $k => $v) $this->set_header($k, $v);
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }

    private function buildRequestWithJson(?array $payload): \WP_REST_Request
    {
        return new class($payload) extends \WP_REST_Request {
            private array $payload;
            public function __construct(?array $payload) {
                parent::__construct('POST', '/x');
                $this->payload = $payload ?? [];
            }
            public function get_method(): string {
                return 'POST';
            }
            public function get_json_params(): array {
                return $this->payload;
            }
        };
    }
}
