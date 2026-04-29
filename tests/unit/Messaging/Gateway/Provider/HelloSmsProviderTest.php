<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\HelloSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class HelloSmsProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'api-user-xyz';
    private const PASSWORD = 'api-secret-pw';
    private const FROM = 'WSMS';
    private const CALLBACK_TOKEN = 'test-callback-token-1234567890';

    protected function createProvider(): AbstractProvider
    {
        return new HelloSmsProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'hellosms' => [
                'shared' => array_merge([
                    'api_username'   => self::USERNAME,
                    'api_password'   => self::PASSWORD,
                    'callback_token' => self::CALLBACK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+46700000000', string $body = 'Hello'): Message
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
        return 'Basic ' . base64_encode(self::USERNAME . ':' . self::PASSWORD);
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('hellosms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(HelloSmsProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['api_username']['type']);
        $this->assertTrue($schema['shared']['api_username']['required']);

        $this->assertArrayHasKey('api_password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_password']['type']);
        $this->assertTrue($schema['shared']['api_password']['required']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertTrue($schema['shared']['callback_token']['required']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertFalse(!empty($schema['channels']['sms']['from']['required']));
    }

    public function testIsConfiguredForSmsRequiresAllSharedFields(): void
    {
        $this->configure(['callback_token' => '']);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send ---

    public function testSendQueuedReturnsApiMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status'      => 'success',
            'statusText'  => 'Message queued',
            'messageIds'  => [['apiMessageId' => 'api-638abc123', 'to' => '+46700000000', 'status' => 0]],
            'messageCount' => 1,
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('api-638abc123', $result->providerId);
    }

    public function testSendPostsToCorrectUrlWithBasicAuthAndJsonBody(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status'     => 'success',
            'messageIds' => [['apiMessageId' => 'api-1', 'status' => 0]],
        ]);

        $this->createProvider()->send($this->createMessage('+46700000001', 'Hej'));

        $this->assertSame(
            'https://api.hellosms.se/api/v1/sms/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedBasicAuth(), $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('+46700000001', $body['to']);
        $this->assertSame('Hej', $body['message']);
        $this->assertSame(self::FROM, $body['from']);
        $this->assertTrue($body['sendApiCallback']);
    }

    public function testSendOmitsFromWhenBlank(): void
    {
        $this->configure([], ['from' => '']);
        $this->mockHttpPost([
            'status'     => 'success',
            'messageIds' => [['apiMessageId' => 'api-1', 'status' => 0]],
        ]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('from', $body);
    }

    public function testSendOmitsCallbackFlagWhenTokenMissing(): void
    {
        $this->configure(['callback_token' => '']);
        $this->mockHttpPost([
            'status'     => 'success',
            'messageIds' => [['apiMessageId' => 'api-1', 'status' => 0]],
        ]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('sendApiCallback', $body);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'failed'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendBubblesUpStatusTextOnFailedTopLevelStatus(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status'     => 'failed',
            'statusText' => 'Invalid sender',
        ], 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid sender', $result->error);
    }

    public function testSendCapturesPerRecipientStatusMinus5(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status'     => 'success',
            'messageIds' => [[
                'apiMessageId' => 'api-pl',
                'to'           => '+48700000000',
                'status'       => -5,
                'message'      => 'Country not enabled',
            ]],
        ]);

        $result = $this->createProvider()->send($this->createMessage('+48700000000'));

        $this->assertFalse($result->success);
        $this->assertSame('-5', $result->meta['hellosms_status']);
        $this->assertSame('Country not enabled', $result->meta['hellosms_message']);
    }

    // --- Credit / Test Connection ---

    public function testGetCreditReadsCreditsField(): void
    {
        $this->configure();
        $this->mockHttpGet(['credits' => '42.5']);

        $this->assertSame('42.50 credits', $this->createProvider()->getCredit());
    }

    public function testGetCreditFallsBackToBalanceField(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => '7']);

        $this->assertSame('7.00 credits', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenNoKnownField(): void
    {
        $this->configure();
        $this->mockHttpGet(['something_else' => 99]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionHitsTestEndpointAndReturnsOk(): void
    {
        $this->configure();
        $this->mockHttpGet(['ok' => true]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
    }

    public function testTestConnectionReturnsErrorOn401(): void
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

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', [
            'token'        => self::CALLBACK_TOKEN,
            'apiMessageId' => 'api-1',
            'status'       => 'delivered',
        ]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsStatuses(): void
    {
        $cases = [
            'delivered'     => 'delivered',
            'failed'        => 'failed',
            'not delivered' => 'failed',
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => $expected) {
            $request = $this->buildRequest('POST', '/x', [
                'apiMessageId' => 'api-' . md5($raw),
                'status'       => $raw,
            ]);
            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expected, $updates[0]->status, "wrong mapping for {$raw}");
        }
    }

    public function testParseStatusCallbackMarksPermanentOnFailure(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'apiMessageId' => 'api-bad',
            'status'       => 'failed',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertTrue($update->permanent);
        $this->assertSame('failed', $update->errorCode);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('POST', '/x', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', [
            'token'  => self::CALLBACK_TOKEN,
            'from'   => '+46700000000',
            'action' => 'INCOMING_SMS',
        ]);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundCallbackProducesIncomingMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'message_id' => 'in-123',
            'from'       => '+46700000000',
            'to'         => '+46707654321',
            'text'       => 'Hello back',
            'timestamp'  => 1700000000,
            'action'     => 'INCOMING_SMS',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+46700000000', $msg->from);
        $this->assertSame('+46707654321', $msg->to);
        $this->assertSame('Hello back', $msg->body);
        $this->assertSame('in-123', $msg->providerId);
        $this->assertNull($msg->optOutType);
        $this->assertSame('INCOMING_SMS', $msg->meta['action']);
    }

    public function testParseInboundCallbackEmitsOptOutOnSignoff(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'message_id' => 'sig-1',
            'from'       => '+46700000000',
            'to'         => '+46707654321',
            'text'       => '',
            'action'     => 'SIGNOFF',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('STOP', $messages[0]->optOutType);
        $this->assertSame('STOP', $messages[0]->body);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildRequest('POST', '/x', ['action' => 'INCOMING_SMS']);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = []): \WP_REST_Request
    {
        $request = new \WP_REST_Request($method, $route);
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        foreach ($headers as $k => $v) {
            $request->set_header($k, $v);
        }
        return $request;
    }
}
