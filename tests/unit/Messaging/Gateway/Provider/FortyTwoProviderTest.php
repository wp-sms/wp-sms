<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\FortyTwoProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class FortyTwoProviderTest extends AbstractProviderTestCase
{
    private const API_TOKEN     = 'fortytwo-test-api-token';
    private const WEBHOOK_TOKEN = 'fortytwo-test-webhook-token';
    private const SMS_SENDER    = 'MyCompany';
    private const VIBER_SENDER  = 'MyViberBiz';
    private const SEND_URL      = 'https://rest.fortytwo.com/1/im';

    protected function createProvider(): AbstractProvider
    {
        return new FortyTwoProvider();
    }

    private function configure(?string $webhookToken = self::WEBHOOK_TOKEN, array $channelOverrides = []): void
    {
        $shared = ['api_token' => self::API_TOKEN];
        if ($webhookToken !== null) {
            $shared['webhook_token'] = $webhookToken;
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'fortytwo' => [
                'shared'   => $shared,
                'channels' => array_merge([
                    'sms'   => ['sender_id' => self::SMS_SENDER],
                    'viber' => ['sender_id' => self::VIBER_SENDER],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+35699123456', string $body = 'Hello', array $meta = []): Message
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

    private function mockHttpGet(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    private function expectedAuthHeader(): string
    {
        return 'Token ' . self::API_TOKEN;
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('fortytwo', $p->getId());
        $this->assertSame(['sms', 'viber'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(FortyTwoProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue($schema['shared']['api_token']['required']);

        $this->assertArrayHasKey('webhook_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['webhook_token']['type']);
        $this->assertFalse($schema['shared']['webhook_token']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertFalse($schema['channels']['sms']['sender_id']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['viber']);
        $this->assertTrue($schema['channels']['viber']['sender_id']['required']);
    }

    public function testIsConfiguredForChannelSmsTrueWithTokenOnly(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'fortytwo' => ['shared' => ['api_token' => self::API_TOKEN], 'channels' => []],
        ];

        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('sms'));
        $this->assertFalse($p->isConfiguredForChannel('viber'));
        $this->assertFalse($p->isConfiguredForChannel('whatsapp'));
    }

    public function testIsConfiguredForChannelViberRequiresSender(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('viber'));
    }

    // --- Send: SMS ---

    public function testSmsSendQueuedReturnsMessageIdFromResultsMap(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'api_job_id'   => 'job-1',
            'result_info'  => ['status_code' => 200, 'description' => 'ok'],
            'results'      => [
                '35699123456' => ['message_id' => 'ft-msg-001', 'custom_id' => '1'],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('ft-msg-001', $result->providerId);
    }

    public function testSmsSendPostsToImEndpointWithTokenAuthAndSnakeCaseBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['results' => ['35699123456' => ['message_id' => 'm1']]]);

        $this->createProvider()->send($this->createMessage('sms', '+35699123456', 'Hi there'));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedAuthHeader(), $args['headers']['Authorization']);
        $this->assertStringStartsWith('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);

        // FortyTwo strips the leading '+' on numbers (matches SDK's NumberValue).
        $this->assertSame('35699123456', $body['destinations'][0]['number']);
        $this->assertSame('Hi there', $body['sms_content']['message']);
        $this->assertSame(self::SMS_SENDER, $body['sms_content']['sender_id']);
        $this->assertArrayNotHasKey('im_content', $body);

        // Webhook URLs include the configured token in the query string.
        $this->assertStringContainsString('callbacks/fortytwo/status', $body['callback_url']);
        $this->assertStringContainsString('token=' . self::WEBHOOK_TOKEN, $body['callback_url']);
        $this->assertStringContainsString('callbacks/fortytwo/inbound', $body['reply_url']);
    }

    public function testSmsSendOmitsSenderIdWhenNotConfigured(): void
    {
        $this->configure(channelOverrides: ['sms' => []]);
        $this->mockHttpPost(['results' => ['35699123456' => ['message_id' => 'm1']]]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('sender_id', $body['sms_content']);
    }

    public function testSmsSendOmitsCallbackUrlsWhenWebhookTokenBlank(): void
    {
        $this->configure(webhookToken: null);
        $this->mockHttpPost(['results' => ['35699123456' => ['message_id' => 'm1']]]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        // URLs are still emitted (RestRoute::url returns a base URL), but with
        // no ?token= query — verify the callback_url has no token param.
        $this->assertArrayHasKey('callback_url', $body);
        $this->assertStringNotContainsString('token=', $body['callback_url']);
    }

    public function testSmsSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'unauth'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendBubblesUpProviderErrorDescription(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'result_info' => ['status_code' => 422, 'description' => 'Number is invalid'],
        ], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Number is invalid', $result->error);
        $this->assertSame(422, $result->meta['fortytwo_status']);
        $this->assertSame('422', $result->meta['fortytwo_code']);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Send: Viber ---

    public function testViberSendBuildsImContentWithViberChannel(): void
    {
        $this->configure();
        $this->mockHttpPost(['results' => ['35699123456' => ['message_id' => 'vib-1']]]);

        $this->createProvider()->send($this->createMessage('viber', '+35699123456', 'Hi viber'));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('VIBER', $body['im_content'][0]['channel']);
        $this->assertSame(self::VIBER_SENDER, $body['im_content'][0]['sender_id']);
        $this->assertSame('Hi viber', $body['im_content'][0]['content']);
        $this->assertArrayNotHasKey('sms_content', $body);
    }

    public function testViberSendIncludesImageFromMediaUrls(): void
    {
        $this->configure();
        $this->mockHttpPost(['results' => ['35699123456' => ['message_id' => 'vib-2']]]);

        $this->createProvider()->send($this->createMessage('viber', '+35699123456', 'Look', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('https://example.com/photo.jpg', $body['im_content'][0]['images'][0]['url']);
    }

    public function testViberSendIncludesActionButtonWhenBothFieldsPresent(): void
    {
        $this->configure();
        $this->mockHttpPost(['results' => ['35699123456' => ['message_id' => 'vib-3']]]);

        $this->createProvider()->send($this->createMessage('viber', '+35699123456', 'Promo', [
            'viber_action_url'   => 'https://example.com/landing',
            'viber_action_title' => 'Tap to join',
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('Tap to join', $body['im_content'][0]['actions'][0]['title']);
        $this->assertSame('https://example.com/landing', $body['im_content'][0]['actions'][0]['target_url']);
    }

    public function testViberSendFailsWithoutSenderId(): void
    {
        $this->configure(channelOverrides: ['viber' => []]);
        $this->mockHttpPost(['results' => []]);

        $result = $this->createProvider()->send($this->createMessage('viber'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Viber', $result->error);
    }

    // --- Test connection ---

    public function testTestConnectionRequiresApiToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionReturnsOkWhenAuthAccepted(): void
    {
        $this->configure();
        // Probe ID returns 404 — token authenticated, message just doesn't exist.
        $this->mockHttpGet(['result_info' => ['status_code' => 404, 'description' => 'not found']], 404);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildJsonRequestWithToken(['data' => []], self::WEBHOOK_TOKEN);
        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = $this->buildJsonRequestWithToken(['data' => []], 'bogus-token');
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildJsonRequestWithToken(['data' => []], null);
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenNoTokenConfigured(): void
    {
        $this->configure(webhookToken: null);
        $request = $this->buildJsonRequestWithToken(['data' => []], 'anything');
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsStatusesAcrossSmsAndViber(): void
    {
        $cases = [
            'QUEUED'        => ['queued', false],
            'BUFFERED'      => ['queued', false],
            'ACCEPTED'      => ['sent', false],
            'SUBMITTED'     => ['sent', false],
            'DELIVERED'     => ['delivered', false],
            'SEEN'          => ['delivered', false],
            'REJECTED'      => ['failed', true],
            'FAILED'        => ['failed', true],
            'EXPIRED'       => ['failed', true],
            'UNDELIVERABLE' => ['failed', true],
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => [$expectedStatus, $expectedPermanent]) {
            $request = $this->buildJsonRequestWithToken([
                'data' => [[
                    'type'       => $raw === 'SEEN' ? 'IM_VIBER' : 'SMS',
                    'message_id' => 'm-' . $raw,
                    'status'     => $raw,
                ]],
            ], self::WEBHOOK_TOKEN);

            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expectedStatus, $updates[0]->status, "wrong status for {$raw}");
            $this->assertSame($expectedPermanent, $updates[0]->permanent, "wrong permanent flag for {$raw}");
            $this->assertSame('m-' . $raw, $updates[0]->providerId);
        }
    }

    public function testParseStatusCallbackBatchesMultipleEntries(): void
    {
        $request = $this->buildJsonRequestWithToken([
            'api_job_id' => 'job-multi',
            'data'       => [
                ['type' => 'SMS', 'message_id' => 'm-1', 'status' => 'DELIVERED'],
                ['type' => 'IM_VIBER', 'message_id' => 'm-2', 'status' => 'FAILED', 'error_code' => 7],
                ['message_id' => '', 'status' => 'DELIVERED'], // skipped (no id)
            ],
        ], self::WEBHOOK_TOKEN);

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(2, $updates);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertSame('failed', $updates[1]->status);
        $this->assertSame('7', $updates[1]->errorCode);
    }

    public function testParseStatusCallbackEmptyForMissingData(): void
    {
        $request = $this->buildJsonRequestWithToken(['api_job_id' => 'x'], self::WEBHOOK_TOKEN);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = $this->buildJsonRequestWithToken([
            'from'       => '35699123456',
            'to'         => 'MyCompany',
            'message'    => 'STOP',
            'message_id' => 'in-1',
            'timestamp'  => '1714400000',
            'type'       => 'SMS',
        ], self::WEBHOOK_TOKEN);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('35699123456', $msg->from);
        $this->assertSame('MyCompany', $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('in-1', $msg->providerId);
        $this->assertSame('1714400000', $msg->meta['received_at']);
        $this->assertSame('SMS', $msg->meta['type']);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildJsonRequestWithToken(['to' => 'x'], self::WEBHOOK_TOKEN);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testValidateInboundCallbackUsesSameTokenScheme(): void
    {
        $this->configure();
        $ok  = $this->buildJsonRequestWithToken([], self::WEBHOOK_TOKEN);
        $bad = $this->buildJsonRequestWithToken([], 'bogus');

        $p = $this->createProvider();
        $this->assertTrue($p->validateInboundCallback($ok));
        $this->assertFalse($p->validateInboundCallback($bad));
    }

    // --- Helpers ---

    private function buildJsonRequestWithToken(array $jsonBody, ?string $token): \WP_REST_Request
    {
        return new class($jsonBody, $token) extends \WP_REST_Request {
            private array $jsonBody;
            public function __construct(array $jsonBody, ?string $token) {
                parent::__construct('POST', '/x');
                $this->jsonBody = $jsonBody;
                if ($token !== null) {
                    $this->set_param('token', $token);
                }
            }
            public function get_method(): string { return 'POST'; }
            public function get_json_params(): array { return $this->jsonBody; }
            public function get_body(): ?string { return json_encode($this->jsonBody); }
        };
    }
}
