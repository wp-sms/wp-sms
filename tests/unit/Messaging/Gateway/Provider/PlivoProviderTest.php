<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\PlivoProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class PlivoProviderTest extends AbstractProviderTestCase
{
    private const AUTH_ID = 'MAXXXXXXXXXXXXXXXXXX';
    private const AUTH_TOKEN = 'plivo-test-auth-token';
    private const SMS_FROM = '+15551234567';
    private const WA_FROM = '+15557654321';

    protected function createProvider(): AbstractProvider
    {
        return new PlivoProvider();
    }

    private function configure(array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'plivo' => [
                'shared' => [
                    'auth_id'    => self::AUTH_ID,
                    'auth_token' => self::AUTH_TOKEN,
                ],
                'channels' => array_merge([
                    'sms'      => ['from_number' => self::SMS_FROM],
                    'whatsapp' => ['from_number' => self::WA_FROM],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+15559876543', string $body = 'Hello', array $meta = []): Message
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

    private function expectedBasicAuth(): string
    {
        return 'Basic ' . base64_encode(self::AUTH_ID . ':' . self::AUTH_TOKEN);
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('plivo', $p->getId());
        $this->assertSame(['sms', 'whatsapp'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(PlivoProvider::TESTED);
    }

    public function testConfigSchemaHasPerChannelFromNumber(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('auth_id', $schema['shared']);
        $this->assertArrayHasKey('auth_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['auth_token']['type']);

        $this->assertArrayHasKey('from_number', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from_number']['dynamic']);
        $this->assertArrayHasKey('from_number', $schema['channels']['whatsapp']);
    }

    public function testIsConfiguredForChannelSmsButNotWhatsappWhenWaMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'plivo' => [
                'shared' => [
                    'auth_id'    => self::AUTH_ID,
                    'auth_token' => self::AUTH_TOKEN,
                ],
                'channels' => [
                    'sms'      => ['from_number' => self::SMS_FROM],
                    'whatsapp' => [],
                ],
            ],
        ];

        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('sms'));
        $this->assertFalse($p->isConfiguredForChannel('whatsapp'));
    }

    // --- Send: SMS ---

    public function testSmsSendQueuedReturnsMessageUuid(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'api_id'       => 'api-123',
            'message'      => 'message(s) queued',
            'message_uuid' => ['uuid-abc-001'],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('uuid-abc-001', $result->providerId);
    }

    public function testSmsSendPostsToCorrectUrlAndAuthHeader(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'api_id'       => 'api-1',
            'message_uuid' => ['uuid-1'],
        ]);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'Hi'));

        $this->assertSame(
            'https://api.plivo.com/v1/Account/' . self::AUTH_ID . '/Message/',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedBasicAuth(), $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::SMS_FROM, $body['src']);
        $this->assertSame('+15559876543', $body['dst']);
        $this->assertSame('Hi', $body['text']);
        $this->assertArrayNotHasKey('type', $body);
        $this->assertArrayNotHasKey('media_urls', $body);
        $this->assertStringContainsString('callbacks/plivo/status', $body['url']);
    }

    public function testSmsWithMediaUrlsSetsMmsType(): void
    {
        $this->configure();
        $this->mockHttpPost(['api_id' => 'api-1', 'message_uuid' => ['uuid-1']]);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'See pic', [
            'media_urls' => ['https://example.com/a.jpg', 'https://example.com/b.png'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('mms', $body['type']);
        $this->assertSame(['https://example.com/a.jpg', 'https://example.com/b.png'], $body['media_urls']);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'unauth'], 401);

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

    public function testSendReturnsFailedWhenChannelFromMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'plivo' => [
                'shared'   => ['auth_id' => self::AUTH_ID, 'auth_token' => self::AUTH_TOKEN],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('From Number', $result->error);
    }

    public function testSendBubblesUpProviderError(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'invalid number', 'api_id' => 'api-err'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('invalid number', $result->error);
        $this->assertSame('api-err', $result->meta['plivo_api_id']);
    }

    // --- Send: WhatsApp ---

    public function testWhatsAppSendSetsType(): void
    {
        $this->configure();
        $this->mockHttpPost(['api_id' => 'api-w', 'message_uuid' => ['uuid-w-1']]);

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', 'Hi WA'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('whatsapp', $body['type']);
        $this->assertSame(self::WA_FROM, $body['src']);
        $this->assertSame('Hi WA', $body['text']);
    }

    public function testWhatsAppSendIncludesTemplateMeta(): void
    {
        $this->configure();
        $this->mockHttpPost(['api_id' => 'api-w', 'message_uuid' => ['uuid-w-2']]);

        $template = [
            'name'       => 'order_update',
            'language'   => 'en_US',
            'components' => [
                ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Alice']]],
            ],
        ];

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', '', [
            'template' => $template,
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame($template, $body['template']);
    }

    public function testWhatsAppSendIncludesMediaUrls(): void
    {
        $this->configure();
        $this->mockHttpPost(['api_id' => 'api-w', 'message_uuid' => ['uuid-w-3']]);

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', 'Caption', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('whatsapp', $body['type']);
        $this->assertSame(['https://example.com/photo.jpg'], $body['media_urls']);
    }

    // --- Credit / Test Connection ---

    public function testGetCreditReturnsFormattedUsd(): void
    {
        $this->configure();
        $this->mockHttpGet(['cash_credits' => '1.80900', 'auth_id' => self::AUTH_ID]);

        $this->assertSame('1.8090 USD', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['cash_credits' => '5.00000', 'auth_id' => self::AUTH_ID]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5.00000', $result->message);
        $this->assertSame('5.00000', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauth'], 401);

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

    public function testValidateStatusCallbackAcceptsValidV3Signature(): void
    {
        $this->configure();
        $url = 'https://example.com/wp-json/wsms/v1/callbacks/plivo/status';
        $nonce = 'nonce-12345';
        $params = [
            'MessageUUID' => 'uuid-callback-1',
            'From'        => '+15551234567',
            'To'          => '+15559876543',
            'Status'      => 'delivered',
        ];
        $signature = $this->computeExpectedSignature('POST', $url, $nonce, self::AUTH_TOKEN, $params);

        $request = $this->buildRequest('POST', $url, $params, [
            'x-plivo-signature-v3'       => $signature,
            'x-plivo-signature-v3-nonce' => $nonce,
        ]);

        // Override callback URL for this test (default uses RestRoute, not the configured AT URL)
        $provider = new class extends PlivoProvider {
            public function getStatusCallbackUrl(): string
            {
                return 'https://example.com/wp-json/wsms/v1/callbacks/plivo/status';
            }
        };

        $this->assertTrue($provider->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsInvalidSignature(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/whatever', [
            'MessageUUID' => 'uuid-1', 'Status' => 'delivered',
        ], [
            'x-plivo-signature-v3'       => 'totally-bogus',
            'x-plivo-signature-v3-nonce' => 'n',
        ]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingHeaders(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['MessageUUID' => 'a']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsCommaSeparatedSignatureList(): void
    {
        $this->configure();
        $url = 'https://example.com/wp-json/wsms/v1/callbacks/plivo/status';
        $nonce = 'nonce-multi';
        $params = ['MessageUUID' => 'uuid-1', 'Status' => 'sent'];
        $valid = $this->computeExpectedSignature('POST', $url, $nonce, self::AUTH_TOKEN, $params);

        $request = $this->buildRequest('POST', $url, $params, [
            'x-plivo-signature-v3'       => "stale-sig,{$valid},another-sig",
            'x-plivo-signature-v3-nonce' => $nonce,
        ]);

        $provider = new class extends PlivoProvider {
            public function getStatusCallbackUrl(): string
            {
                return 'https://example.com/wp-json/wsms/v1/callbacks/plivo/status';
            }
        };

        $this->assertTrue($provider->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsStatuses(): void
    {
        $cases = [
            'queued'      => ['queued', false],
            'sent'        => ['sent', false],
            'delivered'   => ['delivered', false],
            'read'        => ['delivered', false],
            'undelivered' => ['failed', false],
            'failed'      => ['failed', false],
            'rejected'    => ['failed', false],
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => [$expected]) {
            $request = $this->buildRequest('POST', '/x', [
                'MessageUUID' => 'uuid-' . $raw,
                'Status'      => $raw,
            ]);
            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expected, $updates[0]->status, "wrong mapping for {$raw}");
        }
    }

    public function testParseStatusCallbackMarksKnownPermanentErrorCodes(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'MessageUUID' => 'uuid-bad',
            'Status'      => 'failed',
            'ErrorCode'   => '130',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertTrue($update->permanent);
        $this->assertSame('130', $update->errorCode);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('POST', '/x', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'From'        => '+15559876543',
            'To'          => '+15551234567',
            'Text'        => 'Hello back',
            'Type'        => 'sms',
            'MessageUUID' => 'uuid-in-1',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+15559876543', $msg->from);
        $this->assertSame('+15551234567', $msg->to);
        $this->assertSame('Hello back', $msg->body);
        $this->assertSame('sms', $msg->meta['type']);
        $this->assertSame('uuid-in-1', $msg->providerId);
    }

    public function testParseInboundCallbackCollectsMediaUrls(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'From'        => '+15559876543',
            'To'          => '+15551234567',
            'Text'        => 'pic',
            'Type'        => 'mms',
            'MessageUUID' => 'uuid-mms-1',
            'Media0'      => 'https://media.plivo.com/0.jpg',
            'Media1'      => 'https://media.plivo.com/1.jpg',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);
        $this->assertSame([
            'https://media.plivo.com/0.jpg',
            'https://media.plivo.com/1.jpg',
        ], $messages[0]->meta['media_urls']);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildRequest('POST', '/x', []);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsReturnsNumbersWithCapabilities(): void
    {
        $this->mockHttpGet([
            'objects' => [
                ['number' => '15551234567', 'sms_enabled' => true,  'mms_enabled' => true,  'voice_enabled' => true],
                ['number' => '15557654321', 'sms_enabled' => true,  'mms_enabled' => false, 'voice_enabled' => false],
                ['number' => '15550000000', 'sms_enabled' => false, 'mms_enabled' => false, 'voice_enabled' => true],
            ],
        ]);

        $config = [
            'shared' => ['auth_id' => self::AUTH_ID, 'auth_token' => self::AUTH_TOKEN],
            'channels' => [],
        ];

        $options = $this->createProvider()->getConfigOptions('from_number', 'sms', $config);

        $this->assertCount(3, $options);
        $this->assertSame('+15551234567', $options[0]['value']);
        $this->assertStringContainsString('SMS, MMS, Voice', $options[0]['label']);
        $this->assertSame('+15557654321', $options[1]['value']);
        $this->assertStringContainsString('SMS', $options[1]['label']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('unknown', 'sms', []));
    }

    public function testGetConfigOptionsReturnsEmptyForWhatsappSection(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('from_number', 'whatsapp', []));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = []): \WP_REST_Request
    {
        $request = new \WP_REST_Request($method, $route);
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        foreach ($headers as $k => $v) {
            $request->set_header($k, $v);
        }
        // Mock get_method() since the bootstrap stub doesn't expose it
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

    private function computeExpectedSignature(string $method, string $url, string $nonce, string $authToken, array $params): string
    {
        $parsed = parse_url($url);
        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '')
            . (isset($parsed['port']) ? ':' . $parsed['port'] : '')
            . ($parsed['path'] ?? '/');
        $query = $parsed['query'] ?? '';

        if ($method === 'GET') {
            $merged = $params;
            if ($query) {
                parse_str($query, $existing);
                $merged = array_merge($existing, $merged);
            }
            ksort($merged, SORT_STRING);
            $qs = http_build_query($merged, '', '&', PHP_QUERY_RFC3986);
            $canonical = $qs ? $base . '?' . $qs : $base;
        } else {
            $hasParams = !empty($params);
            if ($query !== '') {
                parse_str($query, $existing);
                ksort($existing, SORT_STRING);
                $canonical = $base . '?' . http_build_query($existing, '', '&', PHP_QUERY_RFC3986);
                if ($hasParams) {
                    $canonical .= '.';
                }
            } else {
                $canonical = $hasParams ? $base . '?' : $base;
            }
            if ($hasParams) {
                ksort($params, SORT_STRING);
                foreach ($params as $k => $v) {
                    $canonical .= $k . (is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? json_encode($v) : (string) $v));
                }
            }
        }

        return base64_encode(hash_hmac('sha256', $canonical . '.' . $nonce, $authToken, true));
    }
}
