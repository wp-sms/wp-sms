<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\GlobalVoiceProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class GlobalVoiceProviderTest extends AbstractProviderTestCase
{
    private const TOKEN = 'gv-token-abc';
    private const ACC_ID = '12345';
    private const WEBHOOK_TOKEN = 'gv-webhook-secret';
    private const SENDER = 'MyBrand';

    protected function createProvider(): AbstractProvider
    {
        return new GlobalVoiceProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'globalvoice' => [
                'shared'   => array_merge([
                    'token'         => self::TOKEN,
                    'acc_id'        => self::ACC_ID,
                    'webhook_token' => self::WEBHOOK_TOKEN,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms' => ['sender_id' => self::SENDER],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $recipient = '+15559876543', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
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

    // --- Identity ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(GlobalVoiceProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('globalvoice', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('token', $schema['shared']);
        $this->assertTrue($schema['shared']['token']['required']);
        $this->assertSame('secret', $schema['shared']['token']['type']);

        $this->assertArrayHasKey('acc_id', $schema['shared']);
        $this->assertFalse($schema['shared']['acc_id']['required'] ?? false);

        $this->assertArrayHasKey('webhook_token', $schema['shared']);
        $this->assertFalse($schema['shared']['webhook_token']['required'] ?? false);
        $this->assertSame('secret', $schema['shared']['webhook_token']['type']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
    }

    // --- doSend ---

    public function testDoSendHappyPath(): void
    {
        $this->configure();
        $this->mockHttpPost(['message_id' => 'gv-msg-001']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('gv-msg-001', $result->providerId);
    }

    public function testDoSendBuildsCorrectRequest(): void
    {
        $this->configure();
        $this->mockHttpPost(['message_id' => 'x']);

        $this->createProvider()->send($this->createMessage('+15559876543', 'Hi there'));

        $this->assertSame('https://rest.global-voice.net/rest/send_sms', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::TOKEN, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Accept']);

        // Form-encoded body (array passed to wp_remote_post becomes application/x-www-form-urlencoded).
        $this->assertIsArray($args['body']);
        $this->assertSame(self::SENDER, $args['body']['from']);
        $this->assertSame('+15559876543', $args['body']['to']);
        $this->assertSame('Hi there', $args['body']['message']);
        $this->assertSame(self::ACC_ID, $args['body']['acc_id']);
    }

    public function testDoSendOmitsAccIdWhenBlank(): void
    {
        $this->configure(sharedOverrides: ['acc_id' => '']);
        $this->mockHttpPost(['message_id' => 'x']);

        $this->createProvider()->send($this->createMessage());

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertArrayNotHasKey('acc_id', $body);
    }

    public function testDoSendHttp400ReturnsFailure(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'INVALID_RECIPIENT'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('INVALID_RECIPIENT', $result->error);
    }

    public function testDoSendHandlesV7ErrorShape(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'BadRequest', 'message' => 'Sender not allowed'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        // v7 used $response->message for the user-facing error.
        $this->assertStringContainsString('Sender not allowed', $result->error);
    }

    public function testDoSendInvalidTokenMaps401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Global Voice token', $result->error);
    }

    public function testDoSendNetworkErrorIsRetryable(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'Connection timed out');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Connection timed out', $result->error);
    }

    public function testDoSendMissingTokenFails(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        unset($GLOBALS['_test_wp_remote_post_last_url']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
        $this->assertArrayNotHasKey('_test_wp_remote_post_last_url', $GLOBALS);
    }

    public function testDoSendMissingSenderFails(): void
    {
        $this->configure(channelOverrides: ['sms' => ['sender_id' => '']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditParsesAccountResponse(): void
    {
        $this->configure();
        $this->mockHttpGet([['balance' => '12.5', 'currency_code' => 'EUR']]);

        $this->assertSame('12.5 EUR', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWithoutToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnHttpError(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauthorized'], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $this->mockHttpGet([['balance' => '50.00', 'currency_code' => 'EUR']]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('50.00', $result->message);
        $this->assertStringContainsString('EUR', $result->message);
        $this->assertSame('50.00', $result->details['balance']);
        $this->assertSame('EUR', $result->details['currency']);
    }

    public function testTestConnectionInvalidToken(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Global Voice token', $result->message);
    }

    public function testTestConnectionInvalidTokenWith403(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'forbidden'], 403);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Global Voice token', $result->message);
    }

    public function testTestConnectionRequiresToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
    }

    public function testTestConnectionNetworkFailure(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'Down');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
    }

    // --- Status callback validation ---

    public function testValidateStatusCallbackRejectsWithoutToken(): void
    {
        $this->configure(sharedOverrides: ['webhook_token' => '']);
        $request = $this->buildRequest('POST', '/x', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong-secret']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingTokenParam(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => self::WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    // --- Status callback parsing ---

    public function testParseStatusCallbackDelivered(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'message_id'      => 'gv-msg-001',
            'delivery_status' => 'DELIVRD',
            'result_code'     => '0x0000000',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('gv-msg-001', $update->providerId);
        $this->assertSame('delivered', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testParseStatusCallbackDeliveredViaResultCodeOnly(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'message_id'  => 'gv-msg-002',
            'result_code' => '0x0000000',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('delivered', $update->status);
    }

    public function testParseStatusCallbackPermanentFailure(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'message_id'      => 'gv-msg-003',
            'delivery_status' => 'UNDELIV',
            'result_code'     => '0x0000001',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('0x0000001', $update->errorCode);
    }

    public function testParseStatusCallbackTransientFailure(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'message_id'      => 'gv-msg-004',
            'delivery_status' => 'EXPIRED',
            'result_code'     => '0x0000005',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testParseStatusCallbackReturnsEmptyForMissingMessageId(): void
    {
        $request = $this->buildRequest('POST', '/x', ['delivery_status' => 'DELIVRD']);

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback validation ---

    public function testValidateInboundCallbackRejectsWithoutToken(): void
    {
        $this->configure(sharedOverrides: ['webhook_token' => '']);
        $request = $this->buildRequest('POST', '/x', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => self::WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    // --- Inbound callback parsing ---

    public function testParseInboundCallbackBuildsInboundMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'message_id' => 'in-001',
            'ani'        => '+15559876543',
            'dnis'       => self::SENDER,
            'message'    => 'STOP',
        ]);

        $msg = $this->createProvider()->parseInboundCallback($request)[0];

        $this->assertSame('+15559876543', $msg->from);
        $this->assertSame(self::SENDER, $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('in-001', $msg->providerId);
    }

    public function testParseInboundCallbackReturnsEmptyForMissingAni(): void
    {
        $request = $this->buildRequest('POST', '/x', ['message' => 'hi']);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Callback URLs ---

    public function testCallbackUrlsIncludeProviderSlug(): void
    {
        $p = $this->createProvider();
        $this->assertStringContainsString('callbacks/globalvoice/status', $p->getStatusCallbackUrl());
        $this->assertStringContainsString('callbacks/globalvoice/inbound', $p->getInboundCallbackUrl());
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = [], ?string $body = null): \WP_REST_Request
    {
        return new class($method, $route, $params, $headers, $body) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, array $params, array $headers, ?string $body) {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) {
                    $this->set_param($k, $v);
                }
                foreach ($headers as $k => $v) {
                    $this->set_header($k, $v);
                }
                if ($body !== null) {
                    $this->set_body($body);
                }
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }
}
