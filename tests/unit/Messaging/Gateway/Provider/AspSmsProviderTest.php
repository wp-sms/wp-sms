<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\AspSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class AspSmsProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'aspsms-userkey';
    private const PASSWORD = 'aspsms-password';
    private const ORIGINATOR = 'WSMS';
    private const TOKEN = 'callback-shared-token';

    protected function createProvider(): AbstractProvider
    {
        return new AspSmsProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'aspsms' => [
                'shared' => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from_number' => self::ORIGINATOR], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+41700000000', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body, null, []);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('aspsms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsTrueAfterManualVerification(): void
    {
        $this->assertTrue(AspSmsProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);

        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['callback_token']['type']);
        $this->assertFalse($schema['shared']['callback_token']['required'] ?? false);

        $this->assertArrayHasKey('from_number', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from_number']['required']);
        $this->assertArrayHasKey('flash_sms', $schema['channels']['sms']);
        $this->assertSame('boolean', $schema['channels']['sms']['flash_sms']['type']);
    }

    public function testFeaturesAdvertiseFlashAndIncoming(): void
    {
        $features = $this->createProvider()->getFeatures();
        $this->assertTrue($features['flash_sms']);
        $this->assertTrue($features['delivery_receipt']);
        $this->assertTrue($features['incoming']);
        $this->assertTrue($features['unicode']);
    }

    // --- Send ---

    public function testSendSucceedsWithStatusCodeOne(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'StatusCode'     => '1',
            'StatusInfo'     => 'OK',
            'MessageNumbers' => [
                ['PhoneNumber' => '+41700000000', 'TransactionReferenceNumber' => 'tx-abc-1'],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('tx-abc-1', $result->providerId);
    }

    public function testSendPostsToSendTextSmsEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '1', 'StatusInfo' => 'OK', 'MessageNumbers' => ['tx-1']]);

        $this->createProvider()->send($this->createMessage('+41700000000', 'Hi'));

        $this->assertSame(
            'https://json.aspsms.com/SendTextSMS',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json; charset=UTF-8', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::USERNAME, $body['UserName']);
        $this->assertSame(self::PASSWORD, $body['Password']);
        $this->assertSame(self::ORIGINATOR, $body['Originator']);
        $this->assertSame(['+41700000000'], $body['Recipients']);
        $this->assertSame('Hi', $body['MessageText']);
        $this->assertFalse($body['ForceGSM7bit']);
    }

    public function testSendOmitsCallbackUrlsWhenNoToken(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '1', 'StatusInfo' => 'OK', 'MessageNumbers' => ['tx-1']]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('URLDeliveryNotification', $body);
        $this->assertArrayNotHasKey('URLNonDeliveryNotification', $body);
        $this->assertArrayNotHasKey('URLBufferedMessageNotification', $body);
    }

    public function testSendIncludesCallbackUrlsWhenTokenConfigured(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $this->mockHttpPost(['StatusCode' => '1', 'StatusInfo' => 'OK', 'MessageNumbers' => ['tx-1']]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertStringContainsString('callbacks/aspsms/status', $body['URLDeliveryNotification']);
        $this->assertStringContainsString('token=' . self::TOKEN, $body['URLDeliveryNotification']);
        $this->assertStringContainsString('tx=<TransactionReferenceNumber>', $body['URLDeliveryNotification']);
        $this->assertStringContainsString('kind=delivered', $body['URLDeliveryNotification']);
        $this->assertStringContainsString('kind=failed', $body['URLNonDeliveryNotification']);
        $this->assertStringContainsString('kind=buffered', $body['URLBufferedMessageNotification']);
    }

    public function testSendForwardsFlashFlagWhenEnabled(): void
    {
        $this->configure([], ['flash_sms' => true]);
        $this->mockHttpPost(['StatusCode' => '1', 'StatusInfo' => 'OK', 'MessageNumbers' => ['tx-1']]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertTrue($body['FlashingSMS']);
    }

    public function testSendForwardsAffiliateIdWhenSet(): void
    {
        $this->configure(['affiliate_id' => 'partner-42']);
        $this->mockHttpPost(['StatusCode' => '1', 'StatusInfo' => 'OK', 'MessageNumbers' => ['tx-1']]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('partner-42', $body['AffiliateId']);
    }

    public function testSendFailsOnAuthErrorStatusCodeThree(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '3', 'StatusInfo' => 'Authorization failed']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Userkey', $result->error);
        $this->assertSame('3', $result->meta['aspsms_status_code']);
    }

    public function testSendFailsOnInsufficientCreditStatusCodeFive(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '5', 'StatusInfo' => 'Not enough Credits']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credits', strtolower($result->error));
    }

    public function testSendFailsOnInvalidOriginatorStatusCodeTen(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '10', 'StatusInfo' => 'Invalid originator']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Originator', $result->error);
    }

    public function testSendFailsOnUnknownStatusCodeFallsBackToInfo(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '99', 'StatusInfo' => 'Unexpected error']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Unexpected error', $result->error);
    }

    public function testSendFailsWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsWhenOriginatorMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'aspsms' => [
                'shared'   => ['username' => self::USERNAME, 'password' => self::PASSWORD],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Originator', $result->error);
    }

    public function testSendFailsOnHttpError(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'oops'], 502);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('502', $result->error);
    }

    // --- Credit / Test Connection ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '1', 'StatusInfo' => 'OK', 'Credits' => '42.5']);

        $this->assertSame('42.5', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnAuthFailure(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '3', 'StatusInfo' => 'Authorization failed']);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithCredits(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '1', 'StatusInfo' => 'OK', 'Credits' => '42.5']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42.5', $result->message);
        $this->assertSame('42.5', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost(['StatusCode' => '3', 'StatusInfo' => 'Authorization failed']);

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

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('GET', '/x', ['tx' => 'abc', 'kind' => 'delivered']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMismatchedToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('GET', '/x', [
            'tx' => 'abc', 'kind' => 'delivered', 'token' => 'wrong',
        ]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenTokenNotConfigured(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', '/x', [
            'tx' => 'abc', 'kind' => 'delivered', 'token' => 'anything',
        ]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsValidToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('GET', '/x', [
            'tx' => 'abc', 'kind' => 'delivered', 'token' => self::TOKEN,
        ]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    /**
     * @dataProvider statusKindProvider
     */
    public function testParseStatusCallbackMapsKinds(string $kind, string $expected): void
    {
        $request = $this->buildRequest('GET', '/x', ['tx' => 'tx-1', 'kind' => $kind]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('tx-1', $updates[0]->providerId);
        $this->assertSame($expected, $updates[0]->status);
    }

    public static function statusKindProvider(): array
    {
        return [
            'delivered' => ['delivered', 'delivered'],
            'buffered'  => ['buffered', 'queued'],
            'failed'    => ['failed', 'failed'],
        ];
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('GET', '/x', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackRejectsMismatchedToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('GET', '/x', ['token' => 'nope']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackAcceptsValidToken(): void
    {
        $this->configure(['callback_token' => self::TOKEN]);
        $request = $this->buildRequest('GET', '/x', ['token' => self::TOKEN]);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundCallbackParsesGetParams(): void
    {
        $request = $this->buildRequest('GET', '/x', [
            'MessageData' => 'Hello back',
            'Recipient'   => '+41700000000',
            'Originator'  => '+41799999999',
            'DateReceived' => '29042026120000',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+41799999999', $msg->from);
        $this->assertSame('+41700000000', $msg->to);
        $this->assertSame('Hello back', $msg->body);
        $this->assertSame('29042026120000', $msg->meta['date_received']);
    }

    public function testParseInboundCallbackEmptyWithoutOriginator(): void
    {
        $request = $this->buildRequest('GET', '/x', [
            'Recipient' => '+41700000000', 'MessageData' => 'hi',
        ]);

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
                foreach ($params as $k => $v) $this->set_param($k, $v);
                foreach ($headers as $k => $v) $this->set_header($k, $v);
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }
}
