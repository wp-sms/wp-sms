<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\MittoProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class MittoProviderTest extends AbstractProviderTestCase
{
    private const API_KEY        = 'test-mitto-key';
    private const SENDER         = 'WSms';
    private const RECIPIENT_E164 = '+15551234567';

    protected function createProvider(): AbstractProvider
    {
        return new MittoProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mitto' => [
                'shared' => array_merge([
                    'api_key' => self::API_KEY,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms' => ['from' => self::SENDER, 'unicode' => false],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $recipient = self::RECIPIENT_E164, string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body, null, []);
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
        $this->assertSame('mitto', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsTrueAfterManualVerification(): void
    {
        $this->assertTrue(MittoProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);

        $this->assertArrayHasKey('unicode', $schema['channels']['sms']);
        $this->assertSame('boolean', $schema['channels']['sms']['unicode']['type']);
    }

    public function testIsConfiguredRequiresApiKeyAndSender(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mitto' => ['shared' => ['api_key' => ''], 'channels' => ['sms' => ['from' => self::SENDER]]],
        ];
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mitto' => ['shared' => ['api_key' => self::API_KEY], 'channels' => ['sms' => []]],
        ];
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send ---

    public function testSmsSendBuildsExpectedBody(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'id' => 'msg-001',
            'responseCode' => 0,
            'responseText' => 'SMS sent',
            'textLength' => 5,
            'timestamp' => '2026-01-01T00:00:00Z',
            'to' => self::RECIPIENT_E164,
        ]);

        $this->createProvider()->send($this->createMessage(self::RECIPIENT_E164, 'Hi there'));

        $this->assertSame(
            'https://rest.mittoapi.com/v2/sms.json',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['X-Mitto-API-Key']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::SENDER, $body['from']);
        $this->assertSame(self::RECIPIENT_E164, $body['to']);
        $this->assertSame('Hi there', $body['text']);
        $this->assertSame('GSM', $body['type']);
        $this->assertStringContainsString('callbacks/mitto/status', $body['callback']);
        $this->assertStringContainsString('token=', $body['callback']);
    }

    public function testSmsSendUsesUnicodeWhenChannelFlagSet(): void
    {
        $this->configure(channelOverrides: ['sms' => ['from' => self::SENDER, 'unicode' => true]]);
        $this->mockHttpPost(['id' => 'msg-002', 'responseCode' => 0, 'responseText' => 'SMS sent']);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('Unicode', $body['type']);
    }

    public function testSmsSendIncludesReferenceFromFlowExecutionId(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 'msg-003', 'responseCode' => 0, 'responseText' => 'SMS sent']);

        $msg = new Message('sms', self::RECIPIENT_E164, 'Body', 'flow-exec-42', []);
        $this->createProvider()->send($msg);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('flow-exec-42', $body['reference']);
    }

    public function testSendQueuedReturnsIdAsProviderId(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 'msg-abc', 'responseCode' => 0, 'responseText' => 'SMS sent']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-abc', $result->providerId);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['responseCode' => 7, 'responseText' => 'unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedOnNonZeroResponseCode(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => '', 'responseCode' => 5, 'responseText' => 'Invalid receiver number'], 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid receiver number', $result->error);
        $this->assertSame('5', $result->meta['mitto_response_code']);
    }

    public function testSendReturnsFailedWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key', $result->error);
    }

    public function testSendReturnsFailedWhenSenderMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mitto' => ['shared' => ['api_key' => self::API_KEY], 'channels' => ['sms' => []]],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender', $result->error);
    }

    // --- testConnection ---

    public function testTestConnectionPostsTestFlag(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => '', 'responseCode' => 0, 'responseText' => 'SMS sent', 'test' => true]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertTrue($body['test']);
        $this->assertSame(self::SENDER, $body['from']);
    }

    public function testTestConnectionInvalidKey(): void
    {
        $this->configure();
        $this->mockHttpPost(['responseCode' => 7, 'responseText' => 'unauthorized'], 401);

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

    public function testTestConnectionSurfacesNonZeroResponseCode(): void
    {
        $this->configure();
        $this->mockHttpPost(['responseCode' => 4, 'responseText' => 'Invalid sender'], 200);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertSame('Invalid sender', $result->message);
    }

    // --- Status callback signature ---

    public function testStatusCallbackUrlIncludesToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();

        $this->assertStringContainsString('callbacks/mitto/status', $url);
        $this->assertStringContainsString('token=', $url);
    }

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $expectedToken = hash_hmac('sha256', 'mitto-dlr', self::API_KEY);

        $request = new \WP_REST_Request('GET', '/wsms/v1/callbacks/mitto/status');
        $request->set_param('token', $expectedToken);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('GET', '/wsms/v1/callbacks/mitto/status');
        $request->set_param('token', 'deadbeef');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('GET', '/wsms/v1/callbacks/mitto/status');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $request = new \WP_REST_Request('GET', '/wsms/v1/callbacks/mitto/status');
        $request->set_param('token', 'whatever');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    // --- Status callback parsing ---

    public function testParseStatusCallbackMapsAllStatuses(): void
    {
        $cases = [
            'BUFFERED'    => ['queued',    false],
            'SENT'        => ['sent',      false],
            'DELIVERED'   => ['delivered', false],
            'UNDELIVERED' => ['failed',    true],
            'FAILED'      => ['failed',    true],
            'EXPIRED'     => ['failed',    false],
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => [$expected, $expectedPermanent]) {
            $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/mitto/status');
            $request->set_param('msgid', 'msg-' . $raw);
            $request->set_param('status', $raw);

            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expected, $updates[0]->status, "wrong mapping for {$raw}");
            $this->assertSame($expectedPermanent, $updates[0]->permanent, "wrong permanent for {$raw}");
            $this->assertSame('msg-' . $raw, $updates[0]->providerId);
        }
    }

    public function testParseStatusCallbackPropagatesErrorCode(): void
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/mitto/status');
        $request->set_param('msgid', 'msg-err');
        $request->set_param('status', 'UNDELIVERED');
        $request->set_param('errorcode', '438');

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertTrue($updates[0]->permanent);
        $this->assertSame('438', $updates[0]->errorCode);
        $this->assertStringContainsString('UNDELIVERED', $updates[0]->errorMessage);
    }

    public function testParseStatusCallbackSkipsZeroErrorCode(): void
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/mitto/status');
        $request->set_param('msgid', 'msg-ok');
        $request->set_param('status', 'DELIVERED');
        $request->set_param('errorcode', '0');

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertNull($updates[0]->errorCode);
    }

    public function testParseStatusCallbackEmptyWhenMsgIdMissing(): void
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/mitto/status');
        $request->set_param('status', 'DELIVERED');

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackEmptyWhenStatusMissing(): void
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/mitto/status');
        $request->set_param('msgid', 'msg-x');

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }
}
