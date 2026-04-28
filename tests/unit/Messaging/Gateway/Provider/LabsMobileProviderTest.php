<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\LabsMobileProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class LabsMobileProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'tester@example.com';
    private const API_TOKEN = 'lm-test-token-1234';
    private const TPOA = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new LabsMobileProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'labsmobile' => [
                'shared'   => array_merge([
                    'username'  => self::USERNAME,
                    'api_token' => self::API_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['tpoa' => self::TPOA], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+34600111222', string $body = 'Hello'): Message
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

    private function expectedToken(): string
    {
        return hash_hmac('sha256', 'labsmobile-callback', self::API_TOKEN);
    }

    private function expectedAuth(): string
    {
        return 'Basic ' . base64_encode(self::USERNAME . ':' . self::API_TOKEN);
    }

    // --- Identity & schema ---

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('labsmobile', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsTrueAfterManualVerification(): void
    {
        $this->assertTrue(LabsMobileProvider::TESTED);
    }

    public function testConfigSchemaHasUsernameAndToken(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertArrayNotHasKey('simulated', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertArrayHasKey('tpoa', $schema['channels']['sms']);
        $this->assertFalse((bool) ($schema['channels']['sms']['tpoa']['required'] ?? true));
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendReturnsSentWithSubid(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'code'    => '0',
            'message' => 'Message has been successfully sent.',
            'subid'   => 'subid-abc-001',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('subid-abc-001', $result->providerId);
    }

    public function testSendPostsCorrectPayloadAndAuthHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => '0', 'message' => 'ok', 'subid' => 's1']);

        $this->createProvider()->send($this->createMessage('+34600111222', 'Hi there'));

        $this->assertSame(self::API_BASE() . '/send', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedAuth(), $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('Hi there', $body['message']);
        $this->assertSame([['msisdn' => '+34600111222']], $body['recipient']);
        $this->assertSame(self::TPOA, $body['tpoa']);
        // ackurl is wired through RestRoute::url; in unit-test stub the array
        // form of add_query_arg returns empty, so just assert the key exists.
        $this->assertArrayHasKey('ackurl', $body);
        $this->assertArrayNotHasKey('test', $body);
        $this->assertArrayNotHasKey('ucs2', $body);
        $this->assertArrayNotHasKey('long', $body);
    }

    public function testSendOmitsTpoaWhenNotConfigured(): void
    {
        $this->configure(smsOverrides: ['tpoa' => '']);
        $this->mockHttpPost(['code' => '0', 'subid' => 's2']);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('tpoa', $body);
    }

    public function testSendDoesNotIncludeTestFlag(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => '0', 'subid' => 's-prod']);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('test', $body);
    }

    public function testSendAddsUcs2FlagForUnicodeBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => '0', 'subid' => 's-uc']);

        $this->createProvider()->send($this->createMessage('+34600111222', 'سلام'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(1, $body['ucs2']);
    }

    public function testSendAddsLongFlagForMessagesOver160Chars(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => '0', 'subid' => 's-long']);

        $longMessage = str_repeat('A', 200);
        $this->createProvider()->send($this->createMessage('+34600111222', $longMessage));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(1, $body['long']);
    }

    public function testSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => '5', 'message' => 'Auth failed'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedOnApiError(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'code'    => '15',
            'message' => 'Insufficient credit',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credit', $result->error);
        $this->assertSame(15, $result->meta['labsmobile_code']);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalanceString(): void
    {
        $this->configure();
        $this->mockHttpGet(['code' => 0, 'credits' => '1023.10']);

        $this->assertSame('1023.10', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditUsesBalanceEndpointWithBasicAuth(): void
    {
        $this->configure();
        $captured = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode(['code' => 0, 'credits' => '500']),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->getCredit();

        $this->assertSame('https://api.labsmobile.com/json/balance', $captured['url']);
        $this->assertSame($this->expectedAuth(), $captured['args']['headers']['Authorization']);
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithCredits(): void
    {
        $this->configure();
        $this->mockHttpGet(['code' => 0, 'credits' => '500.00']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500.00', $result->message);
        $this->assertSame('500.00', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['code' => 5], 401);

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
        $request = new \WP_REST_Request('GET', '/wsms/v1/callbacks/labsmobile/status');
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

    public function testParseStatusCallbackMapsDelivered(): void
    {
        $request = new \WP_REST_Request('GET');
        $request->set_param('subid', 'subid-1');
        $request->set_param('msisdn', '+34600111222');
        $request->set_param('status', 'ok');
        $request->set_param('desc', 'DELIVRD');
        $request->set_param('acklevel', 'handset');

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('subid-1', $update->providerId);
        $this->assertSame('delivered', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testParseStatusCallbackMapsAllStatuses(): void
    {
        $cases = [
            'DELIVRD' => ['delivered', false],
            'ACCEPTD' => ['sent', false],
            'ENROUTE' => ['sent', false],
            'REJECTD' => ['failed', true],
            'UNDELIV' => ['failed', true],
            'EXPIRED' => ['failed', true],
        ];

        $p = $this->createProvider();
        foreach ($cases as $desc => [$expected, $permanent]) {
            $request = new \WP_REST_Request('GET');
            $request->set_param('subid', 'subid-' . $desc);
            $request->set_param('desc', $desc);
            $request->set_param('status', $expected === 'failed' ? 'ko' : 'ok');

            $update = $p->parseStatusCallback($request)[0];
            $this->assertSame($expected, $update->status, "wrong status for desc={$desc}");
            $this->assertSame($permanent, $update->permanent, "wrong permanent for desc={$desc}");
        }
    }

    public function testParseStatusCallbackFallsBackToStatusFieldWhenDescMissing(): void
    {
        $request = new \WP_REST_Request('GET');
        $request->set_param('subid', 'subid-fallback');
        $request->set_param('status', 'ok');

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('delivered', $update->status);
    }

    public function testParseStatusCallbackReturnsEmptyForMissingSubid(): void
    {
        $request = new \WP_REST_Request('GET');
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackIncludesErrorCodeForFailures(): void
    {
        $request = new \WP_REST_Request('GET');
        $request->set_param('subid', 'subid-fail');
        $request->set_param('desc', 'REJECTD');
        $request->set_param('status', 'ko');

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('REJECTD', $update->errorCode);
        $this->assertStringContainsString('REJECTD', $update->errorMessage);
    }

    private static function API_BASE(): string
    {
        return 'https://api.labsmobile.com/json';
    }
}
