<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\CheapglobalSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class CheapglobalSmsProviderTest extends AbstractProviderTestCase
{
    private const SUB_ACCOUNT     = 'me@example.com';
    private const SUB_PASS        = 'super-secret';
    private const CALLBACK_TOKEN  = 'cgsms-webhook-token';
    private const SENDER          = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new CheapglobalSmsProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsChannelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'cheapglobalsms' => [
                'shared'   => array_merge([
                    'sub_account'      => self::SUB_ACCOUNT,
                    'sub_account_pass' => self::SUB_PASS,
                    'callback_token'   => self::CALLBACK_TOKEN,
                    'route'            => '0',
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_id' => self::SENDER], $smsChannelOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+2348012345678', string $body = 'Hello world', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function buildRequest(string $method, array $params): \WP_REST_Request
    {
        return new class($method, $params) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, array $params)
            {
                parent::__construct($method, '/');
                $this->methodOverride = $method;
                foreach ($params as $k => $v) {
                    $this->set_param($k, $v);
                }
            }
            public function get_method(): string
            {
                return $this->methodOverride;
            }
        };
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('cheapglobalsms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(CheapglobalSmsProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('string', $schema['shared']['sub_account']['type']);
        $this->assertTrue($schema['shared']['sub_account']['required']);
        $this->assertSame('secret', $schema['shared']['sub_account_pass']['type']);
        $this->assertTrue($schema['shared']['sub_account_pass']['required']);
        $this->assertTrue($schema['shared']['callback_token']['required']);
        $this->assertSame('select', $schema['shared']['route']['type']);
        $this->assertSame('0', $schema['shared']['route']['default']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertFalse($schema['channels']['sms']['sender_id']['required']);
        $this->assertSame('boolean', $schema['channels']['sms']['flash']['type']);
        $this->assertSame('select', $schema['channels']['sms']['unicode_mode']['type']);
        $this->assertSame('2', $schema['channels']['sms']['unicode_mode']['default']);
    }

    public function testFeaturesAdvertiseFlashIncomingDelivery(): void
    {
        $features = $this->createProvider()->getFeatures();

        $this->assertTrue($features['flash_sms']);
        $this->assertTrue($features['delivery_receipt']);
        $this->assertTrue($features['incoming']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
    }

    // --- doSend ---

    public function testSendQueuedReturnsBatchId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'batch_id' => 'BATCH-789',
            'total'    => 1,
            'summary'  => ['2348012345678' => ['initial_units' => 1, 'pages' => 1, 'pending_review' => false]],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('BATCH-789', $result->providerId);
    }

    public function testSendPostsToApiEndpointWithFormBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['batch_id' => 'B1']);

        $this->createProvider()->send($this->createMessage('+2348012345678', 'Hi there'));

        $this->assertSame('https://cheapglobalsms.com/api_v1/', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $body);
        $this->assertSame(self::SUB_ACCOUNT, $body['sub_account']);
        $this->assertSame(self::SUB_PASS, $body['sub_account_pass']);
        $this->assertSame('send_sms', $body['action']);
        $this->assertSame('2348012345678', $body['recipients']);
        $this->assertSame('Hi there', $body['message']);
        $this->assertSame(self::SENDER, $body['sender_id']);
        $this->assertSame('2', $body['unicode']);
        $this->assertSame('0', $body['route']);
        $this->assertStringContainsString('callbacks/cheapglobalsms/status', $body['callback_url']);
        $this->assertStringContainsString('token=' . self::CALLBACK_TOKEN, $body['callback_url']);
        $this->assertArrayNotHasKey('type', $body);
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost(['batch_id' => 'B1']);

        $this->createProvider()->send($this->createMessage('+2348012345678'));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertSame('2348012345678', $body['recipients']);
    }

    public function testSendIncludesFlashTypeWhenChannelFlagOn(): void
    {
        $this->configure(smsChannelOverrides: ['flash' => true]);
        $this->mockHttpPost(['batch_id' => 'B-flash']);

        $this->createProvider()->send($this->createMessage());

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertSame('1', $body['type']);
    }

    public function testSendOmitsSenderIdWhenBlank(): void
    {
        $this->configure(smsChannelOverrides: ['sender_id' => '']);
        $this->mockHttpPost(['batch_id' => 'B1']);

        $this->createProvider()->send($this->createMessage());

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertArrayNotHasKey('sender_id', $body);
    }

    public function testSendOmitsCallbackUrlWhenTokenMissing(): void
    {
        $this->configure(sharedOverrides: ['callback_token' => '']);
        $this->mockHttpPost(['batch_id' => 'B1']);

        $this->createProvider()->send($this->createMessage());

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertArrayNotHasKey('callback_url', $body);
    }

    public function testSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedOnApiErrorEnvelope(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'insufficient credit', 'error_code' => 11]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('insufficient credit', $result->error);
        $this->assertSame('11', $result->meta['cgsms_error_code']);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'auth failed'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    // --- parseStatusCallback ---

    public function testParseStatusCallbackParsesBatchedJsonResult(): void
    {
        $request = $this->buildRequest('POST', [
            'result' => json_encode([
                ['sms_id' => 'm-1', 'batch_id' => 'B1', 'status' => 2,  'status_msg' => 'DELIVERED'],
                ['sms_id' => 'm-2', 'batch_id' => 'B1', 'status' => 1,  'status_msg' => 'SENT'],
                ['sms_id' => 'm-3', 'batch_id' => 'B1', 'status' => 0,  'status_msg' => 'PENDING'],
                ['sms_id' => 'm-4', 'batch_id' => 'B1', 'status' => -1, 'status_msg' => 'FAILED'],
                ['sms_id' => 'm-5', 'batch_id' => 'B1', 'status' => -2, 'status_msg' => 'REJECTED'],
                ['sms_id' => 'm-6', 'batch_id' => 'B1', 'status' => -3, 'status_msg' => 'EXPIRED'],
                ['sms_id' => 'm-7', 'batch_id' => 'B1', 'status' => -4, 'status_msg' => 'UNDELIVERABLE'],
            ]),
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(7, $updates);
        $this->assertSame('m-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);

        $this->assertSame('sent', $updates[1]->status);
        $this->assertSame('queued', $updates[2]->status);

        $this->assertSame('failed', $updates[3]->status);
        $this->assertFalse($updates[3]->permanent);

        $this->assertSame('failed', $updates[4]->status);
        $this->assertTrue($updates[4]->permanent);

        $this->assertSame('failed', $updates[5]->status);
        $this->assertFalse($updates[5]->permanent);

        $this->assertSame('failed', $updates[6]->status);
        $this->assertTrue($updates[6]->permanent);
    }

    public function testParseStatusCallbackReturnsEmptyWhenResultMissing(): void
    {
        $this->assertSame([], $this->createProvider()->parseStatusCallback($this->buildRequest('POST', [])));
    }

    public function testParseStatusCallbackReturnsEmptyWhenResultMalformed(): void
    {
        $request = $this->buildRequest('POST', ['result' => '{not valid json']);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => self::CALLBACK_TOKEN, 'result' => '[]']);
        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['result' => '[]']);
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => 'wrong', 'result' => '[]']);
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenSecretUnset(): void
    {
        $this->configure(sharedOverrides: ['callback_token' => '']);
        $request = $this->buildRequest('POST', ['token' => 'anything']);
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    // --- parseInboundCallback ---

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = $this->buildRequest('POST', [
            'sender'        => '+2348099887766',
            'recipient'     => '+2348011223344',
            'message'       => 'STOP CAMPAIGN1 unsubscribe please',
            'clean_message' => 'unsubscribe please',
            'keyword'       => 'STOP',
            'campaign_id'   => 'cmp-42',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+2348099887766', $messages[0]->from);
        $this->assertSame('+2348011223344', $messages[0]->to);
        $this->assertSame('unsubscribe please', $messages[0]->body);
        $this->assertSame('cmp-42', $messages[0]->meta['campaign_id']);
        $this->assertSame('STOP', $messages[0]->meta['keyword']);
    }

    public function testParseInboundCallbackFallsBackToFullMessageWhenCleanAbsent(): void
    {
        $request = $this->buildRequest('POST', [
            'sender'    => '+2348099887766',
            'recipient' => '+2348011223344',
            'message'   => 'hi there',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('hi there', $messages[0]->body);
    }

    public function testParseInboundCallbackEmptyWithoutSender(): void
    {
        $request = $this->buildRequest('POST', ['message' => 'hi']);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testValidateInboundCallbackUsesSameTokenLogic(): void
    {
        $this->configure();
        $okRequest  = $this->buildRequest('POST', ['token' => self::CALLBACK_TOKEN]);
        $badRequest = $this->buildRequest('POST', ['token' => 'wrong']);

        $this->assertTrue($this->createProvider()->validateInboundCallback($okRequest));
        $this->assertFalse($this->createProvider()->validateInboundCallback($badRequest));
    }

    // --- Credit / Test connection ---

    public function testGetCreditFormatsBalance(): void
    {
        $this->configure();
        $this->mockHttpPost(['balance' => '152.50']);

        $this->assertSame('152.50 credits', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'something']);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpPost(['balance' => '40.00', 'sub_accounts' => []]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('40.00', $result->message);
        $this->assertSame('40.00', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOnBadCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'invalid sub_account'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionReturnsErrorOnApiErrorWith200(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'something went wrong']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('something went wrong', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
