<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsesProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsesProviderTest extends AbstractProviderTestCase
{
    private const BASE = 'https://gateway.example.test:42161/';
    private const USERNAME = 'wsms-tester';
    private const PASSWORD = 'p4ssw0rd-test';
    private const SENDER = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new SmsesProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smses' => [
                'shared'   => array_merge([
                    'api_base_url' => self::BASE,
                    'username'     => self::USERNAME,
                    'password'     => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '34600111222', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 202): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function expectedToken(): string
    {
        return hash_hmac('sha256', 'smses-callback', self::PASSWORD);
    }

    // --- Identity & schema ---

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smses', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmsesProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_base_url', $schema['shared']);
        $this->assertSame('string', $schema['shared']['api_base_url']['type']);
        $this->assertNotEmpty($schema['shared']['api_base_url']['default']);

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);

        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);

        $this->assertArrayHasKey('sender', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender']['required']);
    }

    public function testFeaturesEnableFlashAndDeliveryReceipt(): void
    {
        $features = $this->createProvider()->getFeatures();

        $this->assertTrue($features['delivery_receipt']);
        $this->assertTrue($features['flash_sms']);
        $this->assertFalse($features['incoming']);
        $this->assertFalse($features['mms']);
        $this->assertFalse($features['test_connection']);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendReturnsSentWithMsgIdOn202(): void
    {
        $this->configure();
        $this->mockHttpPost(['msgId' => 'msg-uuid-001', 'numParts' => 1], 202);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('msg-uuid-001', $result->providerId);
    }

    public function testSendPostsToBulkSendsmsAndIncludesNestedAuth(): void
    {
        $this->configure();
        $this->mockHttpPost(['msgId' => 'msg-1', 'numParts' => 1]);

        $this->createProvider()->send($this->createMessage('34600111222', 'Hi there'));

        $this->assertSame(
            rtrim(self::BASE, '/') . '/bulk/sendsms',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);
        $this->assertFalse($args['sslverify']);

        $body = json_decode($args['body'], true);
        $this->assertSame('text', $body['type']);
        $this->assertSame(['username' => self::USERNAME, 'password' => self::PASSWORD], $body['auth']);
        $this->assertSame(self::SENDER, $body['sender']);
        $this->assertSame('34600111222', $body['receiver']);
        $this->assertSame('Hi there', $body['text']);
        $this->assertSame('gsm', $body['dcs']);
        $this->assertSame(19, $body['dlrMask']);
        $this->assertArrayHasKey('dlrUrl', $body);
        $this->assertArrayNotHasKey('flash', $body);
    }

    public function testSendDetectsUnicodeBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['msgId' => 'msg-uc', 'numParts' => 1]);

        $this->createProvider()->send($this->createMessage('34600111222', 'Hola ñoño 🚀'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('ucs', $body['dcs']);
    }

    public function testSendIncludesFlashWhenMetaFlagSet(): void
    {
        $this->configure();
        $this->mockHttpPost(['msgId' => 'msg-fl', 'numParts' => 1]);

        $this->createProvider()->send($this->createMessage('34600111222', 'Flash!', ['flash' => true]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertTrue($body['flash']);
    }

    public function testSendTrimsTrailingSlashFromBaseUrl(): void
    {
        $this->configure(['api_base_url' => 'https://gateway.example.test:42161///']);
        $this->mockHttpPost(['msgId' => 'msg-2', 'numParts' => 1]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://gateway.example.test:42161/bulk/sendsms',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testSendUsesDefaultBaseUrlWhenConfigEmpty(): void
    {
        $this->configure(['api_base_url' => '']);
        $this->mockHttpPost(['msgId' => 'msg-d', 'numParts' => 1]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://194.0.137.110:42161/bulk/sendsms',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsWhenSenderMissing(): void
    {
        $this->configure(smsOverrides: ['sender' => '']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    public function testSendSurfacesProviderErrorMessage(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => ['code' => 113, 'message' => 'Insufficient credit']], 420);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credit', $result->error);
    }

    public function testSendFailsOnUnexpectedHttpCode(): void
    {
        $this->configure();
        $this->mockHttpPost(['msgId' => 'should-not-matter'], 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smses/status');
        $request->set_param('token', $this->expectedToken());

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', 'totally-wrong');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', 'anything');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsAllEvents(): void
    {
        $cases = [
            'DELIVERED'    => ['delivered', false],
            'BUFFERED'     => ['queued', false],
            'SENT_TO_SMSC' => ['sent', false],
            'UNDELIVERED'  => ['failed', false],
            'REJECTED'     => ['failed', true],
        ];

        $p = $this->createProvider();
        foreach ($cases as $event => [$expectedStatus, $expectedPermanent]) {
            $request = new \WP_REST_Request('POST');
            $request->set_body(json_encode([
                'msgId'   => 'msg-' . $event,
                'event'   => $event,
                'partNum' => 0,
                'numParts'=> 1,
            ]));

            $update = $p->parseStatusCallback($request)[0];
            $this->assertSame($expectedStatus, $update->status, "wrong status for event={$event}");
            $this->assertSame($expectedPermanent, $update->permanent, "wrong permanent for event={$event}");
            $this->assertSame('msg-' . $event, $update->providerId);
        }
    }

    public function testParseStatusCallbackPropagatesErrorFields(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode([
            'msgId'        => 'msg-fail',
            'event'        => 'REJECTED',
            'errorCode'    => 7,
            'errorMessage' => 'Rejected by SMSC',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('7', $update->errorCode);
        $this->assertSame('Rejected by SMSC', $update->errorMessage);
        $this->assertTrue($update->permanent);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode([]));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Credit ---

    public function testGetCreditAlwaysReturnsNull(): void
    {
        $this->configure();
        $this->assertNull($this->createProvider()->getCredit());
    }
}
