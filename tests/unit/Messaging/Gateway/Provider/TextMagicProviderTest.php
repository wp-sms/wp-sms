<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\TextMagicProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class TextMagicProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'tm-user';
    private const API_KEY = 'tm-test-api-key';

    protected function createProvider(): AbstractProvider
    {
        return new TextMagicProvider();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args']
        );
    }

    private function configure(array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'textmagic' => [
                'shared' => [
                    'username' => self::USERNAME,
                    'api_key'  => self::API_KEY,
                ],
                'channels' => [
                    'sms' => $smsOverrides,
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+447860021130', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 201): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    /**
     * Multi-URL GET mock — TextMagic's testConnection hits /ping then /user.
     * @param array<string, array{body: string|array, code?: int}> $responsesByPath
     */
    private function mockHttpGetByPath(array $responsesByPath): void
    {
        $GLOBALS['_test_wp_remote_get'] = function (string $url) use ($responsesByPath) {
            foreach ($responsesByPath as $path => $resp) {
                if (str_contains($url, $path)) {
                    $body = $resp['body'];
                    return [
                        'body'     => is_string($body) ? $body : json_encode($body),
                        'response' => ['code' => $resp['code'] ?? 200],
                    ];
                }
            }
            return new \WP_Error('no_mock', "No mock for {$url}");
        };
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testGetIdReturnsTextMagic(): void
    {
        $this->assertSame('textmagic', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertTrue($schema['shared']['username']['required']);

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertFalse($schema['channels']['sms']['from']['required']);
        $this->assertTrue($schema['channels']['sms']['from']['dynamic']);
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(TextMagicProvider::TESTED);
    }

    public function testIsConfiguredRequiresUsernameAndApiKey(): void
    {
        // No config: not configured.
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertFalse($this->createProvider()->isConfigured());

        // Username only: still not configured.
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'textmagic' => ['shared' => ['username' => self::USERNAME]],
        ];
        $this->assertFalse($this->createProvider()->isConfigured());

        // Both creds: configured (sms channel has no required fields).
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send ---

    public function testSmsSendUsesPostWithCorrectAuthHeadersAndStripsPlusFromPhone(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 1, 'messageId' => 12345]);

        $this->createProvider()->send($this->createMessage('+447860021130', 'Hi'));

        $this->assertSame(
            'https://rest.textmagic.com/api/v2/messages',
            $GLOBALS['_test_wp_remote_post_last_url']
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::USERNAME, $args['headers']['X-TM-Username']);
        $this->assertSame(self::API_KEY, $args['headers']['X-TM-Key']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('Hi', $body['text']);
        $this->assertSame('447860021130', $body['phones'], 'leading + must be stripped before send');
        $this->assertArrayNotHasKey('from', $body, 'from omitted when not configured');
    }

    public function testSmsSendIncludesFromWhenConfigured(): void
    {
        $this->configure(['from' => 'AcmeCorp']);
        $this->mockHttpPost(['id' => 1, 'messageId' => 7]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('AcmeCorp', $body['from']);
    }

    public function testSmsSendReturnsQueuedWithMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 1, 'messageId' => 12345], 201);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('12345', $result->providerId);
    }

    public function testSmsSendFallsBackToIdWhenMessageIdMissing(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 67890], 201);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('67890', $result->providerId);
    }

    public function testSmsSendReturnsFailedOnNon201(): void
    {
        $this->configure();
        $this->mockHttpPost(['message' => 'Invalid phone'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid phone', $result->error);
        $this->assertSame(400, $result->meta['textmagic_code']);
    }

    public function testSmsSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['message' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSmsSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsFormattedBalance(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'balance'  => 12.34,
            'currency' => ['htmlSymbol' => '$', 'unicodeSymbol' => '$'],
        ]);

        $this->assertSame('$12.34', $this->createProvider()->getCredit());
    }

    public function testGetCreditFallsBackToUnicodeSymbol(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'balance'  => 5,
            'currency' => ['unicodeSymbol' => '€'],
        ]);

        $this->assertSame('€5.00', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnauthorized(): void
    {
        $this->configure();
        $this->mockHttpGet(['message' => 'unauth'], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkOnPingSuccess(): void
    {
        $this->configure();
        $this->mockHttpGetByPath([
            '/ping' => ['body' => ['ping' => 'pong']],
            '/user' => ['body' => ['balance' => 7.5, 'currency' => ['htmlSymbol' => '$']]],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('$7.50', $result->message);
        $this->assertSame('$7.50', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGetByPath([
            '/ping' => ['body' => ['error' => 'unauth'], 'code' => 401],
        ]);

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

    public function testStatusCallbackVerifiesHmacSha256(): void
    {
        $this->configure();
        $body = json_encode(['message_id' => 'abc', 'status' => 'd', 'timestamp' => 1700000000]);
        $signature = hash_hmac('sha256', $body, self::API_KEY);

        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body($body);
        $request->set_header('x-tm-signature', $signature);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsBadSignature(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode(['message_id' => 'abc', 'status' => 'd']));
        $request->set_header('x-tm-signature', 'totally-bogus');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsMissingSignatureHeader(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode(['message_id' => 'abc', 'status' => 'd']));

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsDeliveredStatus(): void
    {
        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode(['message_id' => 'abc', 'status' => 'd']));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('abc', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testParseStatusCallbackMapsFailureAsPermanent(): void
    {
        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode(['message_id' => 'xyz', 'status' => 'r']));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('r', $update->errorCode);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode([]));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode([
            'message_id' => 'in-1',
            'from'       => '447860021130',
            'to'         => '447111000000',
            'text'       => 'STOP',
            'timestamp'  => 1700000000,
        ]));

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('447860021130', $messages[0]->from);
        $this->assertSame('447111000000', $messages[0]->to);
        $this->assertSame('STOP', $messages[0]->body);
        $this->assertSame('in-1', $messages[0]->providerId);
        $this->assertSame(1700000000, $messages[0]->meta['received_at']);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode(['text' => 'orphan']));

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testInboundCallbackUsesSameSignatureScheme(): void
    {
        $this->configure();
        $body = json_encode(['from' => '447860021130', 'text' => 'STOP', 'message_id' => 'x']);
        $signature = hash_hmac('sha256', $body, self::API_KEY);

        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body($body);
        $request->set_header('x-tm-signature', $signature);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsForFromFieldFlattensSourcesBuckets(): void
    {
        $this->mockHttpGet([
            'shared'    => ['447111000000', '447111000001'],
            'dedicated' => ['447222000000'],
            'senderIds' => ['AcmeCorp'],
            'user'      => ['+447333000000'],
        ]);

        $config = [
            'shared' => ['username' => self::USERNAME, 'api_key' => self::API_KEY],
            'channels' => [],
        ];

        $options = $this->createProvider()->getConfigOptions('from', 'sms', $config);

        $this->assertCount(5, $options);
        $values = array_column($options, 'value');
        $this->assertContains('447111000000', $values);
        $this->assertContains('AcmeCorp', $values);
        $this->assertContains('+447333000000', $values);
        foreach ($options as $opt) {
            $this->assertSame($opt['value'], $opt['label']);
        }
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame(
            [],
            $this->createProvider()->getConfigOptions('unknown', 'sms', [])
        );
    }

    public function testGetConfigOptionsReturnsEmptyForNonSmsSection(): void
    {
        $this->assertSame(
            [],
            $this->createProvider()->getConfigOptions('from', 'shared', [])
        );
    }

    public function testGetConfigOptionsReturnsEmptyOnApiFailure(): void
    {
        $this->mockHttpGet(['message' => 'unauth'], 401);

        $config = [
            'shared' => ['username' => self::USERNAME, 'api_key' => self::API_KEY],
            'channels' => [],
        ];

        $this->assertSame(
            [],
            $this->createProvider()->getConfigOptions('from', 'sms', $config)
        );
    }
}
