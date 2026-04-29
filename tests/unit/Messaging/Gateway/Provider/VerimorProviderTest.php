<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\VerimorProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class VerimorProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = '908501234567';
    private const PASSWORD = 'verimor-test-password';
    private const SOURCE   = 'BASLIGIM';
    private const TOKEN    = 'webhook-secret-token';

    protected function createProvider(): AbstractProvider
    {
        return new VerimorProvider();
    }

    protected function setUp(): void
    {
        parent::setUp();
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_get_last_url'],
        );
    }

    private function configure(array $sharedOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'verimor' => [
                'shared' => array_merge([
                    'username'       => self::USERNAME,
                    'password'       => self::PASSWORD,
                    'callback_token' => self::TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => ['source_addr' => self::SOURCE],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '905311234567', string $body = 'Merhaba', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    private function mockHttpPost(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    /** @param string|callable $body */
    private function mockHttpGet($body, int $statusCode = 200): void
    {
        if (is_callable($body)) {
            $GLOBALS['_test_wp_remote_get'] = $body;
            return;
        }
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    private function captureGetUrl(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use ($body, $statusCode) {
            $GLOBALS['_test_wp_remote_get_last_url']  = $url;
            $GLOBALS['_test_wp_remote_get_last_args'] = $args;
            return ['body' => $body, 'response' => ['code' => $statusCode]];
        };
    }

    // --- Identity / schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('verimor', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(VerimorProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);

        $this->assertArrayHasKey('is_commercial', $schema['shared']);
        $this->assertSame('boolean', $schema['shared']['is_commercial']['type']);

        $this->assertArrayHasKey('iys_recipient_type', $schema['shared']);
        $this->assertSame('select', $schema['shared']['iys_recipient_type']['type']);
        $this->assertSame(
            ['BIREYSEL', 'TACIR'],
            array_column($schema['shared']['iys_recipient_type']['options'], 'value'),
        );

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['callback_token']['type']);

        $this->assertArrayHasKey('source_addr', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['source_addr']['required']);
        $this->assertTrue($schema['channels']['sms']['source_addr']['dynamic']);
    }

    public function testIsConfiguredRequiresAllRequiredFields(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());

        $GLOBALS['_test_options']['wsms_gateway_configs']['verimor']['shared']['password'] = '';
        $this->assertFalse($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendSuccessReturnsCampaignIdFromPlainTextBody(): void
    {
        $this->configure();
        $this->mockHttpPost('20212', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('20212', $result->providerId);
        $this->assertSame('20212', $result->meta['verimor_campaign_id']);
    }

    public function testSendPostsJsonBodyWithExpectedFields(): void
    {
        $this->configure();
        $this->mockHttpPost('20212', 200);

        $this->createProvider()->send($this->createMessage('905311234567', 'Merhaba dünya'));

        $this->assertSame(
            'https://sms.verimor.com.tr/v2/send.json',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::USERNAME, $body['username']);
        $this->assertSame(self::PASSWORD, $body['password']);
        $this->assertSame(self::SOURCE, $body['source_addr']);
        $this->assertSame([
            ['msg' => 'Merhaba dünya', 'dest' => '905311234567'],
        ], $body['messages']);

        // Auto-detect datacoding — must NOT be set.
        $this->assertArrayNotHasKey('datacoding', $body);
        // Default config: not commercial, IYS fields omitted.
        $this->assertArrayNotHasKey('is_commercial', $body);
        $this->assertArrayNotHasKey('iys_recipient_type', $body);
    }

    public function testSendIncludesIysFieldsWhenCommercialEnabled(): void
    {
        $this->configure([
            'is_commercial'      => true,
            'iys_recipient_type' => 'TACIR',
        ]);
        $this->mockHttpPost('20213', 200);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertTrue($body['is_commercial']);
        $this->assertSame('TACIR', $body['iys_recipient_type']);
    }

    public function testSendDefaultsIysRecipientTypeWhenCommercialButNotSet(): void
    {
        $this->configure(['is_commercial' => true, 'iys_recipient_type' => '']);
        $this->mockHttpPost('20214', 200);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('BIREYSEL', $body['iys_recipient_type']);
    }

    public function testSendMapsKnownErrorCodeToHumanMessage(): void
    {
        $this->configure();
        $this->mockHttpPost('INSUFFICIENT_CREDITS', 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient credits', $result->error);
        $this->assertSame('INSUFFICIENT_CREDITS', $result->meta['verimor_error_code']);
        $this->assertSame(400, $result->meta['verimor_http_code']);
    }

    public function testSendPassesThroughUnknownErrorCode(): void
    {
        $this->configure();
        $this->mockHttpPost('SOMETHING_NEW', 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('SOMETHING_NEW', $result->error);
    }

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsWhenSourceAddrMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'verimor' => [
                'shared'   => ['username' => self::USERNAME, 'password' => self::PASSWORD],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender Header', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditParsesPlainIntegerBody(): void
    {
        $this->configure();
        $this->mockHttpGet('4532', 200);

        $this->assertSame('4532', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnNonNumericBody(): void
    {
        $this->configure();
        $this->mockHttpGet('Geçersiz kullanıcı adı/şifre', 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditQueriesBalanceEndpointWithCredentials(): void
    {
        $this->configure();
        $this->captureGetUrl('123', 200);

        $this->createProvider()->getCredit();

        $url = $GLOBALS['_test_wp_remote_get_last_url'];
        $this->assertStringStartsWith('https://sms.verimor.com.tr/v2/balance?', $url);
        $this->assertStringContainsString('username=' . self::USERNAME, $url);
        $this->assertStringContainsString('password=' . self::PASSWORD, $url);
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet('500', 200);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500', $result->message);
        $this->assertSame('500', $result->details['credit']);
    }

    public function testTestConnectionMaps401ToInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpGet('Geçersiz kullanıcı adı/şifre', 401);

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

    public function testGetStatusCallbackUrlAppendsToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();
        $this->assertStringContainsString('callbacks/verimor/status', $url);
        $this->assertStringContainsString('token=' . self::TOKEN, $url);
    }

    public function testGetStatusCallbackUrlOmitsTokenWhenUnset(): void
    {
        $this->configure(['callback_token' => '']);
        $url = $this->createProvider()->getStatusCallbackUrl();
        $this->assertStringContainsString('callbacks/verimor/status', $url);
        $this->assertStringNotContainsString('token=', $url);
    }

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildJsonRequest('POST', '/x', '[]', ['token' => self::TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = $this->buildJsonRequest('POST', '/x', '[]', ['token' => 'nope']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenTokenUnset(): void
    {
        // Operator hasn't configured a callback_token — reject by default.
        $this->configure(['callback_token' => '']);
        $request = $this->buildJsonRequest('POST', '/x', '[]', ['token' => self::TOKEN]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackParsesDeliveredArray(): void
    {
        $this->configure();
        $payload = [[
            'type'        => 'outbound',
            'campaign_id' => 20121,
            'message_id'  => '13582302',
            'dest'        => '905319876543',
            'status'      => 'DELIVERED',
            'gsm_error'   => '0',
        ]];
        $request = $this->buildJsonRequest('POST', '/x', json_encode($payload));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('13582302', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertNull($updates[0]->errorCode);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testParseStatusCallbackHandlesBatchOfMixedStatuses(): void
    {
        $this->configure();
        $payload = [
            ['type' => 'outbound', 'message_id' => 'm1', 'status' => 'SENT'],
            ['type' => 'outbound', 'message_id' => 'm2', 'status' => 'WAITING'],
            ['type' => 'outbound', 'message_id' => 'm3', 'status' => 'EXPIRED'],
        ];
        $request = $this->buildJsonRequest('POST', '/x', json_encode($payload));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(3, $updates);
        $this->assertSame(['delivered', 'sent', 'failed'], array_column($updates, 'status'));
    }

    public function testParseStatusCallbackMarksPermanentForBlacklistedAndIysBlocked(): void
    {
        $this->configure();
        $payload = [
            ['type' => 'outbound', 'message_id' => 'b1', 'status' => 'BLACKLISTED_DESTINATION_ADDRESS'],
            ['type' => 'outbound', 'message_id' => 'b2', 'status' => 'NOT_ALLOWED_BY_IYS'],
            ['type' => 'outbound', 'message_id' => 'b3', 'status' => 'INVALID_DESTINATION_ADDRESS'],
            ['type' => 'outbound', 'message_id' => 'b4', 'status' => 'EXPIRED'],
        ];
        $request = $this->buildJsonRequest('POST', '/x', json_encode($payload));

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertTrue($updates[0]->permanent);
        $this->assertTrue($updates[1]->permanent);
        $this->assertTrue($updates[2]->permanent);
        $this->assertFalse($updates[3]->permanent);  // EXPIRED is failed but not permanent
        $this->assertSame('BLACKLISTED_DESTINATION_ADDRESS', $updates[0]->errorCode);
    }

    public function testParseStatusCallbackSkipsInboundEntries(): void
    {
        $this->configure();
        $payload = [
            ['type' => 'inbound', 'message_id' => 'i1', 'source_addr' => '905319876543', 'content' => 'STOP'],
        ];
        $request = $this->buildJsonRequest('POST', '/x', json_encode($payload));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackEmptyForMalformedPayload(): void
    {
        $this->configure();
        $request = $this->buildJsonRequest('POST', '/x', '');

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackUsesSameTokenScheme(): void
    {
        $this->configure();
        $good = $this->buildJsonRequest('POST', '/x', '[]', ['token' => self::TOKEN]);
        $bad  = $this->buildJsonRequest('POST', '/x', '[]', ['token' => 'no']);

        $p = $this->createProvider();
        $this->assertTrue($p->validateInboundCallback($good));
        $this->assertFalse($p->validateInboundCallback($bad));
    }

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $this->configure();
        $payload = [[
            'type'             => 'inbound',
            'message_id'       => 1234,
            'created_at'       => '2025-01-01 09:00:00',
            'network'          => 'TURKCELL',
            'source_addr'      => '905319876543',
            'destination_addr' => '908501234567',
            'keyword'          => '',
            'content'          => 'verimor deneme',
            'received_at'      => '2025-01-01 09:00:00',
        ]];
        $request = $this->buildJsonRequest('POST', '/x', json_encode($payload));

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('905319876543', $msg->from);
        $this->assertSame('908501234567', $msg->to);
        $this->assertSame('verimor deneme', $msg->body);
        $this->assertSame('1234', $msg->providerId);
        $this->assertSame('TURKCELL', $msg->meta['network']);
        $this->assertSame('2025-01-01 09:00:00', $msg->meta['received_at']);
        $this->assertArrayNotHasKey('keyword', $msg->meta);  // empty filtered out
    }

    public function testParseInboundCallbackSkipsOutboundEntries(): void
    {
        $this->configure();
        $payload = [
            ['type' => 'outbound', 'message_id' => 'o1', 'status' => 'DELIVERED'],
        ];
        $request = $this->buildJsonRequest('POST', '/x', json_encode($payload));

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testParseInboundCallbackSkipsEntriesWithoutSourceAddr(): void
    {
        $this->configure();
        $payload = [
            ['type' => 'inbound', 'destination_addr' => '908501234567', 'content' => 'no sender'],
        ];
        $request = $this->buildJsonRequest('POST', '/x', json_encode($payload));

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorMatchesBlacklistedAndIys(): void
    {
        $p = $this->createProvider();
        $this->assertTrue($p->isOptOutError(
            DeliveryResult::failed('blacklisted', ['verimor_error_code' => 'BLACKLISTED_DESTINATION_ADDRESS']),
        ));
        $this->assertTrue($p->isOptOutError(
            DeliveryResult::failed('iys', ['verimor_error_code' => 'NOT_ALLOWED_BY_IYS']),
        ));
        $this->assertFalse($p->isOptOutError(
            DeliveryResult::failed('credits', ['verimor_error_code' => 'INSUFFICIENT_CREDITS']),
        ));
        $this->assertFalse($p->isOptOutError(DeliveryResult::failed('plain')));
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsForSourceAddrFetchesHeaders(): void
    {
        $this->captureGetUrl('["BASLIGIM","Verimor TLK"]', 200);

        $config = [
            'shared'   => ['username' => self::USERNAME, 'password' => self::PASSWORD],
            'channels' => [],
        ];

        $options = $this->createProvider()->getConfigOptions('source_addr', 'sms', $config);

        $this->assertSame([
            ['value' => 'BASLIGIM',    'label' => 'BASLIGIM'],
            ['value' => 'Verimor TLK', 'label' => 'Verimor TLK'],
        ], $options);

        $this->assertStringStartsWith('https://sms.verimor.com.tr/v2/headers?', $GLOBALS['_test_wp_remote_get_last_url']);
    }

    public function testGetConfigOptionsThrowsOn401(): void
    {
        $this->mockHttpGet('Geçersiz kullanıcı adı/şifre', 401);

        $this->expectException(\RuntimeException::class);

        $this->createProvider()->getConfigOptions('source_addr', 'sms', [
            'shared'   => ['username' => self::USERNAME, 'password' => 'bad'],
            'channels' => [],
        ]);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('unknown', 'sms', []));
        $this->assertSame([], $this->createProvider()->getConfigOptions('source_addr', 'whatsapp', []));
    }

    public function testGetConfigOptionsReturnsEmptyWhenCredentialsMissing(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('source_addr', 'sms', [
            'shared'   => [],
            'channels' => [],
        ]));
    }

    // --- Helpers ---

    /**
     * Build a WP_REST_Request with a JSON body. Verimor callbacks are JSON arrays,
     * so we set the body via set_body() rather than per-param.
     *
     * @param array<string,string> $queryArgs query-string params (e.g. ['token' => 'abc'])
     */
    private function buildJsonRequest(string $method, string $route, string $body, array $queryArgs = []): \WP_REST_Request
    {
        return new class($method, $route, $body, $queryArgs) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, string $body, array $queryArgs) {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                $this->set_body($body);
                foreach ($queryArgs as $k => $v) {
                    $this->set_param($k, $v);
                }
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }
}
