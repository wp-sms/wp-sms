<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\OctopushProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class OctopushProviderTest extends AbstractProviderTestCase
{
    private const API_LOGIN = 'tester@example.com';
    private const API_KEY   = 'octopush-test-api-key';
    private const SENDER    = 'WSMS';
    private const SMS_TICKET = 'sms_5fa275dbf21dc';

    protected function createProvider(): AbstractProvider
    {
        return new OctopushProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'octopush' => [
                'shared' => array_merge([
                    'api_login' => self::API_LOGIN,
                    'api_key'   => self::API_KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge([
                        'from'            => self::SENDER,
                        'type'            => 'sms_low_cost',
                        'default_country' => 'FR',
                    ], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+33612345678', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 201): void
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

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('octopush', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsTrueAfterManualVerification(): void
    {
        $this->assertTrue(OctopushProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_login', $schema['shared']);
        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);

        $sms = $schema['channels']['sms'];
        $this->assertArrayHasKey('from', $sms);
        $this->assertArrayHasKey('type', $sms);
        $this->assertSame('select', $sms['type']['type']);
        $this->assertSame('sms_low_cost', $sms['type']['default']);
        $this->assertArrayHasKey('default_country', $sms);
    }

    public function testIsConfiguredForChannelRequiresAllFields(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));

        $GLOBALS['_test_options']['wsms_gateway_configs']['octopush']['channels']['sms']['from'] = '';
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send ---

    public function testSendPostsCorrectPayloadAndHeaders(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'sms_ticket'           => self::SMS_TICKET,
            'number_of_contacts'   => 1,
            'total_cost'           => 0.045,
            'number_of_sms_needed' => 1,
            'residual_credit'      => 12.5,
        ]);

        $this->createProvider()->send($this->createMessage('+33612345678', 'Hi there'));

        $this->assertSame(
            'https://api.octopush.com/v1/public/sms-campaign/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_LOGIN, $args['headers']['api-login']);
        $this->assertSame(self::API_KEY, $args['headers']['api-key']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::SENDER, $body['sender']);
        $this->assertSame('sms_low_cost', $body['type']);
        $this->assertSame('Hi there', $body['text']);
        $this->assertSame('+33612345678', $body['recipients'][0]['phone_number']);
        $this->assertArrayNotHasKey('purpose', $body);
    }

    public function testSendReturnsQueuedWithSmsTicket(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'sms_ticket'           => self::SMS_TICKET,
            'number_of_contacts'   => 1,
            'total_cost'           => 0.045,
            'number_of_sms_needed' => 1,
            'residual_credit'      => 12.5,
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame(self::SMS_TICKET, $result->providerId);
        $this->assertSame(0.045, $result->cost);
        $this->assertSame(12.5, $result->meta['residual_credit']);
    }

    public function testSendIncludesPurposeWhenSet(): void
    {
        $this->configure(smsOverrides: ['type' => 'sms_premium', 'purpose' => 'alert']);
        $this->mockHttpPost(['sms_ticket' => self::SMS_TICKET]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('sms_premium', $body['type']);
        $this->assertSame('alert', $body['purpose']);
    }

    public function testSendOmitsPurposeWhenEmpty(): void
    {
        $this->configure(smsOverrides: ['purpose' => '']);
        $this->mockHttpPost(['sms_ticket' => self::SMS_TICKET]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('purpose', $body);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['code' => 401, 'message' => 'unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendBubblesUpProviderError(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'code'    => 121,
            'message' => 'Mandatory STOP clause missing',
        ], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Mandatory STOP clause missing', $result->error);
        $this->assertSame(121, $result->meta['octopush_error_code']);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedWhenSenderMissing(): void
    {
        $this->configure(smsOverrides: ['from' => '']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    // --- Credit / Test Connection ---

    public function testGetCreditPassesProductAndCountryParams(): void
    {
        $this->configure(smsOverrides: ['type' => 'sms_premium', 'default_country' => 'CH']);

        $captured = [];
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode(['amount' => 42.5, 'unit' => 'EUR']),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->getCredit();

        $this->assertStringContainsString('product_name=sms_premium', $captured['url']);
        $this->assertStringContainsString('country_code=CH', $captured['url']);
        $this->assertStringStartsWith('https://api.octopush.com/v1/public/wallet/check-balance', $captured['url']);

        $this->assertSame(self::API_LOGIN, $captured['args']['headers']['api-login']);
        $this->assertSame(self::API_KEY, $captured['args']['headers']['api-key']);
    }

    public function testGetCreditReturnsFormattedBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['amount' => 12.5, 'unit' => 'EUR']);

        $this->assertSame('12.5 EUR', $this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['amount' => 5, 'unit' => 'EUR']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5', $result->message);
        $this->assertSame('5', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['code' => 401, 'message' => 'unauthorized'], 401);

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

    public function testStatusCallbackUrlContainsDerivedToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();
        $expectedToken = hash_hmac('sha256', 'octopush-callback', self::API_KEY);

        $this->assertStringContainsString('token=' . $expectedToken, $url);
        $this->assertStringContainsString('callbacks/octopush/status', $url);
    }

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $token = hash_hmac('sha256', 'octopush-callback', self::API_KEY);
        $request = $this->buildRequest('POST', '/x', ['token' => $token]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $request = $this->buildRequest('POST', '/x', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsDelivered(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'channel'       => 'sms',
            'message_id'    => self::SMS_TICKET,
            'number'        => '+33612345678',
            'status'        => 'DELIVERED',
            'delivery_date' => '2026-04-29 11:11:45',
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame(self::SMS_TICKET, $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
        $this->assertFalse($updates[0]->unsubscribe);
    }

    public function testParseStatusCallbackMapsBlacklistedToUnsubscribe(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'message_id' => self::SMS_TICKET,
            'status'     => 'BLACKLISTED_NUMBER',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertTrue($update->unsubscribe);
    }

    public function testParseStatusCallbackMapsBadDestinationToPermanent(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'message_id' => self::SMS_TICKET,
            'status'     => 'BAD_DESTINATION',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertFalse($update->unsubscribe);
    }

    public function testParseStatusCallbackMapsAckToSent(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'message_id' => self::SMS_TICKET,
            'status'     => 'ACK',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('sent', $update->status);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([]));
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'message_id'      => 'sms_5fa275dbf21dc',
            'number'          => '+33612345678',
            'text'            => 'STOP',
            'sim_card_number' => '+33644000001',
            'reception_date'  => '2026-04-29 11:11:45',
        ]));

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+33612345678', $msg->from);
        $this->assertSame('+33644000001', $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('sms_5fa275dbf21dc', $msg->providerId);
        $this->assertSame('2026-04-29 11:11:45', $msg->meta['reception_date']);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode(['text' => 'hi']));
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params = [], array $headers = [], ?string $body = null): \WP_REST_Request
    {
        return new class($method, $route, $params, $headers, $body) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, array $params, array $headers, ?string $body) {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) $this->set_param($k, $v);
                foreach ($headers as $k => $v) $this->set_header($k, $v);
                if ($body !== null) $this->set_body($body);
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }
}
