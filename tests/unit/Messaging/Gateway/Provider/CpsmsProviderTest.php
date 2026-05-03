<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\CpsmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class CpsmsProviderTest extends AbstractProviderTestCase
{
    private const USERNAME       = 'cpsms-user';
    private const API_KEY        = 'cpsms-api-key-12345';
    private const CALLBACK_TOKEN = 'callback-secret-7890';
    private const SENDER         = 'WSMS';
    private const SEND_URL       = 'https://api.cpsms.dk/v2/send';
    private const CREDIT_URL     = 'https://api.cpsms.dk/v2/creditvalue';

    protected function createProvider(): AbstractProvider
    {
        return new CpsmsProvider();
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
            'cpsms' => [
                'shared' => array_merge([
                    'username'       => self::USERNAME,
                    'api_key'        => self::API_KEY,
                    'callback_token' => self::CALLBACK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+4520202020', string $body = 'Hej'): Message
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

    private function expectedBasicAuth(): string
    {
        return 'Basic ' . base64_encode(self::USERNAME . ':' . self::API_KEY);
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
        $this->assertFalse(CpsmsProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('cpsms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testGetConfigSchemaHasRequiredFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertFalse(!empty($schema['shared']['callback_token']['required']));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
    }

    // --- Send ---

    public function testDoSendSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success' => [['to' => '+4520202020', 'cost' => 100, 'smsAmount' => 1]],
        ]);

        $result = $this->createProvider()->send($this->createMessage('+4520202020', 'Hej Verden'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertNotEmpty($result->providerId);
        $this->assertStringStartsWith('wsms-', $result->providerId);
        $this->assertLessThanOrEqual(32, strlen($result->providerId));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedBasicAuth(), $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('+4520202020', $body['to']);
        $this->assertSame('Hej Verden', $body['message']);
        $this->assertSame(self::SENDER, $body['from']);
        $this->assertSame($result->providerId, $body['reference']);
        $this->assertStringContainsString('messageid=' . rawurlencode($result->providerId), $body['dlr_url']);
        $this->assertStringContainsString('%26token=' . rawurlencode(self::CALLBACK_TOKEN), $body['dlr_url']);
    }

    public function testDoSendOmitsDlrUrlWhenCallbackTokenMissing(): void
    {
        $this->configure(['callback_token' => '']);
        $this->mockHttpPost([
            'success' => [['to' => '+4520202020', 'cost' => 100, 'smsAmount' => 1]],
        ]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('dlr_url', $body);
    }

    public function testDoSendFailureMapsErrorMessage(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'error' => ['code' => 'E001', 'message' => 'Sender not allowed'],
        ], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Sender not allowed', $result->error);
        $this->assertSame('E001', $result->meta['cpsms_code'] ?? null);
        $this->assertSame('400', $result->meta['cpsms_http'] ?? null);
    }

    public function testDoSendUnauthorizedReturnsCredentialError(): void
    {
        $this->configure();
        $this->mockHttpPost([], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid CPSMS credentials', $result->error);
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
        $this->mockHttpGet(['credit' => '1234.50']);

        $this->assertSame('1234.50', $this->createProvider()->getCredit());
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
        $this->mockHttpGet(['credit' => '5000']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Connected to CPSMS', $result->message);
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

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback ---

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
            'status'    => '1',
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

    public function testParseStatusCallbackMapsCodes(): void
    {
        $cases = [
            '1' => ['delivered', false],
            '4' => ['sent',      false],
            '2' => ['failed',    true],
            '8' => ['failed',    true],
        ];

        $p = $this->createProvider();
        foreach ($cases as $raw => [$status, $permanent]) {
            $request = $this->buildRequest('GET', [
                'messageid' => 'wsms-' . $raw,
                'status'    => $raw,
                'receiver'  => '+4520202020',
            ]);
            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for status {$raw}");
            $this->assertSame($status, $updates[0]->status, "wrong status for {$raw}");
            $this->assertSame($permanent, $updates[0]->permanent, "wrong permanent for {$raw}");
            $this->assertSame('wsms-' . $raw, $updates[0]->providerId);
        }
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('GET', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackSetsErrorCodeOnFailure(): void
    {
        $request = $this->buildRequest('GET', [
            'messageid' => 'wsms-bad',
            'status'    => '2',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('2', $update->errorCode);
    }

    public function testStatusCallbackUrlCarriesProviderId(): void
    {
        $this->assertStringContainsString(
            'callbacks/cpsms/status',
            $this->createProvider()->getStatusCallbackUrl(),
        );
    }
}
