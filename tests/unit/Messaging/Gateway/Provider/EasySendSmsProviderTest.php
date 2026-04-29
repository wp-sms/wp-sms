<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\EasySendSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class EasySendSmsProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'esms-test-key-1234';
    private const FROM = 'WSMS';

    private const SEND_URL = 'https://restapi.easysendsms.app/v1/rest/sms/send';
    private const BALANCE_URL = 'https://restapi.easysendsms.app/v1/rest/sms/balance';

    protected function createProvider(): AbstractProvider
    {
        return new EasySendSmsProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'easysendsms' => [
                'shared'   => array_merge([
                    'api_key' => self::API_KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
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
        return hash_hmac('sha256', 'easysendsms-callback', self::API_KEY);
    }

    // --- Identity & schema ---

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('easysendsms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(EasySendSmsProvider::TESTED);
    }

    public function testConfigSchemaHasApiKeyAndFrom(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue((bool) ($schema['shared']['api_key']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredFalseWhenFromMissing(): void
    {
        $this->configure(smsOverrides: ['from' => '']);
        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testValidateConfigRejectsMissingApiKey(): void
    {
        $this->assertFalse($this->createProvider()->validateConfig(['shared' => []]));
    }

    // --- Send ---

    public function testSendReturnsSentWithProviderIdFromMessageIds(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status'     => 'OK',
            'scheduled'  => 'Now',
            'messageIds' => ['OK: 69991a73-a560-429f-9c5a-3251dc1522bb'],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('69991a73-a560-429f-9c5a-3251dc1522bb', $result->providerId);
    }

    public function testSendPostsCorrectPayloadAndApikeyHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'OK', 'messageIds' => ['OK: id-1']]);

        $this->createProvider()->send($this->createMessage('+34600111222', 'Hi there'));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['apikey']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);
        $this->assertArrayNotHasKey('Authorization', $args['headers']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::FROM, $body['from']);
        $this->assertSame('34600111222', $body['to']); // leading + stripped
        $this->assertSame('Hi there', $body['text']);
        $this->assertSame(0, $body['type']); // ASCII => GSM
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'OK', 'messageIds' => ['OK: id-2']]);

        $this->createProvider()->send($this->createMessage('+15551234567', 'Hi'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('15551234567', $body['to']);
    }

    public function testSendDoesNotStripWhenRecipientHasNoPlus(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'OK', 'messageIds' => ['OK: id-3']]);

        $this->createProvider()->send($this->createMessage('15551234567', 'Hi'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('15551234567', $body['to']);
    }

    public function testSendSetsTypeOneForUnicodeBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'OK', 'messageIds' => ['OK: id-uc']]);

        $this->createProvider()->send($this->createMessage('+34600111222', 'سلام'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(1, $body['type']);
    }

    public function testSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsAuthErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 4003, 'description' => 'Invalid API key'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendSurfacesApiErrorDescription(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 4012, 'description' => 'Invalid mobile number'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid mobile number', $result->error);
        $this->assertSame(4012, $result->meta['easysendsms_code']);
    }

    public function testSendFailsOn200ButStatusNotOk(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'ERROR', 'description' => 'Insufficient credit']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credit', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalanceString(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => 247247]);

        $this->assertSame('247247', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 4003], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditUsesBalanceEndpointWithApikeyHeader(): void
    {
        $this->configure();
        $captured = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode(['balance' => 500]),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->getCredit();

        $this->assertSame(self::BALANCE_URL, $captured['url']);
        $this->assertSame(self::API_KEY, $captured['args']['headers']['apikey']);
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => 15]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('15', $result->message);
        $this->assertSame(15, $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 4003], 401);

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

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/easysendsms/status');
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

    public function testParseStatusCallbackMapsDelivrdToDelivered(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('sms_id', 'fd940f38-151d-47f1-81b8-22d1220f7a55');
        $request->set_param('source', self::FROM);
        $request->set_param('msisdn', '12345678900');
        $request->set_param('response', 'DELIVRD');
        $request->set_param('sent_date', '5/24/2024 11:23:34 PM');

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('fd940f38-151d-47f1-81b8-22d1220f7a55', $update->providerId);
        $this->assertSame('delivered', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testParseStatusCallbackMapsExpiredToPermanentFailure(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('sms_id', 'id-expired');
        $request->set_param('response', 'EXPIRED');

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('EXPIRED', $update->errorCode);
        $this->assertStringContainsString('EXPIRED', $update->errorMessage);
    }

    public function testParseStatusCallbackMapsUndelivToPermanentFailure(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('sms_id', 'id-undeliv');
        $request->set_param('response', 'UNDELIV');

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
    }

    public function testParseStatusCallbackReturnsEmptyForMissingSmsId(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('response', 'DELIVRD');

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }
}
