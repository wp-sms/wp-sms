<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmssolutionsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmssolutionsProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'eziapi-test-key';
    private const FROM    = 'MyCompany';

    protected function createProvider(): AbstractProvider
    {
        return new SmssolutionsProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsChannelOverrides = ['from' => self::FROM]): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smssolutions' => [
                'shared'   => array_merge([
                    'api_key' => self::API_KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => $smsChannelOverrides,
                ],
            ],
        ];
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

    private function expectedToken(string $apiKey = self::API_KEY): string
    {
        return hash_hmac('sha256', 'smssolutions-callback', $apiKey);
    }

    private function createMessage(string $recipient = '+61400000001', string $body = 'Hello AU'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smssolutions', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmssolutionsProvider::TESTED);
    }

    public function testConfigSchemaHasApiKeyAndOptionalFrom(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayNotHasKey('api_secret', $schema['shared']);
        $this->assertArrayNotHasKey('webhook_secret', $schema['shared']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertEmpty($schema['channels']['sms']['from']['required'] ?? false);
    }

    // --- Send ---

    public function testSendsSmsViaV3SmsEndpointWithFormBody(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'id'           => 4321,
            'direction'    => 'mt',
            'msisdn'       => 61400000001,
            'units'        => 1,
            'encoding'     => 'gsm',
            'status'       => 'queued',
            'message_id'   => 100,
        ]);

        $result = $this->createProvider()->send($this->createMessage('+61400000001', 'Hi mate'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('4321', $result->providerId);

        $this->assertSame(
            'https://eziapi.com/v3/sms',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['key']);
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);
        $this->assertSame('application/json', $args['headers']['Accept']);

        // Body must be a form-encoded array (string keys), not JSON.
        $this->assertIsArray($args['body']);
        $this->assertSame('Hi mate', $args['body']['content']);
        $this->assertSame('61400000001', $args['body']['recipient']);
        $this->assertSame(self::FROM, $args['body']['mask']);
        $this->assertStringContainsString('callbacks/smssolutions/status', $args['body']['callback']);
        $this->assertStringContainsString('token=', $args['body']['callback']);
    }

    public function testSendOmitsMaskWhenFromBlank(): void
    {
        $this->configure(smsChannelOverrides: []);
        $this->mockHttpPost(['id' => 1, 'status' => 'queued']);

        $this->createProvider()->send($this->createMessage());

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertArrayNotHasKey('mask', $body);
    }

    public function testSendUsesTopLevelIdNotMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'id'         => 9876,
            'message_id' => 12,
            'status'     => 'queued',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertSame('9876', $result->providerId);
    }

    public function testSendOnInvalidApiKey400(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'Invalid API key', 'var' => 'key'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendOn401ReturnsInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendBubblesUpProviderError422(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'error' => 'Recipient invalid',
            'var'   => 'recipient',
        ], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Recipient invalid', $result->error);
        $this->assertSame('recipient', $result->meta['eziapi_var']);
    }

    public function testSendRejectsNonSmsChannel(): void
    {
        $this->configure();
        $message = new Message('whatsapp', '+61400000001', 'hi', null, []);

        $result = $this->createProvider()->send($message);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('whatsapp', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configure();
        $capturedUrl = null;
        $capturedArgs = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$capturedUrl, &$capturedArgs) {
            $capturedUrl = $url;
            $capturedArgs = $args;
            return [
                'body'     => json_encode(['balance' => 12.5, 'status_callback_url' => '']),
                'response' => ['code' => 200],
            ];
        };

        $this->assertSame('12.5', $this->createProvider()->getCredit());
        $this->assertSame('https://eziapi.com/v3/settings', $capturedUrl);
        $this->assertSame(self::API_KEY, $capturedArgs['headers']['key']);
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'oops'], 500);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => 25]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('25', $result->message);
        $this->assertStringContainsString('credits', $result->message);
        $this->assertSame('25', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'Invalid API key'], 401);

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

    public function testTestConnectionReportsNetworkError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'connect: no route');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach', $result->message);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => $this->expectedToken()]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMismatchedToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => 'totally-wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest([]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $request = $this->buildRequest(['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    /** @dataProvider statusMappingProvider */
    public function testParseStatusCallbackMapsStatuses(string $rawStatus, string $expected, bool $permanent): void
    {
        $request = $this->buildRequest([
            'id'        => 'msg-' . $rawStatus,
            'status'    => $rawStatus,
            'timestamp' => 1715000000,
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('msg-' . $rawStatus, $updates[0]->providerId);
        $this->assertSame($expected, $updates[0]->status, "wrong status for {$rawStatus}");
        $this->assertSame($permanent, $updates[0]->permanent, "wrong permanent for {$rawStatus}");
    }

    public static function statusMappingProvider(): array
    {
        return [
            'queued'              => ['queued', 'queued', false],
            'sent'                => ['sent', 'sent', false],
            'delivered'           => ['delivered', 'delivered', false],
            'bounced'             => ['bounced', 'failed', true],
            'failed'              => ['failed', 'failed', true],
            'insufficient_credit' => ['insufficient_credit', 'failed', false],
        ];
    }

    public function testParseStatusCallbackEmptyForMissingId(): void
    {
        $request = $this->buildRequest(['status' => 'delivered']);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackEmptyForMissingStatus(): void
    {
        $request = $this->buildRequest(['id' => 'msg-1']);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackIncludesErrorMessageOnFailure(): void
    {
        $request = $this->buildRequest(['id' => 'msg-1', 'status' => 'bounced']);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertStringContainsString('bounced', $update->errorMessage);
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => $this->expectedToken()]);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackRejectsMismatchedToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundCallbackReturnsInboundMessage(): void
    {
        $request = $this->buildRequest([
            'id'        => 'in-1',
            'from'      => '+61444444444',
            'message'   => 'hi back',
            'timestamp' => 1715000000,
            'reply_to'  => 'msg-orig',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+61444444444', $messages[0]->from);
        $this->assertSame('', $messages[0]->to);
        $this->assertSame('hi back', $messages[0]->body);
        $this->assertSame('in-1', $messages[0]->providerId);
        $this->assertSame(1715000000, $messages[0]->meta['timestamp']);
        $this->assertSame('msg-orig', $messages[0]->meta['reply_to']);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildRequest(['message' => 'hi']);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorTrueWhenMessageMentionsOptOut(): void
    {
        $p = $this->createProvider();

        $this->assertTrue($p->isOptOutError(DeliveryResult::failed('Recipient has opted out')));
        $this->assertTrue($p->isOptOutError(DeliveryResult::failed('Number is on the opt-out list')));
        $this->assertTrue($p->isOptOutError(DeliveryResult::failed('User unsubscribed')));
    }

    public function testIsOptOutErrorFalseForUnrelatedErrors(): void
    {
        $p = $this->createProvider();
        $this->assertFalse($p->isOptOutError(DeliveryResult::failed('Invalid number')));
        $this->assertFalse($p->isOptOutError(DeliveryResult::failed('')));
    }

    // --- Helpers ---

    private function buildRequest(array $params, array $headers = []): \WP_REST_Request
    {
        return new class('GET', '/x', $params, $headers) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, array $params, array $headers)
            {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) {
                    $this->set_param($k, $v);
                }
                foreach ($headers as $k => $v) {
                    $this->set_header($k, $v);
                }
            }
            public function get_method(): string
            {
                return $this->methodOverride;
            }
        };
    }
}
