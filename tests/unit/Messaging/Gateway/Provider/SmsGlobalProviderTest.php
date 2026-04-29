<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsGlobalProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsGlobalProviderTest extends AbstractProviderTestCase
{
    private const API_KEY    = 'sg-key-12345';
    private const API_SECRET = 'sg-secret-abcdef';
    private const SMS_FROM   = 'WSMS';
    private const MMS_FROM   = '+61400000000';

    protected function createProvider(): AbstractProvider
    {
        return new SmsGlobalProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get'],
        );
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = [], array $mmsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsglobal' => [
                'shared'   => array_merge([
                    'api_key'    => self::API_KEY,
                    'api_secret' => self::API_SECRET,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from_number' => self::SMS_FROM], $smsOverrides),
                    'mms' => array_merge(['from_number' => self::MMS_FROM], $mmsOverrides),
                ],
            ],
        ];
    }

    private function mockHttpPost(?array $responseBody, int $statusCode = 200, ?string $rawBody = null): void
    {
        $body = $rawBody !== null ? $rawBody : ($responseBody !== null ? json_encode($responseBody) : '');
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
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

    private function expectedToken(): string
    {
        return hash_hmac('sha256', 'smsglobal-callback', self::API_SECRET);
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smsglobal', $p->getId());
        $this->assertSame(['sms', 'mms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmsGlobalProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertArrayHasKey('api_secret', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertSame('secret', $schema['shared']['api_secret']['type']);
        $this->assertTrue((bool) $schema['shared']['api_key']['required']);
        $this->assertTrue((bool) $schema['shared']['api_secret']['required']);

        $this->assertArrayHasKey('from_number', $schema['channels']['sms']);
        $this->assertArrayHasKey('from_number', $schema['channels']['mms']);
        $this->assertTrue((bool) $schema['channels']['sms']['from_number']['required']);
        $this->assertTrue((bool) $schema['channels']['mms']['from_number']['required']);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredForChannelChecksChannelFields(): void
    {
        $this->configure();
        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('sms'));
        $this->assertTrue($p->isConfiguredForChannel('mms'));
        $this->assertFalse($p->isConfiguredForChannel('whatsapp'));
    }

    // --- Send SMS ---

    public function testSendSmsReturnsSentWithMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost(['messages' => [['id' => 'msg-abc-001']]]);

        $result = $this->createProvider()->send(new Message('sms', '+61433000000', 'hello'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('msg-abc-001', $result->providerId);
    }

    public function testSendSmsHitsRestEndpointWithMacHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['messages' => [['id' => 'm1']]]);

        $this->createProvider()->send(new Message('sms', '+61433000000', 'hi'));

        $this->assertSame('https://api.smsglobal.com/v2/sms', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);
        $this->assertMatchesRegularExpression(
            '/^MAC id="' . preg_quote(self::API_KEY, '/') . '", ts="\d+", nonce="\d+", mac="[A-Za-z0-9+\/=]+"$/',
            $args['headers']['Authorization'],
        );

        $body = json_decode($args['body'], true);
        $this->assertSame(self::SMS_FROM, $body['origin']);
        $this->assertSame(['+61433000000'], $body['destinations']);
        $this->assertSame('hi', $body['message']);
        $this->assertArrayHasKey('notifyUrl', $body);
        $this->assertArrayHasKey('incomingUrl', $body);
    }

    public function testSendSmsReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 401, 'message' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send(new Message('sms', '+61433000000', 'x'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendSmsReturnsFailedOnInnerErrorCode(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 400, 'message' => 'Invalid origin']);

        $result = $this->createProvider()->send(new Message('sms', '+61433000000', 'x'));

        $this->assertFalse($result->success);
        $this->assertSame('Invalid origin', $result->error);
        $this->assertSame('400', $result->meta['smsglobal_code']);
    }

    public function testSendSmsReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send(new Message('sms', '+61433000000', 'x'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Send MMS ---

    public function testSendMmsHitsLegacyEndpointWithBasicAuth(): void
    {
        $this->configure();
        $this->mockHttpGet([]);
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => 'BINARY-IMAGE-BYTES',
            'response' => ['code' => 200, 'headers' => ['content-type' => 'image/jpeg']],
        ];
        $this->mockHttpPost(null, 200, rawBody: 'OK: 998877');

        $message = new Message(
            channel: 'mms',
            recipient: '+61433000000',
            body: 'see image',
            meta: ['media_urls' => ['https://example.com/cat.jpg']],
        );

        $result = $this->createProvider()->send($message);

        $this->assertTrue($result->success, 'MMS send failed: ' . ($result->error ?? ''));
        $this->assertSame('998877', $result->providerId);
        $this->assertSame('https://api.smsglobal.com/mms/sendmms.php', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $expectedAuth = 'Basic ' . base64_encode(self::API_KEY . ':' . self::API_SECRET);
        $this->assertSame($expectedAuth, $args['headers']['Authorization']);
        $this->assertStringStartsWith('multipart/form-data; boundary=', $args['headers']['Content-Type']);
        $this->assertStringContainsString('+61433000000', $args['body']);
        $this->assertStringContainsString(self::MMS_FROM, $args['body']);
        $this->assertStringContainsString('BINARY-IMAGE-BYTES', $args['body']);
    }

    public function testSendMmsRequiresMediaUrls(): void
    {
        $this->configure();

        $result = $this->createProvider()->send(new Message('mms', '+61433000000', 'just text'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('media URL', $result->error);
    }

    public function testSendMmsRejectsOversizedPayload(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => str_repeat('X', 400 * 1024),
            'response' => ['code' => 200, 'headers' => ['content-type' => 'image/jpeg']],
        ];

        $message = new Message(
            channel: 'mms',
            recipient: '+61433000000',
            body: 'big',
            meta: ['media_urls' => ['https://example.com/big.jpg']],
        );

        $result = $this->createProvider()->send($message);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('300 KB', $result->error);
    }

    // --- Channel routing ---

    public function testSendRejectsUnsupportedChannel(): void
    {
        $this->configure();

        $result = $this->createProvider()->send(new Message('whatsapp', '+61433000000', 'x'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unsupported', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsFormattedBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => '24.50', 'currency' => 'AUD']);

        $this->assertSame('24.50 AUD', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditUsesMacSignedRequest(): void
    {
        $this->configure();
        $captured = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode(['balance' => '10', 'currency' => 'AUD']),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->getCredit();

        $this->assertSame('https://api.smsglobal.com/v2/user/credit-balance', $captured['url']);
        $this->assertMatchesRegularExpression(
            '/^MAC id="' . preg_quote(self::API_KEY, '/') . '", ts="\d+", nonce="\d+", mac="[A-Za-z0-9+\/=]+"$/',
            $captured['args']['headers']['Authorization'],
        );
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => '25.00', 'currency' => 'AUD']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('25.00 AUD', $result->message);
        $this->assertSame('25.00 AUD', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauthorized'], 401);

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

    public function testStatusCallbackUrlIncludesDerivedToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();
        $this->assertStringContainsString($this->expectedToken(), $url);
    }

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('GET', '/wsms/v1/callbacks/smsglobal/status');
        $request->set_param('token', $this->expectedToken());

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('GET');
        $request->set_param('token', 'totally-wrong');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $request = new \WP_REST_Request('GET');
        $request->set_param('token', 'anything');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsDlrStatuses(): void
    {
        $cases = [
            'DELIVRD' => ['delivered', false],
            'EXPIRED' => ['failed',    false],
            'UNDELIV' => ['failed',    true],
            'ENROUTE' => ['sent',      false], // fallback for any other code
        ];

        $p = $this->createProvider();
        foreach ($cases as $dlrStatus => [$expectedStatus, $expectedPermanent]) {
            $request = new \WP_REST_Request('POST');
            $request->set_param('msgid', 'm-' . $dlrStatus);
            $request->set_param('dlrstatus', $dlrStatus);

            $update = $p->parseStatusCallback($request)[0];
            $this->assertSame($expectedStatus, $update->status, "wrong status for {$dlrStatus}");
            $this->assertSame($expectedPermanent, $update->permanent, "wrong permanent for {$dlrStatus}");
        }
    }

    public function testParseStatusCallbackIncludesErrorCodeForFailures(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('msgid', 'm-fail');
        $request->set_param('dlrstatus', 'UNDELIV');
        $request->set_param('dlr_err', '8');

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('UNDELIV', $update->errorCode === '8' ? 'UNDELIV' : 'UNDELIV'); // sanity
        $this->assertSame('8', $update->errorCode);
        $this->assertStringContainsString('UNDELIV', $update->errorMessage);
        $this->assertStringContainsString('8', $update->errorMessage);
    }

    public function testParseStatusCallbackReturnsEmptyForMissingMsgid(): void
    {
        $request = new \WP_REST_Request('POST');
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testInboundCallbackUrlIncludesDerivedToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getInboundCallbackUrl();
        $this->assertStringContainsString($this->expectedToken(), $url);
    }

    public function testValidateInboundCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('GET');
        $request->set_param('token', $this->expectedToken());

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundCallbackBuildsInboundMessage(): void
    {
        $request = new \WP_REST_Request('GET');
        $request->set_param('msgid', '471051701');
        $request->set_param('from', '61433111222');
        $request->set_param('to', '61499057767');
        $request->set_param('msg', 'hello back');
        $request->set_param('date', '2026-04-29 16:53:19');

        $inbound = $this->createProvider()->parseInboundCallback($request)[0];

        $this->assertSame('61433111222', $inbound->from);
        $this->assertSame('61499057767', $inbound->to);
        $this->assertSame('hello back', $inbound->body);
        $this->assertSame('471051701', $inbound->providerId);
        $this->assertSame('2026-04-29 16:53:19', $inbound->meta['date']);
    }

    public function testParseInboundCallbackReturnsEmptyWhenFromMissing(): void
    {
        $request = new \WP_REST_Request('GET');
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }
}
