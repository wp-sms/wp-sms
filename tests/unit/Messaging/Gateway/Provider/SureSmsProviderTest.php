<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SureSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SureSmsProviderTest extends AbstractProviderTestCase
{
    private const API_KEY        = 'sk-suresms-test-12345';
    private const CALLBACK_TOKEN = 'callback-secret-7890';
    private const SENDER         = 'WSMS';
    private const TOKEN          = 'bearer-access-token-abcdef';
    private const SEND_URL       = 'https://api.suresms.com/oauth2/api/Message/SendExtended';
    private const TOKEN_URL      = 'https://api.suresms.com/oauth2/api/Account/Gettoken';
    private const SENDER_URL     = 'https://api.suresms.com/oauth2/api/User/SenderId';

    protected function createProvider(): AbstractProvider
    {
        return new SureSmsProvider();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_test_transients'] = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_transients'],
        );
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'suresms' => [
                'shared' => array_merge([
                    'api_key'        => self::API_KEY,
                    'callback_token' => self::CALLBACK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function seedToken(?string $token = self::TOKEN): void
    {
        $key = 'wsms_suresms_token_' . sha1(self::API_KEY);
        $GLOBALS['_test_transients'][$key] = [
            'value'   => $token,
            'expires' => $token === null ? 0 : time() + 3600,
        ];
    }

    private function tokenTransientExists(): bool
    {
        $key = 'wsms_suresms_token_' . sha1(self::API_KEY);
        return isset($GLOBALS['_test_transients'][$key]);
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

    private function createMessage(string $recipient = '+4520202020', string $body = 'Hej'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function buildRequest(string $method, array $params, ?string $jsonBody = null): \WP_REST_Request
    {
        $request = new \WP_REST_Request($method, '/x');
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        if ($jsonBody !== null) {
            $request->set_body($jsonBody);
        }
        return $request;
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('suresms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SureSmsProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertTrue($schema['shared']['callback_token']['required']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertFalse(!empty($schema['channels']['sms']['from']['required']));
        $this->assertTrue($schema['channels']['sms']['from']['dynamic']);
    }

    public function testIsConfiguredForSmsRequiresApiKeyAndCallbackToken(): void
    {
        $this->configure(['callback_token' => '']);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $this->configure(['api_key' => '']);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testGetCreditReturnsNullEvenWhenConfigured(): void
    {
        $this->configure();
        // Modern SureSMS API has no balance endpoint; provider inherits the
        // null default from AbstractProvider.
        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Send: token issuance ---

    public function testDoSendFetchesTokenWhenCacheEmpty(): void
    {
        $this->configure();
        // Static POST mock returns the token-issuance response. Send-extended
        // call sees the same response (statusCode==0 with `data.token`
        // present); body has no `messageId`-failure surface, so the send is
        // accepted via parseSendResponse.
        $this->mockHttpPost([
            'statusCode' => 0,
            'data'       => ['token' => self::TOKEN, 'expires' => '3600'],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        // Last POST was the send call; first POST was Gettoken (URL captured
        // is overwritten by send). Token now cached.
        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);
        $this->assertTrue($this->tokenTransientExists());
    }

    public function testDoSendUsesCachedTokenAndSendsToSendExtended(): void
    {
        $this->configure();
        $this->seedToken();
        $this->mockHttpPost(['statusCode' => 0]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::TOKEN, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);
    }

    public function testDoSendBodyShape(): void
    {
        $this->configure();
        $this->seedToken();
        $this->mockHttpPost(['statusCode' => 0]);

        $this->createProvider()->send($this->createMessage('+4520202020', 'Hej Verden'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(['+4520202020'], $body['toPhonenumber']);
        $this->assertSame('Hej Verden', $body['messageText']);
        $this->assertSame(self::SENDER, $body['senderID']);
        $this->assertFalse($body['includeOptOutMessage']);
        $this->assertNotEmpty($body['messageID']);
        $this->assertStringContainsString('messageid=' . rawurlencode($body['messageID']), $body['statusWebhook']);
        $this->assertStringContainsString('%26token=' . rawurlencode(self::CALLBACK_TOKEN), $body['statusWebhook']);
    }

    public function testDoSendOmitsSenderIdWhenBlank(): void
    {
        $this->configure([], ['from' => '']);
        $this->seedToken();
        $this->mockHttpPost(['statusCode' => 0]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('senderID', $body);
    }

    public function testDoSendSucceedsWithoutCallbackTokenButOmitsStatusWebhook(): void
    {
        // callback_token is required by the schema; this test bypasses the
        // schema check by calling doSend directly via send() — the only path
        // affected by the missing token is buildStatusWebhookUrl.
        $this->configure(['callback_token' => '']);
        $this->seedToken();
        $this->mockHttpPost(['statusCode' => 0]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('statusWebhook', $body);
    }

    public function testDoSendReturnsQueuedWithGeneratedMessageId(): void
    {
        $this->configure();
        $this->seedToken();
        $this->mockHttpPost(['statusCode' => 0]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertNotEmpty($result->providerId);
        // providerId is the messageID we generated and sent in the body.
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame($body['messageID'], $result->providerId);
    }

    public function testDoSendFailsOnNonZeroStatusCode(): void
    {
        $this->configure();
        $this->seedToken();
        $this->mockHttpPost([
            'statusCode'    => 1001,
            'statusMessage' => 'Sender ID not approved',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Sender ID not approved', $result->error);
        $this->assertSame('1001', $result->meta['suresms_code'] ?? null);
    }

    public function testDoSendFailsOnHttpError(): void
    {
        $this->configure();
        $this->seedToken();
        $this->mockHttpPost(['statusMessage' => 'Internal'], 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Internal', $result->error);
    }

    public function testDoSendDropsCachedTokenOn401(): void
    {
        $this->configure();
        $this->seedToken();
        $this->assertTrue($this->tokenTransientExists());

        // Return 401 for both the send and the subsequent re-auth attempt;
        // the retry path will re-fail via auth (Invalid SureSMS API key).
        $this->mockHttpPost([], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertFalse($this->tokenTransientExists(), 'Cached token should be cleared on 401');
    }

    public function testDoSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testDoSendFailsWhenAuthEndpointReturnsInvalidJson(): void
    {
        $this->configure();
        // Clear transient by NOT seeding; auth is required.
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => 'not valid json',
            'response' => ['code' => 200],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid response', $result->error);
    }

    public function testDoSendFailsWhenAuthReturns401(): void
    {
        $this->configure();
        $this->mockHttpPost([], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid SureSMS API key', $result->error);
    }

    // --- Auth caching ---

    public function testGetAccessTokenCachesUsingApiKeyHash(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'statusCode' => 0,
            'data'       => ['token' => self::TOKEN, 'expires' => '600'],
        ]);

        // First send triggers auth (POST to Gettoken) followed by send POST.
        $this->createProvider()->send($this->createMessage());

        $this->assertTrue($this->tokenTransientExists());
        $expectedKey = 'wsms_suresms_token_' . sha1(self::API_KEY);
        $this->assertSame(self::TOKEN, $GLOBALS['_test_transients'][$expectedKey]['value']);
    }

    // --- Test connection ---

    public function testTestConnectionSucceedsWithValidApiKey(): void
    {
        $this->configure();
        // POST is the auth call (returns token), GET is the sender-id list.
        $this->mockHttpPost([
            'statusCode' => 0,
            'data'       => ['token' => self::TOKEN, 'expires' => '3600'],
        ]);
        $this->mockHttpGet([
            'statusCode' => 0,
            'data'       => [['senderID' => 'WSMS', 'validFromDateTime' => '2025-01-01T00:00:00Z']],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Connected', $result->message);
    }

    public function testTestConnectionReturnsErrorOn401FromAuth(): void
    {
        $this->configure();
        $this->mockHttpPost([], 401);

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

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', [
            'token'     => self::CALLBACK_TOKEN,
            'messageid' => 'wsms-1',
            'Status'    => '1',
        ]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', []);

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
                'Status'    => $raw,
                'Receiver'  => '+4520202020',
            ]);
            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for status {$raw}");
            $this->assertSame($status, $updates[0]->status, "wrong status for {$raw}");
            $this->assertSame($permanent, $updates[0]->permanent, "wrong permanent for {$raw}");
            $this->assertSame('wsms-' . $raw, $updates[0]->providerId);
        }
    }

    public function testParseStatusCallbackSetsErrorCodeOnFailure(): void
    {
        $request = $this->buildRequest('GET', [
            'messageid' => 'wsms-bad',
            'Status'    => '2',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('2', $update->errorCode);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('GET', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => self::CALLBACK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundCallbackJsonBody(): void
    {
        $payload = [
            'fromCountryCode'              => '45',
            'fromPhoneNumber'              => '20202020',
            'toPhoneNumberWithCountryCode' => '+4570707070',
            'receivedDateTime'             => '2026-04-29T12:00:00Z',
            'messageText'                  => 'Hello back',
        ];
        $request = $this->buildRequest('POST', [], json_encode($payload));

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+4520202020', $msg->from);
        $this->assertSame('+4570707070', $msg->to);
        $this->assertSame('Hello back', $msg->body);
        $this->assertNull($msg->optOutType);
        $this->assertSame('2026-04-29T12:00:00Z', $msg->meta['received_at']);
    }

    public function testParseInboundCallbackOptOutSynthesizesStop(): void
    {
        $payload = [
            'fromPhoneNumberWithCountryCodeAndContactName' => '+4520202020',
            'toPhoneNumberWithCountryCode' => '+4570707070',
            'messageText'                  => '',
            'optoutFromGroupId'            => 42,
            'optoutFromGroupName'          => 'Marketing',
        ];
        $request = $this->buildRequest('POST', [], json_encode($payload));

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('STOP', $messages[0]->optOutType);
        $this->assertSame('STOP', $messages[0]->body);
        $this->assertSame('Marketing', $messages[0]->meta['optout_group']);
    }

    public function testParseInboundCallbackBodyMatchingStopMarksOptOut(): void
    {
        $payload = [
            'fromPhoneNumberWithCountryCodeAndContactName' => '+4520202020',
            'toPhoneNumberWithCountryCode' => '+4570707070',
            'messageText'                  => 'STOP',
        ];
        $request = $this->buildRequest('POST', [], json_encode($payload));

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertSame('STOP', $messages[0]->optOutType);
    }

    public function testParseInboundCallbackLegacyGetParams(): void
    {
        $request = $this->buildRequest('GET', [
            'receivedfromphonenumber' => '+4520202020',
            'receivedbyphonenumber'   => '+4570707070',
            'body'                    => 'Hello legacy',
            'receivedutcdatetime'     => '2026-04-29T12:00:00Z',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+4520202020', $messages[0]->from);
        $this->assertSame('Hello legacy', $messages[0]->body);
        $this->assertNull($messages[0]->optOutType);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildRequest('POST', [], json_encode([
            'messageText' => 'orphan',
        ]));

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testParseInboundCallbackStripsContactNameFromCombinedField(): void
    {
        $payload = [
            'fromPhoneNumberWithCountryCodeAndContactName' => '+4520202020 (Jon Doe)',
            'toPhoneNumberWithCountryCode'                 => '+4570707070',
            'messageText'                                  => 'Hi',
        ];
        $request = $this->buildRequest('POST', [], json_encode($payload));

        $messages = $this->createProvider()->parseInboundCallback($request);
        $this->assertSame('+4520202020', $messages[0]->from);
    }

    // --- Dynamic options (sender ID dropdown) ---

    public function testGetConfigOptionsReturnsValidSenderIds(): void
    {
        $this->seedToken();
        $this->mockHttpGet([
            'statusCode' => 0,
            'data'       => [
                ['senderID' => 'WSMS',     'validFromDateTime' => '2025-01-01T00:00:00Z', 'validToDateTime' => null],
                ['senderID' => 'PendIng',  'validFromDateTime' => null,                   'validToDateTime' => null],
                ['senderID' => 'Expired',  'validFromDateTime' => '2024-01-01T00:00:00Z', 'validToDateTime' => '2024-12-31T00:00:00Z'],
                ['senderID' => 'Approved', 'validFromDateTime' => '2025-06-01T00:00:00Z', 'validToDateTime' => null],
            ],
        ]);

        $config = [
            'shared'   => ['api_key' => self::API_KEY, 'callback_token' => self::CALLBACK_TOKEN],
            'channels' => ['sms' => ['from' => '']],
        ];

        $options = $this->createProvider()->getConfigOptions('from', 'sms', $config);

        $this->assertCount(2, $options);
        $values = array_column($options, 'value');
        $this->assertContains('WSMS', $values);
        $this->assertContains('Approved', $values);
        $this->assertNotContains('PendIng', $values);
        $this->assertNotContains('Expired', $values);
    }

    public function testGetConfigOptionsReturnsEmptyForOtherFields(): void
    {
        $config = [
            'shared'   => ['api_key' => self::API_KEY, 'callback_token' => self::CALLBACK_TOKEN],
            'channels' => ['sms' => []],
        ];
        $this->assertSame([], $this->createProvider()->getConfigOptions('api_key', 'shared', $config));
        $this->assertSame([], $this->createProvider()->getConfigOptions('from', 'whatsapp', $config));
    }

    // --- Callback URLs ---

    public function testCallbackUrlsCarryProviderId(): void
    {
        $p = $this->createProvider();
        $this->assertStringContainsString('callbacks/suresms/status', $p->getStatusCallbackUrl());
        $this->assertStringContainsString('callbacks/suresms/inbound', $p->getInboundCallbackUrl());
    }
}
