<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BrevoProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BrevoProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'xkeysib-test-key';
    private const SMS_FROM = 'WSMSAlerts';
    private const WA_FROM = '15551234567';
    private const WEBHOOK_TOKEN = 'webhook-secret-token';

    protected function createProvider(): AbstractProvider
    {
        return new BrevoProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'brevo' => [
                'shared' => array_merge([
                    'api_key'       => self::API_KEY,
                    'webhook_token' => self::WEBHOOK_TOKEN,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms'      => ['from' => self::SMS_FROM, 'type' => 'transactional'],
                    'whatsapp' => ['from' => self::WA_FROM],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+15559876543', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 201): void
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

    private function buildRequest(string $method = 'POST', array $params = [], ?string $jsonBody = null): \WP_REST_Request
    {
        $request = new \WP_REST_Request($method, '/x');
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        if ($jsonBody !== null) {
            $request->set_body($jsonBody);
        }
        return $request;
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('brevo', $p->getId());
        $this->assertSame(['sms', 'whatsapp'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BrevoProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('webhook_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['webhook_token']['type']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
        $this->assertArrayHasKey('type', $schema['channels']['sms']);
        $this->assertSame('select', $schema['channels']['sms']['type']['type']);
        $optionValues = array_column($schema['channels']['sms']['type']['options'], 'value');
        $this->assertContains('transactional', $optionValues);
        $this->assertContains('marketing', $optionValues);

        $this->assertArrayHasKey('from', $schema['channels']['whatsapp']);
        $this->assertTrue($schema['channels']['whatsapp']['from']['required']);
    }

    public function testIsConfiguredForChannelTrueWhenSmsConfigured(): void
    {
        $this->configure();
        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('sms'));
        $this->assertTrue($p->isConfiguredForChannel('whatsapp'));
    }

    // --- Send: SMS ---

    public function testSmsSendPostsToTransactionalSendEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost(['messageId' => 99001], 201);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://api.brevo.com/v3/transactionalSMS/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['api-key']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);
        $this->assertSame('application/json', $args['headers']['Accept']);
    }

    public function testSmsSendBodyShape(): void
    {
        $this->configure();
        $this->mockHttpPost(['messageId' => 99001], 201);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'Hi'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::SMS_FROM, $body['sender']);
        $this->assertSame('15559876543', $body['recipient']);
        $this->assertSame('Hi', $body['content']);
        $this->assertSame('transactional', $body['type']);
    }

    public function testSmsSendUsesMarketingTypeWhenConfigured(): void
    {
        $this->configure([], [
            'sms' => ['from' => self::SMS_FROM, 'type' => 'marketing'],
        ]);
        $this->mockHttpPost(['messageId' => 1], 201);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('marketing', $body['type']);
    }

    public function testSmsSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost(['messageId' => 1], 201);

        $this->createProvider()->send($this->createMessage('sms', '+447700900123', 'Hi'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('447700900123', $body['recipient']);
    }

    public function testSmsSendSuccessReturnsMessageIdAsQueued(): void
    {
        $this->configure();
        $this->mockHttpPost(['messageId' => 99001, 'reference' => 'ref-1'], 201);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('99001', $result->providerId);
    }

    public function testSmsSendInvalidApiKeyReturns401AsFailed(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 'unauthorized', 'message' => 'no'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSmsSendBrevoErrorCodeSurfacedInMeta(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'code'    => 'invalid_parameter',
            'message' => 'Invalid recipient',
        ], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid recipient', $result->error);
        $this->assertSame('invalid_parameter', $result->meta['brevo_code']);
        $this->assertSame(400, $result->meta['brevo_http']);
    }

    public function testSendReturnsFailedWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Send: WhatsApp ---

    public function testWhatsAppSendPostsToWhatsAppEndpointWithExpectedBodyShape(): void
    {
        $this->configure();
        $this->mockHttpPost(['messageId' => 'wa-1'], 201);

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', 'Hi WA'));

        $this->assertSame(
            'https://api.brevo.com/v3/whatsapp/sendMessage',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::WA_FROM, $body['senderNumber']);
        $this->assertSame(['15559876543'], $body['contactNumbers']);
        $this->assertSame('Hi WA', $body['text']);
    }

    public function testWhatsAppSendFailedWhenSenderMissing(): void
    {
        $this->configure([], [
            'whatsapp' => [],
        ]);

        $result = $this->createProvider()->send($this->createMessage('whatsapp'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sender', strtolower($result->error));
    }

    // --- Status callback ---

    public function testValidateStatusCallbackRejectsWhenWebhookTokenNotConfigured(): void
    {
        $this->configure(['webhook_token' => '']);
        $request = $this->buildRequest('POST', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsGoodToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => self::WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackHandlesSingleEventObject(): void
    {
        $event = [
            'message_id' => 'evt-1',
            'msg_status' => 'delivered',
        ];
        $request = $this->buildRequest('POST', [], json_encode($event));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('evt-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
    }

    public function testParseStatusCallbackHandlesEventArray(): void
    {
        $events = [
            ['message_id' => 'evt-1', 'msg_status' => 'sent'],
            ['message_id' => 'evt-2', 'msg_status' => 'delivered'],
        ];
        $request = $this->buildRequest('POST', [], json_encode($events));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(2, $updates);
        $this->assertSame('sent', $updates[0]->status);
        $this->assertSame('delivered', $updates[1]->status);
    }

    public function testParseStatusCallbackSkipsEventsWithoutMessageId(): void
    {
        $event = ['msg_status' => 'delivered']; // no message_id
        $request = $this->buildRequest('POST', [], json_encode($event));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    /**
     * @dataProvider statusMappingProvider
     */
    public function testParseStatusCallbackMapsAllStatusValues(string $brevoStatus, string $expectedStatus, bool $expectedPermanent, bool $expectedUnsub): void
    {
        $event = [
            'message_id' => 'm-1',
            'msg_status' => $brevoStatus,
        ];
        $request = $this->buildRequest('POST', [], json_encode($event));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates, "Expected status update for {$brevoStatus}");
        $this->assertSame($expectedStatus, $updates[0]->status, "Wrong status for {$brevoStatus}");
        $this->assertSame($expectedPermanent, $updates[0]->permanent, "Wrong permanent flag for {$brevoStatus}");
        $this->assertSame($expectedUnsub, $updates[0]->unsubscribe, "Wrong unsubscribe flag for {$brevoStatus}");
    }

    public static function statusMappingProvider(): array
    {
        return [
            'sent'          => ['sent',          'sent',      false, false],
            'accepted'      => ['accepted',      'queued',    false, false],
            'delivered'     => ['delivered',     'delivered', false, false],
            'soft_bounce'   => ['soft_bounce',   'failed',    false, false],
            'hard_bounce'   => ['hard_bounce',   'failed',    true,  true],
            'unsubscribed'  => ['unsubscribed',  'failed',    true,  true],
            'blacklisted'   => ['blacklisted',   'failed',    true,  true],
            'rejected'      => ['rejected',      'failed',    true,  false],
            'skipped'       => ['skipped',       'failed',    false, false],
        ];
    }

    public function testParseStatusCallbackHardBounceTypeOverridesPermanentFlag(): void
    {
        $event = [
            'message_id'  => 'm-1',
            'msg_status'  => 'soft_bounce',
            'bounce_type' => 'hard',
        ];
        $request = $this->buildRequest('POST', [], json_encode($event));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertTrue($updates[0]->permanent);
    }

    public function testParseStatusCallbackSkipsRepliedAndSubscribed(): void
    {
        $events = [
            ['message_id' => 'm-1', 'msg_status' => 'replied'],
            ['message_id' => 'm-2', 'msg_status' => 'subscribed'],
        ];
        $request = $this->buildRequest('POST', [], json_encode($events));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testGetStatusCallbackUrlIncludesTokenQueryArg(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();

        $this->assertStringContainsString('callbacks/brevo/status', $url);
        $this->assertStringContainsString('token=' . self::WEBHOOK_TOKEN, $url);
    }

    // --- Test connection / credit ---

    public function testTestConnectionOkReportsSmsCredits(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'companyName' => 'Test Co',
            'plan' => [
                ['type' => 'transactional', 'credits' => 1000],
                ['type' => 'sms',           'credits' => 250],
            ],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('250', $result->message);
        $this->assertSame(250, $result->details['sms_credits']);
    }

    public function testTestConnectionFailsOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['code' => 'unauthorized', 'message' => 'no'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionFailsOn403(): void
    {
        $this->configure();
        $this->mockHttpGet(['code' => 'forbidden', 'message' => 'no'], 403);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('permissions', $result->message);
    }

    public function testGetCreditReturnsFormattedSmsCount(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'plan' => [
                ['type' => 'sms', 'credits' => 250],
            ],
        ]);

        $this->assertSame('250 SMS', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenNoSmsPlan(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'plan' => [
                ['type' => 'transactional', 'credits' => 1000],
            ],
        ]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Opt-out ---

    public function testIsOptOutErrorReturnsFalse(): void
    {
        $result = DeliveryResult::failed('any error');
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }
}
