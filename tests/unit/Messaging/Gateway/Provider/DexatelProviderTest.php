<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\DexatelProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class DexatelProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'dxt-test-key-abc';
    private const WEBHOOK_SECRET = 'dxt-webhook-secret';
    private const SMS_FROM = '+15551234567';
    private const WA_FROM = 'wsms_business';
    private const VIBER_FROM = 'WSMS Viber';
    private const RCS_FROM = 'wsms-rcs-agent';

    protected function createProvider(): AbstractProvider
    {
        return new DexatelProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'dexatel' => [
                'shared'   => array_merge([
                    'api_key'        => self::API_KEY,
                    'webhook_secret' => self::WEBHOOK_SECRET,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms'      => ['from' => self::SMS_FROM],
                    'whatsapp' => ['from' => self::WA_FROM],
                    'viber'    => ['from' => self::VIBER_FROM],
                    'rcs'      => ['from' => self::RCS_FROM],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+15559876543', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
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

    // --- Identity ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(DexatelProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('dexatel', $p->getId());
        $this->assertSame(['sms', 'whatsapp', 'viber', 'rcs'], $p->getSupportedChannels());
    }

    public function testConfigSchemaCoversAllFourChannels(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertArrayHasKey('webhook_secret', $schema['shared']);
        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertArrayHasKey('whatsapp', $schema['channels']);
        $this->assertArrayHasKey('viber', $schema['channels']);
        $this->assertArrayHasKey('rcs', $schema['channels']);
    }

    public function testSmsFromFieldIsMarkedDynamic(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $this->assertTrue($schema['channels']['sms']['from']['dynamic'] ?? false);
    }

    public function testIsConfiguredForChannelChecksFromField(): void
    {
        $this->configure(channelOverrides: ['sms' => ['from' => '']]);
        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('whatsapp'));
        $this->assertTrue($p->isConfiguredForChannel('viber'));
    }

    // --- Send ---

    public function testSmsSendQueuedReturnsMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'uuid-001', 'status' => 'submitted']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('uuid-001', $result->providerId);
    }

    public function testSmsSendUsesUnifiedEndpointAndApiKeyHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'uuid-001']]);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'Hi'));

        $this->assertSame('https://api.dexatel.com/v1/messages', $GLOBALS['_test_wp_remote_post_last_url']);
        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['X-Dexatel-Key']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::SMS_FROM, $body['data']['from']);
        $this->assertSame(['+15559876543'], $body['data']['to']);
        $this->assertSame('Hi', $body['data']['text']);
        $this->assertSame('sms', $body['data']['channel']);
    }

    public function testWhatsappSendUsesWhatsappChannelField(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'uuid-wa']]);

        $this->createProvider()->send($this->createMessage('whatsapp'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('whatsapp', $body['data']['channel']);
        $this->assertSame(self::WA_FROM, $body['data']['from']);
    }

    public function testViberSendUsesViberChannelField(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'uuid-vb']]);

        $this->createProvider()->send($this->createMessage('viber'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('viber', $body['data']['channel']);
        $this->assertSame(self::VIBER_FROM, $body['data']['from']);
    }

    public function testRcsSendUsesRcsChannelField(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'uuid-rcs']]);

        $this->createProvider()->send($this->createMessage('rcs'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('rcs', $body['data']['channel']);
        $this->assertSame(self::RCS_FROM, $body['data']['from']);
    }

    public function testInvalidApiKeyMaps401ToFriendlyError(): void
    {
        $this->configure();
        $this->mockHttpPost(['errors' => [['code' => 'unauthorized', 'message' => 'Invalid key']]], 401);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Dexatel API key', $result->error);
    }

    public function testGenericApiErrorPropagatesProviderMessage(): void
    {
        $this->configure();
        $this->mockHttpPost(['errors' => [['code' => 'invalid_recipient', 'message' => 'Bad number']]], 422);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Bad number', $result->error);
        $this->assertSame('invalid_recipient', $result->meta['dexatel_error']);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedForUnsupportedChannel(): void
    {
        $this->configure();
        $result = $this->createProvider()->send($this->createMessage('voice'));
        $this->assertFalse($result->success);
        $this->assertStringContainsString('does not support channel voice', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsBalanceFromAccountsListShape(): void
    {
        // /v1/accounts may return data as an array of accounts.
        $this->configure();
        $this->mockHttpGet(['data' => [['balance' => '125.50', 'currency' => 'USD']]]);

        $this->assertSame('125.50 USD', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsBalanceFromSingleAccountShape(): void
    {
        // ...or it may return data as a single account object.
        $this->configure();
        $this->mockHttpGet(['data' => ['balance' => '7.25', 'currency' => 'EUR']]);

        $this->assertSame('7.25 EUR', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauth'], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = ['dexatel' => ['shared' => []]];
        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionOkOnAuthSuccess(): void
    {
        $this->configure();
        $this->mockHttpGet(['data' => []]);

        $result = $this->createProvider()->testConnection();
        $this->assertTrue($result->success);
        $this->assertStringContainsString('Connected to Dexatel', $result->message);
    }

    public function testTestConnectionFailsOnInvalidKey(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Dexatel API key', $result->message);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->testConnection();
        $this->assertFalse($result->success);
    }

    // --- Status callback parsing ---

    public function testParseStatusCallbackMapsDelivered(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'data' => ['event' => 'delivered', 'message_id' => 'uuid-001', 'channel' => 'sms'],
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('uuid-001', $update->providerId);
        $this->assertSame('delivered', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testParseStatusCallbackMapsDroppedAsPermanentFailure(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'data' => ['event' => 'dropped', 'message_id' => 'uuid-fail'],
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertStringContainsString('Dexatel: dropped', $update->errorMessage);
    }

    public function testParseStatusCallbackMapsSubmittedAndPendingAsQueued(): void
    {
        foreach (['submitted', 'pending'] as $event) {
            $request = $this->buildRequest('POST', '/x', [], [], json_encode([
                'data' => ['event' => $event, 'message_id' => 'uuid-' . $event],
            ]));

            $update = $this->createProvider()->parseStatusCallback($request)[0];
            $this->assertSame('queued', $update->status, "event={$event}");
        }
    }

    public function testParseStatusCallbackIgnoresInboundEvent(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'data' => ['event' => 'message', 'message_id' => 'uuid-in', 'text' => 'hi'],
        ]));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackReturnsEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode(['data' => []]));
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback parsing ---

    public function testParseInboundCallbackBuildsInboundMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'data' => [
                'event'      => 'message',
                'message_id' => 'in-001',
                'from'       => '+15559876543',
                'to'         => self::SMS_FROM,
                'text'       => 'STOP',
                'channel'    => 'sms',
                'timestamp'  => '2026-04-30T10:00:00Z',
            ],
        ]));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('+15559876543', $msg->from);
        $this->assertSame(self::SMS_FROM, $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('in-001', $msg->providerId);
        $this->assertSame('sms', $msg->meta['channel']);
    }

    public function testParseInboundCallbackReturnsEmptyForMissingFrom(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode(['data' => ['text' => 'hi']]));
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Signature verification ---

    public function testStatusCallbackVerifiesValidSignature(): void
    {
        $this->configure();
        $body = '{"data":{"event":"delivered","message_id":"x"}}';
        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $request = $this->buildRequest('POST', '/x', [], ['x-dexatel-signature' => $signature], $body);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsTamperedBody(): void
    {
        $this->configure();
        $signedBody = '{"data":{"event":"delivered","message_id":"x"}}';
        $signature = hash_hmac('sha256', $signedBody, self::WEBHOOK_SECRET);
        $tamperedBody = '{"data":{"event":"delivered","message_id":"y"}}';

        $request = $this->buildRequest('POST', '/x', [], ['x-dexatel-signature' => $signature], $tamperedBody);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsMissingSignatureWhenSecretConfigured(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', [], [], '{}');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptsAnythingWhenSecretEmpty(): void
    {
        $this->configure(sharedOverrides: ['webhook_secret' => '']);
        $request = $this->buildRequest('POST', '/x', [], [], '{}');

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testInboundCallbackUsesSameSignatureScheme(): void
    {
        $this->configure();
        $body = '{"data":{"event":"message","from":"+15551234567"}}';
        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $request = $this->buildRequest('POST', '/x', [], ['x-dexatel-signature' => $signature], $body);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testCallbackUrlsIncludeProviderSlug(): void
    {
        $p = $this->createProvider();
        $this->assertStringContainsString('callbacks/dexatel/status', $p->getStatusCallbackUrl());
        $this->assertStringContainsString('callbacks/dexatel/inbound', $p->getInboundCallbackUrl());
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsReturnsEmptyForNonSmsField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('from', 'whatsapp', []));
        $this->assertSame([], $this->createProvider()->getConfigOptions('something_else', 'sms', []));
    }

    public function testGetConfigOptionsReturnsEmptyWithoutApiKey(): void
    {
        $config = ['shared' => [], 'channels' => []];
        $this->assertSame([], $this->createProvider()->getConfigOptions('from', 'sms', $config));
    }

    public function testGetConfigOptionsFetchesSendersFromApi(): void
    {
        $capturedUrl = null;
        $capturedArgs = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$capturedUrl, &$capturedArgs) {
            $capturedUrl = $url;
            $capturedArgs = $args;
            return [
                'body' => json_encode([
                    'data' => [
                        ['phone' => '+12025550100', 'display_name' => 'Sales', 'channel' => 'sms'],
                        ['name' => 'WSMS Brand', 'channel' => 'viber'],
                    ],
                ]),
                'response' => ['code' => 200],
            ];
        };

        $config = ['shared' => ['api_key' => self::API_KEY]];

        $options = $this->createProvider()->getConfigOptions('from', 'sms', $config);

        $this->assertCount(2, $options);
        $this->assertSame('+12025550100', $options[0]['value']);
        $this->assertStringContainsString('sms', $options[0]['label']);
        $this->assertStringContainsString('Sales', $options[0]['label']);
        $this->assertSame('WSMS Brand', $options[1]['value']);

        $this->assertSame('https://api.dexatel.com/v1/senders', $capturedUrl);
        $this->assertSame(self::API_KEY, $capturedArgs['headers']['X-Dexatel-Key']);
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = [], ?string $body = null): \WP_REST_Request
    {
        return new class($method, $route, $params, $headers, $body) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, array $params, array $headers, ?string $body) {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) $this->set_param($k, $v);
                foreach ($headers as $k => $v) $this->set_header($k, $v);
                if ($body !== null) $this->set_body($body);
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }
}
