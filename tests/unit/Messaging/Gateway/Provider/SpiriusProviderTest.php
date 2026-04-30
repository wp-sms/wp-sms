<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SpiriusProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SpiriusProviderTest extends AbstractProviderTestCase
{
    private const USERNAME       = 'spirius-user';
    private const SHARED_KEY     = 'spirius-shared-key-1234567890';
    private const FROM           = 'WSMS';
    private const CALLBACK_TOKEN = 'spirius-cb-token-9876543210';

    protected function createProvider(): AbstractProvider
    {
        return new SpiriusProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'spirius' => [
                'shared' => array_merge([
                    'username'       => self::USERNAME,
                    'shared_key'     => self::SHARED_KEY,
                    'auth_mode'      => 'hmac',
                    'callback_token' => self::CALLBACK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge([
                        'from'      => self::FROM,
                        'from_type' => 'alphanumeric',
                    ], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+46700000000', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
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

    private function buildRequest(string $method, array $params, ?string $jsonBody = null): \WP_REST_Request
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
        $this->assertSame('spirius', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SpiriusProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertTrue($schema['shared']['username']['required']);

        $this->assertArrayHasKey('shared_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['shared_key']['type']);

        $this->assertArrayHasKey('auth_mode', $schema['shared']);
        $this->assertSame('select', $schema['shared']['auth_mode']['type']);
        $this->assertSame('hmac', $schema['shared']['auth_mode']['default']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertArrayHasKey('from_type', $schema['channels']['sms']);
        $this->assertSame('alphanumeric', $schema['channels']['sms']['from_type']['default']);
    }

    // --- Send ---

    public function testSendQueuedReturnsTransactionId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'detail'                => 'OK',
            'numberOfSms'           => 1,
            'transactionId'         => 'tx-abc-123',
            'remainingRequestQuota' => 9999,
        ], 202);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('tx-abc-123', $result->providerId);
    }

    public function testSendPostsToCorrectUrlWithJsonBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['transactionId' => 'tx-1'], 202);

        $this->createProvider()->send($this->createMessage('+46700000001', 'Hej'));

        $this->assertSame(
            'https://rest.spirius.com/v1/sms/mt/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(['+46700000001'], $body['to']);
        $this->assertSame('Hej', $body['message']);
        $this->assertSame(self::FROM, $body['from']);
        $this->assertSame('alphanumeric', $body['fromType']);
        $this->assertTrue($body['deliveryReport']);
        $this->assertStringContainsString('token=' . self::CALLBACK_TOKEN, $body['dlrCallbackUrl']);
    }

    public function testHmacAuthHeadersIncludeTimestampAndSpiriusSmsV1Prefix(): void
    {
        $this->configure();
        $this->mockHttpPost(['transactionId' => 'tx-1'], 202);

        $this->createProvider()->send($this->createMessage());

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $auth = $args['headers']['Authorization'];

        $this->assertStringStartsWith('SpiriusSmsV1 ' . self::USERNAME . ':', $auth);
        $this->assertArrayHasKey('X-SMS-Timestamp', $args['headers']);
        $this->assertMatchesRegularExpression('/^\d+$/', (string) $args['headers']['X-SMS-Timestamp']);

        // Re-derive the signature from the captured timestamp + body and confirm it matches.
        $timestamp  = (string) $args['headers']['X-SMS-Timestamp'];
        $bodyDigest = sha1($args['body']);
        $message    = implode("\n", ['SpiriusSmsV1', $timestamp, 'POST', '/v1/sms/mt/send', $bodyDigest]);
        $expected   = 'SpiriusSmsV1 ' . self::USERNAME . ':' . base64_encode(hash_hmac('sha256', $message, self::SHARED_KEY, true));

        $this->assertSame($expected, $auth);
    }

    public function testBasicAuthMode(): void
    {
        $this->configure(['auth_mode' => 'basic']);
        $this->mockHttpPost(['transactionId' => 'tx-1'], 202);

        $this->createProvider()->send($this->createMessage());

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(
            'Basic ' . base64_encode(self::USERNAME . ':' . self::SHARED_KEY),
            $args['headers']['Authorization'],
        );
        $this->assertArrayNotHasKey('X-SMS-Timestamp', $args['headers']);
    }

    public function testSendOmitsDlrCallbackWhenTokenMissing(): void
    {
        $this->configure(['callback_token' => '']);
        $this->mockHttpPost(['transactionId' => 'tx-1'], 202);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('dlrCallbackUrl', $body);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['detail' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedDetailOn4xx(): void
    {
        $this->configure();
        $this->mockHttpPost(['detail' => 'Invalid sender'], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid sender', $result->error);
        $this->assertSame(422, $result->meta['spirius_code']);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Test connection ---

    public function testTestConnectionPassesOn2xx(): void
    {
        $this->configure();
        $this->mockHttpGet([], 200);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet([], 401);

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

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => self::CALLBACK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenTokenMissing(): void
    {
        $this->configure(['callback_token' => '']);
        $request = $this->buildRequest('POST', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsResult1ToDelivered(): void
    {
        $request = $this->buildRequest('POST', [], json_encode([
            'transactionId' => 'tx-1',
            'result'        => 1,
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('tx-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
    }

    public function testParseStatusCallbackMapsResult32ToQueued(): void
    {
        $request = $this->buildRequest('POST', [], json_encode([
            'transactionId' => 'tx-2',
            'result'        => 32,
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('queued', $updates[0]->status);
    }

    public function testParseStatusCallbackMarksPermanentForUndeliverable(): void
    {
        $request = $this->buildRequest('POST', [], json_encode([
            'transactionId' => 'tx-3',
            'result'        => 2,
            'statusCode'    => 1,
            'detail'        => 'Number not in service',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('1', $update->errorCode);
        $this->assertStringContainsString('Number not in service', $update->errorMessage);
    }

    public function testParseStatusCallbackDoesNotMarkPermanentForInternalError(): void
    {
        $request = $this->buildRequest('POST', [], json_encode([
            'transactionId' => 'tx-4',
            'result'        => 2,
            'statusCode'    => 5,
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testParseStatusCallbackEmptyForMissingTransactionId(): void
    {
        $request = $this->buildRequest('POST', [], json_encode(['result' => 1]));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackUsesSameTokenCheck(): void
    {
        $this->configure();

        $ok = $this->buildRequest('POST', ['token' => self::CALLBACK_TOKEN]);
        $bad = $this->buildRequest('POST', ['token' => 'nope']);

        $p = $this->createProvider();
        $this->assertTrue($p->validateInboundCallback($ok));
        $this->assertFalse($p->validateInboundCallback($bad));
    }

    public function testParseInboundCallbackProducesIncomingMessage(): void
    {
        $request = $this->buildRequest('POST', [], json_encode([
            'from'          => '+46700000000',
            'fromType'      => 'international',
            'to'            => '71111',
            'toType'        => 'short',
            'message'       => 'Hello back',
            'type'          => 'text',
            'result'        => 1,
            'timestamp'     => '2026-04-30T12:00:00Z',
            'transactionId' => 'mo-1',
        ]));

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+46700000000', $msg->from);
        $this->assertSame('71111', $msg->to);
        $this->assertSame('Hello back', $msg->body);
        $this->assertSame('mo-1', $msg->providerId);
        $this->assertSame('international', $msg->meta['fromType']);
    }

    public function testParseInboundCallbackEmptyForBrokenMo(): void
    {
        $request = $this->buildRequest('POST', [], json_encode([
            'from'   => '+46700000000',
            'result' => 4,
        ]));

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildRequest('POST', [], json_encode(['message' => 'hi']));

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }
}
