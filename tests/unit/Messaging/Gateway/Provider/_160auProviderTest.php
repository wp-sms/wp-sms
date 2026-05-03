<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\_160auProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class _160auProviderTest extends AbstractProviderTestCase
{
    private const USERNAME       = '160au-user';
    private const SMS_SECRET     = 'super-sms-secret';
    private const SENDER_ID      = 'WSMS';
    private const VIRTUAL_NUMBER = '+61400000000';
    private const DLR_TOKEN      = 'dlr-token-1234567890';
    private const MO_TOKEN       = 'mo-token-abcdefghij';

    protected function createProvider(): AbstractProvider
    {
        return new _160auProvider();
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
            '160au' => [
                'shared' => array_merge([
                    'username'           => self::USERNAME,
                    'sms_secret'         => self::SMS_SECRET,
                    'callback_token_dlr' => self::DLR_TOKEN,
                    'callback_token_mo'  => self::MO_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge([
                        'sender_id'      => self::SENDER_ID,
                        'virtual_number' => self::VIRTUAL_NUMBER,
                    ], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+61411111111', string $body = 'Hello'): Message
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
        return 'Basic ' . base64_encode(self::USERNAME . ':' . self::SMS_SECRET);
    }

    private function buildRequest(array $params): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/x');
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        return $request;
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(_160auProvider::TESTED);
    }

    public function testGetIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('160au', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    // --- Send ---

    public function testSendSuccessReturnsProviderId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'messages' => [[
                'messageId'  => 'msg-160au-001',
                'statusCode' => 0,
                'status'     => 'OK',
            ]],
        ], 201);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-160au-001', $result->providerId);
    }

    public function testSendPostsToCorrectUrlWithBasicAuthAndJsonBody(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'messages' => [['messageId' => 'm-1', 'statusCode' => 0, 'status' => 'OK']],
        ], 201);

        $this->createProvider()->send($this->createMessage('+61422222222', 'Hi mate'));

        $this->assertSame(
            'https://api.160.com.au/v1/messages',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedBasicAuth(), $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertCount(1, $body['messages']);
        $entry = $body['messages'][0];
        $this->assertSame('+61422222222', $entry['recipient']);
        $this->assertSame('Hi mate', $entry['message']);
        $this->assertSame(self::SENDER_ID, $entry['senderId']);
        $this->assertSame(self::VIRTUAL_NUMBER, $entry['phone']);
        $this->assertStringContainsString('callbacks/160au/status', $entry['callbackURL']);
        $this->assertStringContainsString('token=' . self::DLR_TOKEN, $entry['callbackURL']);
    }

    public function testSendOmitsOptionalFieldsWhenBlank(): void
    {
        $this->configure(
            ['callback_token_dlr' => ''],
            ['sender_id' => '', 'virtual_number' => ''],
        );
        $this->mockHttpPost([
            'messages' => [['messageId' => 'm-2', 'statusCode' => 0, 'status' => 'OK']],
        ], 201);

        $this->createProvider()->send($this->createMessage());

        $entry = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true)['messages'][0];
        $this->assertArrayNotHasKey('senderId', $entry);
        $this->assertArrayNotHasKey('phone', $entry);
        $this->assertArrayNotHasKey('callbackURL', $entry);
    }

    public function testSendMissingCredentialsFails(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendInvalidAuthMappedTo401Message(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
        $this->assertStringContainsString('SMS Secret', $result->error);
    }

    public function testSendNetworkErrorReturnsFailure(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'Connection refused');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Connection refused', $result->error);
    }

    public function testSendPropagatesStatusCodeMinus1AsFailure(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'messages' => [[
                'messageId'  => 'm-err',
                'statusCode' => -1,
                'status'     => 'Internal error',
            ]],
        ], 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Internal error', $result->error);
        $this->assertSame('-1', $result->meta['160au_status_code']);
        $this->assertTrue($result->retryable);
    }

    public function testSendRateLimitReturnsRetryableFailure(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'rate-limited'], 429);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    // --- Credit / Test Connection ---

    public function testGetCreditFormatsBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => 12.5]);

        $this->assertSame('12.50 credits', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenNoBalanceField(): void
    {
        $this->configure();
        $this->mockHttpGet(['something' => 1]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionOkOn200(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => 99.5]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame(99.5, $result->details['balance']);
        $this->assertStringContainsString('Balance', $result->message);
        $this->assertStringContainsString('99.50', $result->message);
    }

    public function testTestConnectionAuthErrorOn401(): void
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

    // --- Status callback (DLR) ---

    public function testStatusCallbackTokenValidationAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => self::DLR_TOKEN]);
        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackTokenValidationRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => 'wrong']);
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackTokenValidationRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest([]);
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackTokenValidationRejectsBlankConfiguredToken(): void
    {
        $this->configure(['callback_token_dlr' => '']);
        $request = $this->buildRequest(['token' => self::DLR_TOKEN]);
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsKnownStatuses(): void
    {
        $cases = [
            'delivered'   => 'delivered',
            'success'     => 'delivered',
            'failed'      => 'failed',
            'undelivered' => 'failed',
            'queued'      => 'sent',
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => $expected) {
            $request = $this->buildRequest([
                'messageId' => 'msg-' . $raw,
                'status'    => $raw,
            ]);
            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expected, $updates[0]->status, "wrong mapping for {$raw}");
        }
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest([]);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testInboundCallbackTokenValidationAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => self::MO_TOKEN]);
        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testInboundCallbackTokenValidationRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => 'wrong']);
        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testInboundCallbackTokenValidationRejectsDlrToken(): void
    {
        // DLR and MO tokens must be independently validated.
        $this->configure();
        $request = $this->buildRequest(['token' => self::DLR_TOKEN]);
        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundCallbackProducesIncomingMessage(): void
    {
        $request = $this->buildRequest([
            'messageId' => 'in-99',
            'sender'    => '+61411222333',
            'recipient' => self::VIRTUAL_NUMBER,
            'message'   => 'Reply text',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+61411222333', $msg->from);
        $this->assertSame(self::VIRTUAL_NUMBER, $msg->to);
        $this->assertSame('Reply text', $msg->body);
        $this->assertSame('in-99', $msg->providerId);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildRequest(['message' => 'orphaned']);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }
}
