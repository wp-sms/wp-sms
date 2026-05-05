<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\ProSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class ProSmsProviderTest extends AbstractProviderTestCase
{
    private const API_KEY        = 'prosms-api-key-12345';
    private const CALLBACK_TOKEN = 'callback-secret-7890';
    private const SENDER         = 'WSMS';
    private const SEND_URL       = 'https://api.prosms.se/v1/sms/send';
    private const CREDIT_URL     = 'https://api.prosms.se/user/getcreditvalue';
    private const SENDERS_URL    = 'https://api.prosms.se/v1/sendername/list';

    protected function createProvider(): AbstractProvider
    {
        return new ProSmsProvider();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get'],
        );
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'prosms' => [
                'shared' => array_merge([
                    'api_key'        => self::API_KEY,
                    'callback_token' => self::CALLBACK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_name' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+46701234567', string $body = 'Hej'): Message
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

    private function buildRequest(string $method, array $params): \WP_REST_Request
    {
        $request = new \WP_REST_Request($method, '/x');
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        return $request;
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(ProSmsProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('prosms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testGetConfigSchemaHasRequiredFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertFalse(!empty($schema['shared']['callback_token']['required']));

        $this->assertArrayHasKey('sender_name', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender_name']['required']);
        $this->assertTrue($schema['channels']['sms']['sender_name']['dynamic']);
    }

    // --- Send ---

    public function testDoSendSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 5000, 'message' => 'OK']);

        $result = $this->createProvider()->send($this->createMessage('+46701234567', 'Hej Sverige'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertNotEmpty($result->providerId);
        $this->assertStringStartsWith('wsms-', $result->providerId);
        $this->assertLessThanOrEqual(50, strlen($result->providerId));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::API_KEY, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);
        $this->assertSame('application/json', $args['headers']['Accept']);

        $body = json_decode($args['body'], true);
        $this->assertSame('46701234567', $body['receiver']);
        $this->assertSame(self::SENDER, $body['senderName']);
        $this->assertSame('Hej Sverige', $body['message']);
        $this->assertSame('gsm', $body['format']);
        $this->assertSame('utf8', $body['encoding']);
        $this->assertSame($result->providerId, $body['userReference']);
        $this->assertStringContainsString('messageid=' . rawurlencode($result->providerId), $body['dlrUrl']);
        $this->assertStringContainsString('%26token=' . rawurlencode(self::CALLBACK_TOKEN), $body['dlrUrl']);
    }

    public function testDoSendNormalizesNumericReceiver(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 5000]);

        $this->createProvider()->send($this->createMessage('+46-70 123 4567'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('46701234567', $body['receiver']);
    }

    public function testDoSendUsesUnicodeFormatForNonAscii(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 5000]);

        $this->createProvider()->send($this->createMessage('+46701234567', 'Hej 你好'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('unicode', $body['format']);
    }

    public function testDoSendOmitsDlrUrlWhenCallbackTokenMissing(): void
    {
        $this->configure(['callback_token' => '']);
        $this->mockHttpPost(['code' => 5000]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('dlrUrl', $body);
    }

    public function testDoSendIncludesScheduledFromMessageMeta(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 5000]);

        $message = new Message('sms', '+46701234567', 'Hej', null, ['scheduled_at' => '2026-09-15T17:00:00+00:00']);
        $this->createProvider()->send($message);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('2026-09-15T17:00:00+00:00', $body['scheduled']);
    }

    public function testDoSendPartialRejectionReturnsFailed(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'code'     => 1059,
            'message'  => 'Some recipients rejected',
            'rejected' => [['receiver' => '46701234567', 'reason' => 'invalid']],
        ], 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('1059', $result->meta['prosms_code'] ?? null);
    }

    public function testDoSendNonSuccessHttpReturnsFailedWithMeta(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'code'    => 1011,
            'message' => 'Sender name invalid',
        ], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Sender name invalid', $result->error);
        $this->assertSame('1011', $result->meta['prosms_code'] ?? null);
        $this->assertSame('400', $result->meta['prosms_http'] ?? null);
    }

    public function testDoSendUnauthorizedReturnsCredentialError(): void
    {
        $this->configure();
        $this->mockHttpPost([], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testDoSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Credit ---

    public function testGetCreditFetchesValue(): void
    {
        $this->configure();
        $this->mockHttpGet(['credit' => '1500.25']);

        $this->assertSame('1500.25', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpGet([], 500);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = function ($url) {
            $this->assertSame(self::CREDIT_URL, $url);
            return [
                'body'     => json_encode(['credit' => '5000']),
                'response' => ['code' => 200],
            ];
        };

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('ProSMS', $result->message);
        $this->assertStringContainsString('5000', $result->message);
    }

    public function testTestConnectionUnauthorized(): void
    {
        $this->configure();
        $this->mockHttpGet([], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback ---

    public function testStatusCallbackUrlCarriesProviderId(): void
    {
        $this->assertStringContainsString(
            'callbacks/prosms/status',
            $this->createProvider()->getStatusCallbackUrl(),
        );
    }

    public function testStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', [
            'token'     => self::CALLBACK_TOKEN,
            'messageid' => 'wsms-1',
            'status'    => 'delivered',
        ]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsWhenTokenNotConfigured(): void
    {
        $this->configure(['callback_token' => '']);
        $request = $this->buildRequest('GET', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('GET', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackMapsDeliveredStatus(): void
    {
        $request = $this->buildRequest('GET', [
            'messageid' => 'wsms-ok',
            'status'    => 'delivered',
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(1, $updates);
        $this->assertSame('wsms-ok', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testParseStatusCallbackTerminalFailureSetsPermanent(): void
    {
        $request = $this->buildRequest('GET', [
            'messageid' => 'wsms-bad',
            'status'    => 'failed',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('failed', $update->errorCode);
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsBuildsSenderListFromApi(): void
    {
        $config = [
            'shared'   => ['api_key' => self::API_KEY],
            'channels' => ['sms' => []],
        ];
        $GLOBALS['_test_wp_remote_get'] = function ($url, $args) {
            $this->assertSame(self::SENDERS_URL, $url);
            $this->assertSame('Bearer ' . self::API_KEY, $args['headers']['Authorization']);
            return [
                'body'     => json_encode([
                    'senderNames' => [
                        ['senderName' => 'BrandA'],
                        ['senderName' => 'BrandB'],
                    ],
                ]),
                'response' => ['code' => 200],
            ];
        };

        $options = $this->createProvider()->getConfigOptions('sender_name', 'sms', $config);

        $this->assertSame([
            ['value' => 'BrandA', 'label' => 'BrandA'],
            ['value' => 'BrandB', 'label' => 'BrandB'],
        ], $options);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('something_else', 'sms', []));
    }

    public function testGetConfigOptionsReturnsEmptyWithoutApiKey(): void
    {
        $config = ['shared' => [], 'channels' => ['sms' => []]];
        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_name', 'sms', $config));
    }

    public function testGetConfigOptionsReturnsEmptyOnApiError(): void
    {
        $config = ['shared' => ['api_key' => self::API_KEY], 'channels' => ['sms' => []]];
        $this->mockHttpGet(['error' => 'unauthorized'], 401);

        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_name', 'sms', $config));
    }
}
