<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\GunismsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class GunismsProviderTest extends AbstractProviderTestCase
{
    private const TOKEN = 'guni-test-token';
    private const FROM  = '+61400000123';

    protected function createProvider(): AbstractProvider
    {
        return new GunismsProvider();
    }

    private function configure(array $sharedOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gunisms' => [
                'shared'   => array_merge([
                    'gateway_token' => self::TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => ['from' => self::FROM],
                ],
            ],
        ];
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

    private function createMessage(string $recipient = '+61400000001', string $body = 'Hello AU', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('gunisms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(GunismsProvider::TESTED);
    }

    public function testConfigSchemaHasRequiredFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('gateway_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['gateway_token']['type']);
        $this->assertTrue($schema['shared']['gateway_token']['required']);

        $this->assertArrayHasKey('webhook_secret', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['webhook_secret']['type']);
        $this->assertEmpty($schema['shared']['webhook_secret']['required'] ?? false);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
    }

    // --- Send: SMS ---

    public function testSendsSmsViaGatewayEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status'  => true,
            'message' => 'queued',
            'data'    => ['id' => 'abc123'],
        ]);

        $result = $this->createProvider()->send($this->createMessage('+61400000001', 'Hi mate'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('abc123', $result->providerId);

        $this->assertSame(
            'https://api.gunisms.com.au/api/v1/gateway',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::TOKEN, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('Hi mate', $body['message']);
        $this->assertSame(['61400000001'], $body['contacts']);
        $this->assertSame(self::FROM, $body['sender']);
        $this->assertArrayNotHasKey('media', $body);
    }

    public function testSendsMmsViaGatewayMmsEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status' => true,
            'data'   => ['id' => 'mms1'],
        ]);

        $this->createProvider()->send($this->createMessage('+61400000001', 'See pic', [
            'media_urls' => ['https://example.com/x.jpg'],
        ]));

        $this->assertSame(
            'https://api.gunisms.com.au/api/v1/gatewaymms',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('https://example.com/x.jpg', $body['media']);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => false, 'message' => 'unauth'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendBubblesUpProviderError(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => false, 'message' => 'invalid recipient'], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('invalid recipient', $result->error);
    }

    public function testSendRejectsNonSmsChannel(): void
    {
        $this->configure();
        $message = new Message('whatsapp', '+61400000001', 'hi', null, []);

        $result = $this->createProvider()->send($message);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('whatsapp', $result->error);
    }

    // --- Credit ---

    public function testGetCreditFetchesNestedDataBalance(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => true, 'data' => ['balance' => 42.5]]);

        $this->assertSame('42.5', $this->createProvider()->getCredit());
        $this->assertSame(
            'https://api.gunisms.com.au/api/v1/user/balance',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testGetCreditFetchesTopLevelBalance(): void
    {
        // v7 shape: { status, message, balance } — no nested data wrapper.
        $this->configure();
        $this->mockHttpPost(['status' => true, 'balance' => 17.25]);

        $this->assertSame('17.25', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => false, 'message' => 'oops'], 500);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionWithValidToken(): void
    {
        $this->configure();
        $capturedUrl = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return ['body' => json_encode(['status' => true]), 'response' => ['code' => 200]];
        };

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('https://api.gunisms.com.au/api/v1/user/token/verify', $capturedUrl);
    }

    public function testTestConnectionWithInvalidToken(): void
    {
        $this->configure();
        $this->mockHttpGet(['status' => false], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback ---

    public function testStatusCallbackRejectsRequestWithoutSecretWhenConfigured(): void
    {
        $this->configure(['webhook_secret' => 'topsecret']);
        $request = $this->buildRequest('POST', '/x', ['_id' => 'a', 'status' => 'delivered']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptsMatchingSecret(): void
    {
        $this->configure(['webhook_secret' => 'topsecret']);
        $request = $this->buildRequest('POST', '/x', [
            '_id'    => 'a',
            'status' => 'delivered',
            'secret' => 'topsecret',
        ]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsWrongSecret(): void
    {
        $this->configure(['webhook_secret' => 'topsecret']);
        $request = $this->buildRequest('POST', '/x', [
            '_id'    => 'a',
            'status' => 'delivered',
            'secret' => 'wrong',
        ]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptsAnyRequestWhenSecretBlank(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['_id' => 'a', 'status' => 'delivered']);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackReturnsDeliveredUpdate(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            '_id'      => 'abc123',
            'status'   => 'delivered',
            'sender'   => '+61400000123',
            'receiver' => '+61400000001',
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('abc123', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testParseStatusCallbackMapsFailedAsPermanent(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            '_id'     => 'abc123',
            'status'  => 'failed',
            'message' => 'unreachable',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('unreachable', $update->errorMessage);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('POST', '/x', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackReturnsInboundMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'type'     => 'receive',
            'sender'   => '+61444444444',
            'receiver' => '+61400000123',
            'message'  => 'hi back',
            '_id'      => 'in-1',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+61444444444', $messages[0]->from);
        $this->assertSame('+61400000123', $messages[0]->to);
        $this->assertSame('hi back', $messages[0]->body);
        $this->assertSame('in-1', $messages[0]->providerId);
    }

    public function testParseInboundCallbackEmptyForWrongType(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'type'   => 'send',
            'sender' => '+61444444444',
        ]);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testParseInboundCallbackEmptyWithoutSender(): void
    {
        $request = $this->buildRequest('POST', '/x', ['type' => 'receive']);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testInboundCallbackRespectsWebhookSecret(): void
    {
        $this->configure(['webhook_secret' => 'topsecret']);
        $request = $this->buildRequest('POST', '/x', ['type' => 'receive', 'sender' => '+61444444444']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));

        $requestOk = $this->buildRequest('POST', '/x', [
            'type'   => 'receive',
            'sender' => '+61444444444',
            'secret' => 'topsecret',
        ]);
        $this->assertTrue($this->createProvider()->validateInboundCallback($requestOk));
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
