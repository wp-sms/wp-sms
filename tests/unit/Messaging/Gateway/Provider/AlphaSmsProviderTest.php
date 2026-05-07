<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\AlphaSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class AlphaSmsProviderTest extends AbstractProviderTestCase
{
    private const API_URL = 'https://alphasms.ua/api/json.php';
    private const API_KEY = 'alpha-test-key-1234';
    private const SMS_SENDER = 'WSMS';
    private const VIBER_SENDER = 'WSMS-Viber';
    private const RCS_SENDER = 'WSMS-RCS';

    protected function createProvider(): AbstractProvider
    {
        return new AlphaSmsProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'alphasms' => [
                'shared'   => array_merge(['api_key' => self::API_KEY], $sharedOverrides),
                'channels' => array_merge([
                    'sms'   => ['sender_id' => self::SMS_SENDER],
                    'viber' => ['sender_id' => self::VIBER_SENDER],
                    'rcs'   => ['sender_id' => self::RCS_SENDER],
                ], $channelOverrides),
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

    private function expectedToken(): string
    {
        return hash_hmac('sha256', 'alphasms-callback', self::API_KEY);
    }

    private function lastRequestBody(): array
    {
        return json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
    }

    private function lastFirstRecord(): array
    {
        return $this->lastRequestBody()['data'][0];
    }

    // --- Identity & schema ---

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('alphasms', $p->getId());
        $this->assertSame(['sms', 'viber', 'rcs'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManualVerification(): void
    {
        $this->assertFalse(AlphaSmsProvider::TESTED);
    }

    public function testConfigSchemaHasApiKey(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue((bool) $schema['shared']['api_key']['required']);
    }

    public function testConfigSchemaSmsSenderIdOptional(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertFalse((bool) ($schema['channels']['sms']['sender_id']['required'] ?? true));
    }

    public function testConfigSchemaViberSenderIdRequired(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $this->assertArrayHasKey('sender_id', $schema['channels']['viber']);
        $this->assertTrue((bool) $schema['channels']['viber']['sender_id']['required']);
    }

    public function testConfigSchemaRcsSenderIdRequired(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $this->assertArrayHasKey('sender_id', $schema['channels']['rcs']);
        $this->assertTrue((bool) $schema['channels']['rcs']['sender_id']['required']);
    }

    // --- SMS send ---

    public function testSmsPostsCorrectPayload(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'm-1']]]]);

        $this->createProvider()->send(new Message('sms', '+380501112233', 'Hi there'));

        $this->assertSame(self::API_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);
        $this->assertSame('application/json', $args['headers']['Accept']);

        $body = $this->lastRequestBody();
        $this->assertSame(self::API_KEY, $body['auth']);
        $this->assertCount(1, $body['data']);

        $record = $body['data'][0];
        $this->assertSame('sms', $record['type']);
        $this->assertSame('Hi there', $record['sms_message']);
        $this->assertSame(self::SMS_SENDER, $record['sms_signature']);
        $this->assertArrayHasKey('id', $record);
        $this->assertArrayHasKey('hook', $record);
    }

    public function testSmsStripsPlusFromPhone(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'm-2']]]]);

        $this->createProvider()->send(new Message('sms', '+380 50 111-22-33', 'hi'));

        $this->assertSame('380501112233', $this->lastFirstRecord()['phone']);
    }

    public function testSmsOmitsSignatureWhenSenderEmpty(): void
    {
        $this->configure(channelOverrides: ['sms' => ['sender_id' => '']]);
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'm-3']]]]);

        $this->createProvider()->send(new Message('sms', '+380501112233', 'hi'));

        $this->assertArrayNotHasKey('sms_signature', $this->lastFirstRecord());
    }

    public function testSmsReturnsSentWithMsgId(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'msg-abc-001']]]]);

        $result = $this->createProvider()->send(new Message('sms', '+380501112233', 'hi'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('msg-abc-001', $result->providerId);
    }

    public function testSmsReturnsFailedOnRootError(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => false, 'error' => 'Insufficient funds']);

        $result = $this->createProvider()->send(new Message('sms', '+380501112233', 'hi'));

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient funds', $result->error);
    }

    public function testSmsReturnsFailedOnPerRecordError(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['error' => 'Invalid phone format']]]);

        $result = $this->createProvider()->send(new Message('sms', '+380501112233', 'hi'));

        $this->assertFalse($result->success);
        $this->assertSame('Invalid phone format', $result->error);
    }

    public function testSmsReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send(new Message('sms', '+380501112233', 'hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send(new Message('sms', '+380501112233', 'hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Viber send ---

    public function testViberRequiresSignature(): void
    {
        $this->configure(channelOverrides: ['viber' => ['sender_id' => '']]);

        $result = $this->createProvider()->send(new Message('viber', '+380501112233', 'hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Viber', $result->error);
    }

    public function testViberDefaultsToTextType(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'v-1']]]]);

        $this->createProvider()->send(new Message('viber', '+380501112233', 'hello'));

        $record = $this->lastFirstRecord();
        $this->assertSame('viber', $record['type']);
        $this->assertSame('text', $record['viber_type']);
        $this->assertSame(self::VIBER_SENDER, $record['viber_signature']);
        $this->assertSame('hello', $record['viber_message']);
        $this->assertSame(172800, $record['viber_lifetime']);
        $this->assertArrayNotHasKey('viber_image', $record);
        $this->assertArrayNotHasKey('viber_link', $record);
        $this->assertArrayNotHasKey('viber_button', $record);
    }

    public function testViberPicksTextPlusLinkFromMeta(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'v-2']]]]);

        $message = new Message('viber', '+380501112233', 'with link', null, [
            'link_url'    => 'https://example.com',
            'button_text' => 'Open',
        ]);
        $this->createProvider()->send($message);

        $record = $this->lastFirstRecord();
        $this->assertSame('text+link', $record['viber_type']);
        $this->assertSame('https://example.com', $record['viber_link']);
        $this->assertSame('Open', $record['viber_button']);
        $this->assertArrayNotHasKey('viber_image', $record);
    }

    public function testViberPicksImageFromMeta(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'v-3']]]]);

        $message = new Message('viber', '+380501112233', 'caption', null, [
            'media_url' => 'https://cdn.example.com/img.jpg',
        ]);
        $this->createProvider()->send($message);

        $record = $this->lastFirstRecord();
        $this->assertSame('image', $record['viber_type']);
        $this->assertSame('https://cdn.example.com/img.jpg', $record['viber_image']);
    }

    public function testViberPicksTextImageLinkFromMeta(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'v-4']]]]);

        $message = new Message('viber', '+380501112233', 'rich', null, [
            'media_url'   => 'https://cdn.example.com/img.jpg',
            'link_url'    => 'https://example.com',
            'button_text' => 'Buy',
        ]);
        $this->createProvider()->send($message);

        $record = $this->lastFirstRecord();
        $this->assertSame('text+image+link', $record['viber_type']);
        $this->assertSame('https://cdn.example.com/img.jpg', $record['viber_image']);
        $this->assertSame('https://example.com', $record['viber_link']);
        $this->assertSame('Buy', $record['viber_button']);
    }

    public function testViberSetsLifetime(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'v-5']]]]);

        $message = new Message('viber', '+380501112233', 'hi', null, ['viber_lifetime' => 3600]);
        $this->createProvider()->send($message);

        $this->assertSame(3600, $this->lastFirstRecord()['viber_lifetime']);
    }

    public function testViberReturnsSentWithMsgId(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'viber-99']]]]);

        $result = $this->createProvider()->send(new Message('viber', '+380501112233', 'hi'));

        $this->assertTrue($result->success);
        $this->assertSame('viber-99', $result->providerId);
    }

    // --- RCS send ---

    public function testRcsRequiresSignature(): void
    {
        $this->configure(channelOverrides: ['rcs' => ['sender_id' => '']]);

        $result = $this->createProvider()->send(new Message('rcs', '+380501112233', 'hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('RCS', $result->error);
    }

    public function testRcsBuildsTextRecord(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'r-1']]]]);

        $this->createProvider()->send(new Message('rcs', '+380501112233', 'hello rcs'));

        $record = $this->lastFirstRecord();
        $this->assertSame('rcs', $record['type']);
        $this->assertSame('hello rcs', $record['rcs_message']);
        $this->assertSame(self::RCS_SENDER, $record['rcs_signature']);
        $this->assertArrayNotHasKey('rcs_image', $record);
        $this->assertArrayNotHasKey('rcs_link', $record);
        $this->assertArrayNotHasKey('rcs_button', $record);
    }

    public function testRcsAddsImageLinkButtonFromMeta(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'r-2']]]]);

        $message = new Message('rcs', '+380501112233', 'rich rcs', null, [
            'media_url'   => 'https://cdn.example.com/r.jpg',
            'link_url'    => 'https://example.com',
            'button_text' => 'Tap',
        ]);
        $this->createProvider()->send($message);

        $record = $this->lastFirstRecord();
        $this->assertSame('https://cdn.example.com/r.jpg', $record['rcs_image']);
        $this->assertSame('https://example.com', $record['rcs_link']);
        $this->assertSame('Tap', $record['rcs_button']);
    }

    public function testRcsReturnsSentWithMsgId(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['msg_id' => 'rcs-77']]]]);

        $result = $this->createProvider()->send(new Message('rcs', '+380501112233', 'hi'));

        $this->assertTrue($result->success);
        $this->assertSame('rcs-77', $result->providerId);
    }

    // --- Credit / test connection ---

    public function testGetCreditReturnsAmount(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['amount' => 1234.56]]]]);

        $this->assertSame('1234.56', $this->createProvider()->getCredit());
    }

    public function testGetCreditPostsBalanceRecord(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['amount' => 0]]]]);

        $this->createProvider()->getCredit();

        $body = $this->lastRequestBody();
        $this->assertSame(self::API_KEY, $body['auth']);
        $this->assertSame([['type' => 'balance']], $body['data']);
        $this->assertSame(self::API_URL, $GLOBALS['_test_wp_remote_post_last_url']);
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => [['data' => ['amount' => '500.00']]]]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500.00', $result->message);
        $this->assertStringContainsString('UAH', $result->message);
        $this->assertSame('500.00', $result->details['balance']);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'Unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionReturnsErrorOnSuccessFalse(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => false, 'error' => 'Account suspended']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('suspended', $result->message);
    }

    // --- Status callback ---

    public function testCallbackUrlContainsToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();

        $this->assertStringContainsString('callbacks/alphasms/status', $url);
        $this->assertStringContainsString('token=' . $this->expectedToken(), $url);
    }

    public function testValidateCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/alphasms/status');
        $request->set_param('token', $this->expectedToken());

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', 'wrong-token');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateCallbackRejectsWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', 'any');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateCallbackAcceptsWhenSignatureHeaderAbsent(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', $this->expectedToken());
        $request->set_body('{"msg_id":"abc","status":"DELIVERED"}');
        // No X-Signature header

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateCallbackVerifiesXSignatureWhenPresent(): void
    {
        $this->configure();
        $body = '{"msg_id":"abc","status":"DELIVERED"}';
        $expected = hash('sha256', $body . self::API_KEY);

        $request = new \WP_REST_Request('POST');
        $request->set_param('token', $this->expectedToken());
        $request->set_body($body);
        $request->set_header('X-Signature', $expected);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateCallbackRejectsBadXSignature(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', $this->expectedToken());
        $request->set_body('{"msg_id":"abc","status":"DELIVERED"}');
        $request->set_header('X-Signature', 'deadbeef');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    // --- DLR parsing ---

    public function testParseMapsDeliveredVariants(): void
    {
        foreach (['DELIVERED', 'PARTIALLY DELIVERED', 'READ', 'REPLIED'] as $status) {
            $request = new \WP_REST_Request('POST');
            $request->set_body(json_encode(['msg_id' => 'm-' . $status, 'status' => $status]));

            $update = $this->createProvider()->parseStatusCallback($request)[0];
            $this->assertSame('delivered', $update->status, "Wrong mapping for {$status}");
            $this->assertFalse($update->permanent, "Delivered statuses must not be permanent failures");
        }
    }

    public function testParseMapsSentVariants(): void
    {
        foreach (['ACCEPTED', 'QUEUED', 'ROUTING'] as $status) {
            $request = new \WP_REST_Request('POST');
            $request->set_body(json_encode(['msg_id' => 'm-' . $status, 'status' => $status]));

            $update = $this->createProvider()->parseStatusCallback($request)[0];
            $this->assertSame('sent', $update->status, "Wrong mapping for {$status}");
            $this->assertFalse($update->permanent);
        }
    }

    /** @dataProvider permanentFailureProvider */
    public function testParseMapsAllPermanentFailures(string $status): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode(['msg_id' => 'm-perm', 'status' => $status]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent, "{$status} should be a permanent failure");
    }

    public static function permanentFailureProvider(): array
    {
        return [
            ['REJECTED'],
            ['EXPIRED'],
            ['INVALID DESTINATION ADDRESS'],
            ['INVALID SOURCE ADDRESS'],
            ['NO ROUTE'],
            ['DELETED'],
        ];
    }

    /** @dataProvider transientFailureProvider */
    public function testParseMapsTransientFailures(string $status): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode(['msg_id' => 'm-tran', 'status' => $status]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertFalse($update->permanent, "{$status} should be transient, not permanent");
    }

    public static function transientFailureProvider(): array
    {
        return [
            ['FILTERED'],
            ['SIM FULL'],
            ['UNDELIVERABLE'],
            ['UNKNOWN'],
        ];
    }

    public function testParseReturnsEmptyForMissingMsgId(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode(['status' => 'DELIVERED']));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseSetsErrorCodeAndMessageOnFailure(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode(['msg_id' => 'm-fail', 'status' => 'REJECTED']));

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('REJECTED', $update->errorCode);
        $this->assertStringContainsString('REJECTED', $update->errorMessage);
    }
}
