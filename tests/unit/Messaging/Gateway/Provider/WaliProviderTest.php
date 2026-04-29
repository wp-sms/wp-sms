<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\WaliProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class WaliProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'wali-test-api-key';
    private const DEVICE_ID = '5f8a1b2c3d4e5f6a7b8c9d0e';
    private const CALLBACK_TOKEN = 'token-shared-secret';

    protected function createProvider(): AbstractProvider
    {
        return new WaliProvider();
    }

    private function configure(array $overrides = []): void
    {
        $defaults = [
            'shared' => [
                'api_key'        => self::API_KEY,
                'callback_token' => self::CALLBACK_TOKEN,
            ],
            'channels' => [
                'whatsapp' => ['device_id' => self::DEVICE_ID],
            ],
        ];

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'wali' => array_replace_recursive($defaults, $overrides),
        ];
    }

    private function createMessage(string $recipient = '+15559876543', string $body = 'Hello from WSMS', array $meta = []): Message
    {
        return new Message('whatsapp', $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 201): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array|callable $responseBody, int $statusCode = 200): void
    {
        if (is_callable($responseBody)) {
            $GLOBALS['_test_wp_remote_get'] = $responseBody;
            return;
        }
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function expectedAuthHeader(): string
    {
        return 'Bearer ' . self::API_KEY;
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('wali', $p->getId());
        $this->assertSame(['whatsapp'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(WaliProvider::TESTED);
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['callback_token']['type']);

        $this->assertArrayHasKey('device_id', $schema['channels']['whatsapp']);
        $this->assertSame('select', $schema['channels']['whatsapp']['device_id']['type']);
        $this->assertTrue($schema['channels']['whatsapp']['device_id']['dynamic']);
    }

    public function testIsConfiguredForWhatsapp(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('whatsapp'));
    }

    public function testIsNotConfiguredWhenDeviceMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'wali' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['whatsapp' => []],
            ],
        ];
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('whatsapp'));
    }

    // --- Send: happy path ---

    public function testSendQueuedReturnsMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 'msg-12345', 'status' => 'queued']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-12345', $result->providerId);
    }

    public function testSendPostsBearerAuthAndExpectedBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 'msg-1']);

        $this->createProvider()->send($this->createMessage('+15559876543', 'Ping'));

        $this->assertSame(
            'https://api.wali.chat/v1/messages',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedAuthHeader(), $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('+15559876543', $body['phone']);
        $this->assertSame('Ping', $body['message']);
        $this->assertSame(self::DEVICE_ID, $body['device']);
        $this->assertArrayNotHasKey('media', $body);
    }

    public function testSendUploadsMediaThenAttachesFileId(): void
    {
        $this->configure();

        // The bootstrap's wp_remote_post stub doesn't invoke callables, so we
        // use a single static response — both /files and /messages return it.
        // The chaining assertion comes from inspecting the LAST captured call,
        // which is the /messages POST (it runs after /files).
        $this->mockHttpPost(['id' => 'file-abc']);

        $result = $this->createProvider()->send($this->createMessage('+15559876543', 'photo', [
            'media_urls' => ['https://example.com/cat.jpg'],
        ]));

        $this->assertTrue($result->success);

        // _last_url reflects the most recent call — must be /messages, proving
        // the upload step ran and was followed by the send.
        $this->assertSame(
            'https://api.wali.chat/v1/messages',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $sendBody = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(['file' => 'file-abc'], $sendBody['media']);
        $this->assertSame('+15559876543', $sendBody['phone']);
    }

    public function testSendReturnsFailedWhenMediaUploadFails(): void
    {
        $this->configure();
        // Both POSTs see this 500 — the /files upload step trips the early return.
        $this->mockHttpPost(['error' => 'storage unavailable'], 500);

        $result = $this->createProvider()->send($this->createMessage('+15559876543', 'photo', [
            'media_urls' => ['https://example.com/cat.jpg'],
        ]));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('media upload failed', $result->error);
    }

    // --- Send: error paths ---

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedWhenDeviceMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'wali' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['whatsapp' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Device', $result->error);
    }

    public function testSendBubblesValidationError(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => [['path' => 'phone', 'message' => 'Invalid E.164 format']],
        ], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('phone', $result->error);
        $this->assertStringContainsString('Invalid E.164 format', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditAlwaysReturnsNull(): void
    {
        $this->configure();
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsErrorWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionPingsDevicesEndpoint(): void
    {
        $this->configure();

        $captured = ['url' => null, 'args' => null];
        $this->mockHttpGet(static function (string $url, array $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode([['id' => self::DEVICE_ID, 'alias' => 'main', 'phone' => '+15551234567']]),
                'response' => ['code' => 200],
            ];
        });

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('https://api.wali.chat/v1/devices?size=1', $captured['url']);
        $this->assertSame($this->expectedAuthHeader(), $captured['args']['headers']['Authorization']);
        $this->assertSame(1, $result->details['device_count']);
    }

    public function testTestConnectionOkWhenNoDevicesYet(): void
    {
        $this->configure();
        $this->mockHttpGet([]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->details['device_count']);
        $this->assertStringContainsString('no WhatsApp devices', $result->message);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'invalid api key'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    // --- SupportsDynamicOptions ---

    public function testGetConfigOptionsReturnsOnlyOperativeDevices(): void
    {
        $this->mockHttpGet([
            ['id' => 'dev-1', 'alias' => 'main',     'phone' => '+15551111111', 'status' => 'operative'],
            ['id' => 'dev-2', 'alias' => 'pending',  'phone' => '+15552222222', 'status' => 'pending'],
            ['id' => 'dev-3', 'alias' => 'disabled', 'phone' => '+15553333333', 'status' => 'disabled'],
            ['id' => 'dev-4', 'alias' => 'sales',    'phone' => '+15554444444', 'status' => 'operative'],
        ]);

        $config = [
            'shared'   => ['api_key' => self::API_KEY],
            'channels' => [],
        ];

        $options = $this->createProvider()->getConfigOptions('device_id', 'whatsapp', $config);

        $this->assertCount(2, $options);
        $this->assertSame('dev-1', $options[0]['value']);
        $this->assertSame('main (+15551111111)', $options[0]['label']);
        $this->assertSame('dev-4', $options[1]['value']);
        $this->assertSame('sales (+15554444444)', $options[1]['label']);
    }

    public function testGetConfigOptionsTolersWrappedDataShape(): void
    {
        $this->mockHttpGet([
            'data' => [
                ['id' => 'dev-w1', 'alias' => 'w', 'phone' => '+15555555555', 'status' => 'operative'],
            ],
        ]);

        $config = ['shared' => ['api_key' => self::API_KEY], 'channels' => []];
        $options = $this->createProvider()->getConfigOptions('device_id', 'whatsapp', $config);

        $this->assertCount(1, $options);
        $this->assertSame('dev-w1', $options[0]['value']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('unknown', 'whatsapp', []));
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownSection(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('device_id', 'sms', []));
    }

    public function testGetConfigOptionsThrowsWhenApiKeyMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->createProvider()->getConfigOptions('device_id', 'whatsapp', ['shared' => [], 'channels' => []]);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => self::CALLBACK_TOKEN], '{}');

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong'], '{}');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', [], '{}');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenNotConfigured(): void
    {
        // Empty callback_token in config — must reject every webhook.
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'wali' => [
                'shared'   => ['api_key' => self::API_KEY, 'callback_token' => ''],
                'channels' => ['whatsapp' => ['device_id' => self::DEVICE_ID]],
            ],
        ];
        $request = $this->buildRequest('POST', '/x', ['token' => 'anything'], '{}');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsSentEvent(): void
    {
        $request = $this->buildRequest('POST', '/x', [], json_encode([
            'event' => 'message:out:sent',
            'data'  => ['id' => 'msg-1', 'status' => 'sent'],
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('msg-1', $updates[0]->providerId);
        $this->assertSame('sent', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testParseStatusCallbackMapsAckDeliveredAndRead(): void
    {
        foreach (['delivered', 'read'] as $deliveryStatus) {
            $request = $this->buildRequest('POST', '/x', [], json_encode([
                'event' => 'message:out:ack',
                'data'  => ['id' => 'msg-' . $deliveryStatus, 'deliveryStatus' => $deliveryStatus],
            ]));

            $updates = $this->createProvider()->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$deliveryStatus}");
            $this->assertSame('delivered', $updates[0]->status, "wrong mapping for {$deliveryStatus}");
        }
    }

    public function testParseStatusCallbackMarksFailedAsPermanent(): void
    {
        $request = $this->buildRequest('POST', '/x', [], json_encode([
            'event' => 'message:out:failed',
            'data'  => [
                'id'            => 'msg-bad',
                'failureCode'   => 'WA_BLOCKED',
                'failureReason' => 'Recipient has blocked the sender',
            ],
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertTrue($updates[0]->permanent);
        $this->assertSame('WA_BLOCKED', $updates[0]->errorCode);
        $this->assertStringContainsString('Recipient has blocked', $updates[0]->errorMessage);
    }

    public function testParseStatusCallbackIgnoresUnrelatedEvents(): void
    {
        $request = $this->buildRequest('POST', '/x', [], json_encode([
            'event' => 'chat:update',
            'data'  => ['id' => 'chat-1'],
        ]));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackEmptyForMissingId(): void
    {
        $request = $this->buildRequest('POST', '/x', [], json_encode([
            'event' => 'message:out:sent',
            'data'  => [],
        ]));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', [], json_encode([
            'event' => 'message:in:new',
            'data'  => [
                'id'   => 'in-1',
                'from' => ['phone' => '+15559876543', 'name' => 'Alice'],
                'to'   => '+15551234567',
                'body' => 'Hi back',
                'type' => 'text',
            ],
        ]));

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+15559876543', $msg->from);
        $this->assertSame('+15551234567', $msg->to);
        $this->assertSame('Hi back', $msg->body);
        $this->assertSame('in-1', $msg->providerId);
        $this->assertSame('text', $msg->meta['type']);
    }

    public function testParseInboundCallbackAcceptsFlatPhoneFrom(): void
    {
        $request = $this->buildRequest('POST', '/x', [], json_encode([
            'event' => 'message:in:new',
            'data'  => ['id' => 'in-2', 'from' => '+15559876543', 'body' => 'flat'],
        ]));

        $messages = $this->createProvider()->parseInboundCallback($request);
        $this->assertCount(1, $messages);
        $this->assertSame('+15559876543', $messages[0]->from);
    }

    public function testParseInboundCallbackIgnoresOutboundEvents(): void
    {
        $request = $this->buildRequest('POST', '/x', [], json_encode([
            'event' => 'message:out:sent',
            'data'  => ['id' => 'out-1'],
        ]));

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testParseInboundCallbackEmptyWhenFromMissing(): void
    {
        $request = $this->buildRequest('POST', '/x', [], json_encode([
            'event' => 'message:in:new',
            'data'  => ['id' => 'in-x', 'body' => 'no sender'],
        ]));

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Helpers ---

    /**
     * Builds a WP_REST_Request with both query params and a JSON body. The
     * bootstrap stub doesn't auto-decode JSON, so we set the body explicitly
     * so get_json_params() works.
     */
    private function buildRequest(string $method, string $route, array $params, string $body = ''): \WP_REST_Request
    {
        $request = new \WP_REST_Request($method, $route);
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        if ($body !== '') {
            $request->set_body($body);
        }
        return $request;
    }
}
