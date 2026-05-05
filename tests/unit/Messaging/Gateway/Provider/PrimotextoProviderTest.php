<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\PrimotextoProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class PrimotextoProviderTest extends AbstractProviderTestCase
{
    private const API_KEY     = 'pt-test-api-key';
    private const SENDER      = 'WSMS';
    private const SNAPSHOT_ID = 'snap_42';

    protected function createProvider(): AbstractProvider
    {
        return new PrimotextoProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get_last_url'],
            $GLOBALS['_test_wp_remote_get_last_args'],
        );
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'primotexto' => [
                'shared' => array_merge([
                    'api_key' => self::API_KEY,
                    'mode'    => 'notification',
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+33612345678', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body, null, []);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(PrimotextoProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('primotexto', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('mode', $schema['shared']);
        $this->assertSame('select', $schema['shared']['mode']['type']);
        $this->assertSame('notification', $schema['shared']['mode']['default']);

        $sms = $schema['channels']['sms'];
        $this->assertTrue($sms['from']['required']);
        $this->assertArrayHasKey('category', $sms);
        $this->assertEmpty($sms['category']['required'] ?? false);
    }

    public function testFeaturesAdvertiseDeliveryReceiptIncomingAndTestConnection(): void
    {
        $features = $this->createProvider()->getFeatures();
        $this->assertTrue($features['delivery_receipt']);
        $this->assertTrue($features['incoming']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
    }

    public function testIsConfiguredForChannelRequiresApiKeyAndSender(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));

        $GLOBALS['_test_options']['wsms_gateway_configs']['primotexto']['channels']['sms']['from'] = '';
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send ---

    public function testSendPostsJsonToNotificationEndpointWithApiKeyHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['snapshotId' => self::SNAPSHOT_ID, 'creditsUsed' => 1.0]);

        $this->createProvider()->send($this->createMessage('+33612345678', 'Bonjour'));

        $this->assertSame(
            'https://api.primotexto.com/v2/notification/messages/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['X-Primotexto-ApiKey']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('+33612345678', $body['number']);
        $this->assertSame('Bonjour', $body['message']);
        $this->assertSame(self::SENDER, $body['sender']);
        $this->assertArrayNotHasKey('category', $body);
    }

    public function testSendUsesMarketingEndpointWhenModeMarketing(): void
    {
        $this->configure(['mode' => 'marketing']);
        $this->mockHttpPost(['snapshotId' => self::SNAPSHOT_ID, 'creditsUsed' => 1.0]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://api.primotexto.com/v2/marketing/messages/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testSendIncludesCategoryWhenConfigured(): void
    {
        $this->configure(smsOverrides: ['category' => 'newsletter-2026']);
        $this->mockHttpPost(['snapshotId' => self::SNAPSHOT_ID, 'creditsUsed' => 1.0]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('newsletter-2026', $body['category']);
    }

    public function testSendReturnsSentWithSnapshotIdAndCost(): void
    {
        $this->configure();
        $this->mockHttpPost(['snapshotId' => self::SNAPSHOT_ID, 'creditsUsed' => 0.75]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame(self::SNAPSHOT_ID, $result->providerId);
        $this->assertSame(0.75, $result->cost);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 70, 'message' => 'Invalid API key'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendMapsSenderErrorCode23(): void
    {
        $this->configure(smsOverrides: ['from' => '12345']);
        $this->mockHttpPost(['code' => 23, 'message' => 'Sender must contain at least one letter'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Sender must contain at least one letter', $result->error);
        $this->assertSame(23, $result->meta['primotexto_error_code']);
    }

    public function testSendMapsPhoneErrorCode10(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 10, 'message' => 'Invalid phone number'], 400);

        $result = $this->createProvider()->send($this->createMessage('not-a-number'));

        $this->assertFalse($result->success);
        $this->assertSame('Invalid phone number', $result->error);
        $this->assertSame(10, $result->meta['primotexto_error_code']);
    }

    public function testSendReturnsFailedOnNetworkError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'Connection timeout');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('timeout', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedWhenSenderMissing(): void
    {
        $this->configure(smsOverrides: ['from' => '']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsCreditsBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['credits' => 42.5]);

        $this->assertSame('42.5', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOn401(): void
    {
        $this->configure();
        $this->mockHttpGet([], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnMalformedJson(): void
    {
        $this->configure();
        $this->mockHttpGet('not-json', 200);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['credits' => 50]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('50', $result->message);
        $this->assertSame('50', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet([], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback ---

    public function testStatusCallbackUrlContainsDerivedToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();
        $expectedToken = hash_hmac('sha256', 'primotexto-callback', self::API_KEY);

        $this->assertStringContainsString('callbacks/primotexto/status', $url);
        $this->assertStringContainsString('token=' . $expectedToken, $url);
    }

    public function testInboundCallbackUrlContainsDerivedToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getInboundCallbackUrl();
        $expectedToken = hash_hmac('sha256', 'primotexto-callback', self::API_KEY);

        $this->assertStringContainsString('callbacks/primotexto/inbound', $url);
        $this->assertStringContainsString('token=' . $expectedToken, $url);
    }

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $token = hash_hmac('sha256', 'primotexto-callback', self::API_KEY);
        $request = $this->buildJsonRequest(['event' => 'sent', 'snapshotId' => self::SNAPSHOT_ID], ['token' => $token]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildJsonRequest([], ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildJsonRequest([]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $request = $this->buildJsonRequest([], ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateInboundCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $token = hash_hmac('sha256', 'primotexto-callback', self::API_KEY);
        $request = $this->buildJsonRequest(['event' => 'reply'], ['token' => $token]);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = $this->buildJsonRequest([], ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    /**
     * @dataProvider statusEventProvider
     */
    public function testParseStatusCallbackMapsEventToStatus(
        string $event,
        string $expectedStatus,
        bool $expectedPermanent,
        bool $expectedUnsubscribe,
    ): void {
        $request = $this->buildJsonRequest([
            'event'      => $event,
            'snapshotId' => self::SNAPSHOT_ID,
            'contact'    => ['identifier' => '+33612345678'],
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame(self::SNAPSHOT_ID, $updates[0]->providerId);
        $this->assertSame($expectedStatus, $updates[0]->status);
        $this->assertSame($expectedPermanent, $updates[0]->permanent);
        $this->assertSame($expectedUnsubscribe, $updates[0]->unsubscribe);
    }

    public static function statusEventProvider(): array
    {
        return [
            'submitted'    => ['submitted',    'queued',    false, false],
            'sent'         => ['sent',         'sent',      false, false],
            'delivered'    => ['delivered',    'delivered', false, false],
            'bounced'      => ['bounced',      'failed',    true,  false],
            'unsubscribed' => ['unsubscribed', 'failed',    true,  true],
            'error'        => ['error',        'failed',    false, false],
        ];
    }

    public function testParseStatusCallbackSkipsReplyEvent(): void
    {
        $request = $this->buildJsonRequest([
            'event'      => 'reply',
            'snapshotId' => self::SNAPSHOT_ID,
        ]);

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackSkipsOpenedEvent(): void
    {
        $request = $this->buildJsonRequest([
            'event'      => 'opened',
            'snapshotId' => self::SNAPSHOT_ID,
        ]);

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildJsonRequest(['event' => 'sent']);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackBuildsInboundMessageFromReplyEvent(): void
    {
        $this->configure();
        $request = $this->buildJsonRequest([
            'event'        => 'reply',
            'snapshotId'   => self::SNAPSHOT_ID,
            'contact'      => ['identifier' => '+33612345678'],
            'replyMessage' => 'STOP',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+33612345678', $messages[0]->from);
        $this->assertSame(self::SENDER, $messages[0]->to);
        $this->assertSame('STOP', $messages[0]->body);
        $this->assertSame(self::SNAPSHOT_ID, $messages[0]->providerId);
    }

    public function testParseInboundCallbackReturnsEmptyForNonReplyEvents(): void
    {
        $this->configure();
        foreach (['delivered', 'bounced', 'unsubscribed', 'error', 'opened', 'submitted', 'sent'] as $event) {
            $request = $this->buildJsonRequest([
                'event'        => $event,
                'snapshotId'   => self::SNAPSHOT_ID,
                'contact'      => ['identifier' => '+33612345678'],
                'replyMessage' => 'should be ignored',
            ]);
            $this->assertSame([], $this->createProvider()->parseInboundCallback($request), "event={$event}");
        }
    }

    public function testParseInboundCallbackReturnsEmptyWhenContactMissing(): void
    {
        $this->configure();
        $request = $this->buildJsonRequest([
            'event'        => 'reply',
            'replyMessage' => 'Hello',
        ]);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testParseInboundCallbackReturnsEmptyWhenReplyMessageMissing(): void
    {
        $this->configure();
        $request = $this->buildJsonRequest([
            'event'   => 'reply',
            'contact' => ['identifier' => '+33612345678'],
        ]);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Helpers ---

    private function buildJsonRequest(array $payload, array $params = []): \WP_REST_Request
    {
        $request = new class('POST', '/x') extends \WP_REST_Request {
            public function get_method(): string { return 'POST'; }
        };
        $request->set_body(json_encode($payload));
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        return $request;
    }
}
