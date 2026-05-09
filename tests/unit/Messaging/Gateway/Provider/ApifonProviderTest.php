<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\ApifonProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class ApifonProviderTest extends AbstractProviderTestCase
{
    private const TOKEN = 'apifon-test-token';
    private const SECRET = 'apifon-test-secret';
    private const SENDER = 'MyBrand';
    private const WEBHOOK_TOKEN = 'wh-token-xyz';

    protected function createProvider(): AbstractProvider
    {
        return new ApifonProvider();
    }

    private function configure(array $sharedOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'apifon' => [
                'shared' => array_merge([
                    'token'         => self::TOKEN,
                    'secret'        => self::SECRET,
                    'webhook_token' => self::WEBHOOK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => ['sender_id' => self::SENDER],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+306900000000', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
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

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(ApifonProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('apifon', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaHasSharedAndSmsKeys(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('token', $schema['shared']);
        $this->assertArrayHasKey('secret', $schema['shared']);
        $this->assertArrayHasKey('webhook_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['secret']['type']);
        $this->assertTrue($schema['shared']['token']['required']);
        $this->assertTrue($schema['shared']['secret']['required']);
        $this->assertFalse($schema['shared']['webhook_token']['required'] ?? false);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
    }

    // --- Send ---

    public function testSmsSendQueuedReturnsMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'request_id' => 'req-123',
            'results'    => [
                '306900000000' => [
                    'message_id'         => 'msg-001',
                    'status_code'        => 0,
                    'status_description' => 'QUEUED',
                ],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-001', $result->providerId);
        $this->assertSame('req-123', $result->meta['request_id']);
    }

    public function testSmsSendFallsBackToRequestIdWhenNoMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'request_id' => 'req-only',
            'results'    => [],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('req-only', $result->providerId);
    }

    public function testSmsSendPostsToCorrectUrlAndHeaders(): void
    {
        $this->configure();
        $this->mockHttpPost(['request_id' => 'r', 'results' => []]);

        $this->createProvider()->send($this->createMessage('+306900000000', 'Hi'));

        $this->assertSame(
            'https://ars.apifon.com/services/api/v1/sms/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);
        $this->assertNotEmpty($args['headers']['Authorization']);
        $this->assertStringStartsWith('ApifonWS ' . self::TOKEN . ':', $args['headers']['Authorization']);
        $this->assertNotEmpty($args['headers']['X-ApifonWS-Date']);

        $body = json_decode($args['body'], true);
        $this->assertSame('Hi', $body['message']['text']);
        $this->assertSame(self::SENDER, $body['message']['sender_id']);
        $this->assertSame('306900000000', $body['subscribers'][0]['number']);
        $this->assertStringContainsString('callbacks/apifon/status', $body['message']['callback_url']);
    }

    public function testSmsSendComputesExpectedHmacSignature(): void
    {
        // Capture the actual generated headers + body, then re-compute the
        // expected signature using the documented canonical string. This proves
        // the implementation matches Apifon's spec byte-for-byte.
        $this->configure();
        $this->mockHttpPost(['request_id' => 'r', 'results' => []]);

        $this->createProvider()->send($this->createMessage('+306900000000', 'Hi'));

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $authHeader = $args['headers']['Authorization'];
        $date = $args['headers']['X-ApifonWS-Date'];
        $body = $args['body'];

        // Canonical: METHOD\n + PATH\n + BODY\n + DATE
        $stringToSign = "POST\n/services/api/v1/sms/send\n{$body}\n{$date}";
        $expectedSig = base64_encode(hash_hmac('sha256', $stringToSign, self::SECRET, true));
        $expectedHeader = 'ApifonWS ' . self::TOKEN . ':' . $expectedSig;

        $this->assertSame($expectedHeader, $authHeader);

        // Verify the date header matches RFC 1123 GMT format per Apifon's sample
        // headers, e.g. "Thu, 29 Sep 2016 12:18:56 GMT" — literal "GMT", not "+0000".
        $this->assertMatchesRegularExpression(
            '/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/',
            $date,
        );
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'unauthorized'], 401);

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

    public function testSendReturnsFailedWhenSenderIdMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'apifon' => [
                'shared'   => ['token' => self::TOKEN, 'secret' => self::SECRET],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    public function testSendBubblesUpProviderError(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status_code'        => 5,
            'status_description' => 'INVALID_RECIPIENT',
        ], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('INVALID_RECIPIENT', $result->error);
        $this->assertSame('5', $result->meta['apifon_status_code']);
    }

    // --- Credit / Test Connection ---

    public function testGetCreditReturnsFormattedBalance(): void
    {
        $this->configure();
        // Apifon balance response: { "balance": "9864.0", "reserved": "0.0", "plafon": "0.0", ... }
        // No "currency" field — display the raw amount.
        $this->mockHttpPost(['balance' => '9864.0', 'reserved' => '0.0', 'plafon' => '0.0']);

        $this->assertSame('9,864.0000', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpPost(['balance' => '5.25', 'reserved' => '0.0', 'plafon' => '0.0']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5.25', (string) $result->message);
        $this->assertSame('5.25', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testGetCreditUsesPostNotGetForBalance(): void
    {
        // Regression: Apifon's /balance is POST, not GET. Sending GET returns 405.
        $this->configure();
        $this->mockHttpPost(['balance' => '1.0']);

        $this->createProvider()->getCredit();

        $this->assertSame(
            'https://ars.apifon.com/services/api/v1/balance',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
        // Body must be empty per Apifon docs; signature canonical string includes the empty body
        $this->assertSame('', $GLOBALS['_test_wp_remote_post_last_args']['body']);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => self::WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenNoTokenConfigured(): void
    {
        $this->configure(['webhook_token' => '']);
        $request = $this->buildRequest('POST', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsStatuses(): void
    {
        $cases = [
            'SENT'          => ['sent', false],
            'DELIVERED'     => ['delivered', false],
            'FAILED'        => ['failed', true],
            'UNDELIVERABLE' => ['failed', true],
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => [$expectedStatus, $expectedPermanent]) {
            $request = $this->buildRequestWithJsonBody([
                'request_id' => 'req-x',
                'results'    => [
                    [
                        'message_id' => 'msg-' . $raw,
                        'status'     => $raw,
                        'status_code' => 0,
                    ],
                ],
            ]);

            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expectedStatus, $updates[0]->status, "wrong status for {$raw}");
            $this->assertSame($expectedPermanent, $updates[0]->permanent, "wrong permanent flag for {$raw}");
            $this->assertSame('msg-' . $raw, $updates[0]->providerId);
        }
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequestWithJsonBody([]);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testGetStatusCallbackUrlIncludesToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();

        $this->assertStringContainsString('callbacks/apifon/status', $url);
        $this->assertStringContainsString('token=' . self::WEBHOOK_TOKEN, $url);
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => self::WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', []);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = $this->buildRequestWithJsonBody([
            'message_id'    => 'in-1',
            'destination'   => '+306911111111',
            'sender_id'     => self::SENDER,
            'reply_message' => 'STOP',
            'date_received' => '2026-05-09T12:34:56Z',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+306911111111', $msg->from);
        $this->assertSame(self::SENDER, $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('in-1', $msg->providerId);
        $this->assertSame('2026-05-09T12:34:56Z', $msg->meta['date_received']);
    }

    public function testParseInboundCallbackEmptyWithoutDestination(): void
    {
        $request = $this->buildRequestWithJsonBody(['reply_message' => 'STOP']);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testGetInboundCallbackUrlIncludesToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getInboundCallbackUrl();

        $this->assertStringContainsString('callbacks/apifon/inbound', $url);
        $this->assertStringContainsString('token=' . self::WEBHOOK_TOKEN, $url);
    }

    // --- Helpers ---

    private function buildRequest(string $method, array $params): \WP_REST_Request
    {
        $request = new \WP_REST_Request($method, '/x');
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        return $request;
    }

    private function buildRequestWithJsonBody(array $payload): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode($payload));
        return $request;
    }
}
