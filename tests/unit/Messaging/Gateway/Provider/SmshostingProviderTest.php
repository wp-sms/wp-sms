<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmshostingProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmshostingProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'smshosting-user';
    private const PASSWORD = 'smshosting-pass';
    private const SENDER   = 'WSMS';
    private const TOKEN    = 'callback-shared-token';

    protected function createProvider(): AbstractProvider
    {
        return new SmshostingProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smshosting' => [
                'shared' => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+393480000000', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body, null, []);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smshosting', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmshostingProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);

        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['callback_token']['type']);
        $this->assertFalse($schema['shared']['callback_token']['required'] ?? false);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
    }

    public function testFeaturesAdvertiseIncomingAndTestConnection(): void
    {
        $features = $this->createProvider()->getFeatures();
        $this->assertTrue($features['incoming']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
        $this->assertFalse($features['delivery_receipt']);
    }

    // --- Send ---

    public function testSendPostsFormBodyWithBasicAuth(): void
    {
        $this->configure();
        $this->mockHttpPost(['transactionId' => 'tx-abc-1']);

        $this->createProvider()->send($this->createMessage('+393480000001', 'Ciao'));

        $this->assertSame(
            'https://api.smshosting.it/rest/api/sms/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $expectedAuth = 'Basic ' . base64_encode(self::USERNAME . ':' . self::PASSWORD);
        $this->assertSame($expectedAuth, $args['headers']['Authorization']);
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        $this->assertSame('+393480000001', $args['body']['to']);
        $this->assertSame(self::SENDER, $args['body']['from']);
        $this->assertSame('Ciao', $args['body']['text']);
    }

    public function testSendReturnsTransactionIdOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost(['transactionId' => 'tx-abc-1']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('tx-abc-1', $result->providerId);
    }

    public function testSendFailsOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost(['errorCode' => 'AUTH', 'errorMsg' => 'Authentication failed'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendFailsOnNon2xxWithProviderErrorMessage(): void
    {
        $this->configure();
        $this->mockHttpPost(['errorCode' => 'BAD_NUMBER', 'errorMsg' => 'Invalid recipient'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid recipient', $result->error);
        $this->assertSame('BAD_NUMBER', $result->meta['smshosting_error_code']);
    }

    public function testSendFailsOnMalformedJson(): void
    {
        $this->configure();
        $this->mockHttpPost('not-json', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('HTTP 200', $result->error);
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
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smshosting' => [
                'shared'   => ['username' => self::USERNAME, 'password' => self::PASSWORD],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsItalysmsBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['italysms' => '42.5']);

        $this->assertSame('42.5', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnNon2xx(): void
    {
        $this->configure();
        $this->mockHttpGet([], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWithoutItalysmsField(): void
    {
        $this->configure();
        $this->mockHttpGet(['somethingElse' => 1]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionOkOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpGet(['italysms' => '42.5']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42.5', $result->message);
        $this->assertSame('42.5', $result->details['balance']);
    }

    public function testTestConnectionErrorOnInvalidCredentials(): void
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

    // --- Inbound callback ---

    public function testGetInboundCallbackUrlIncludesTokenWhenConfigured(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);

        $url = $this->createProvider()->getInboundCallbackUrl();

        $this->assertStringContainsString('callbacks/smshosting/inbound', $url);
        $this->assertStringContainsString('token=' . self::TOKEN, $url);
    }

    public function testGetInboundCallbackUrlOmitsTokenWhenNotConfigured(): void
    {
        $this->configure();

        $url = $this->createProvider()->getInboundCallbackUrl();

        $this->assertStringContainsString('callbacks/smshosting/inbound', $url);
        $this->assertStringNotContainsString('token=', $url);
    }

    public function testValidateInboundRejectsWhenTokenNotConfigured(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', '/x', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundRejectsMissingToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('GET', '/x', ['number' => '393480000001', 'text' => 'hi']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundRejectsWrongToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('GET', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundAcceptsValidToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('GET', '/x', ['token' => self::TOKEN]);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundReturnsInboundMessageFromQueryParams(): void
    {
        $request = $this->buildRequest('GET', '/x', [
            'number' => '393480000001',
            'text'   => 'Reply text',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('393480000001', $messages[0]->from);
        $this->assertSame('', $messages[0]->to);
        $this->assertSame('Reply text', $messages[0]->body);
    }

    public function testParseInboundEmptyWithoutNumber(): void
    {
        $request = $this->buildRequest('GET', '/x', ['text' => 'hi']);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testParseInboundEmptyWithoutText(): void
    {
        $request = $this->buildRequest('GET', '/x', ['number' => '393480000001']);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = []): \WP_REST_Request
    {
        return new class($method, $route, $params, $headers) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, array $params, array $headers) {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) {
                    $this->set_param($k, $v);
                }
                foreach ($headers as $k => $v) {
                    $this->set_header($k, $v);
                }
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }
}
