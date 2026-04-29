<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SpotHitProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SpotHitProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'spothit-test-api-key';
    private const SENDER  = 'WSMS';
    private const MSG_ID  = 'sh_42';

    protected function createProvider(): AbstractProvider
    {
        return new SpotHitProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'spothit' => [
                'shared' => array_merge([
                    'api_key' => self::API_KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge([
                        'from' => self::SENDER,
                        'type' => 'lowcost',
                    ], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+33612345678', string $body = 'Hello'): Message
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

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SpotHitProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('spothit', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $sms = $schema['channels']['sms'];
        $this->assertArrayHasKey('from', $sms);
        $this->assertSame('select', $sms['type']['type']);
        $this->assertSame('lowcost', $sms['type']['default']);
        $this->assertArrayHasKey('campaign_name', $sms);
        $this->assertEmpty($sms['campaign_name']['required'] ?? false);
    }

    public function testIsConfiguredForChannelRequiresCredentialsAndSender(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));

        $GLOBALS['_test_options']['wsms_gateway_configs']['spothit']['channels']['sms']['from'] = '';
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send ---

    public function testSendPostsFormBodyToCorrectEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost(['resultat' => 1, 'id' => self::MSG_ID]);

        $this->createProvider()->send($this->createMessage('+33612345678', 'Hi there'));

        $this->assertSame(
            'https://www.spot-hit.fr/api/envoyer/sms',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertIsArray($args['body']);
        $this->assertSame(self::API_KEY, $args['body']['key']);
        $this->assertSame('+33612345678', $args['body']['destinataires']);
        $this->assertSame('lowcost', $args['body']['type']);
        $this->assertSame('Hi there', $args['body']['message']);
        $this->assertSame(self::SENDER, $args['body']['expediteur']);
        $this->assertSame(1, $args['body']['smslong']);
        $this->assertArrayHasKey('url', $args['body']);
        $this->assertStringContainsString('callbacks/spothit/status', $args['body']['url']);
        $this->assertArrayNotHasKey('nom', $args['body']);
    }

    public function testSendReturnsSentOnResultat1(): void
    {
        $this->configure();
        $this->mockHttpPost(['resultat' => 1, 'id' => self::MSG_ID]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame(self::MSG_ID, $result->providerId);
    }

    public function testSendReturnsFailedOnResultat0(): void
    {
        $this->configure();
        $this->mockHttpPost(['resultat' => 0, 'erreurs' => [1, 4]]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('1,4', $result->error);
        $this->assertSame([1, 4], $result->meta['spothit_errors']);
    }

    public function testSendUsesPremiumWhenConfigured(): void
    {
        $this->configure(smsOverrides: ['type' => 'premium']);
        $this->mockHttpPost(['resultat' => 1, 'id' => self::MSG_ID]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('premium', $GLOBALS['_test_wp_remote_post_last_args']['body']['type']);
    }

    public function testSendIncludesCampaignNameWhenConfigured(): void
    {
        $this->configure(smsOverrides: ['campaign_name' => 'Spring Promo']);
        $this->mockHttpPost(['resultat' => 1, 'id' => self::MSG_ID]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('Spring Promo', $GLOBALS['_test_wp_remote_post_last_args']['body']['nom']);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['resultat' => 0], 401);

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

    public function testSendReturnsFailedWhenSenderMissing(): void
    {
        $this->configure(smsOverrides: ['from' => '']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsBalanceFromApi(): void
    {
        $this->configure();
        $this->mockHttpPost(['resultat' => 1, 'credits' => 12.5]);

        $this->assertSame('12.5 €', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenApiRejects(): void
    {
        $this->configure();
        $this->mockHttpPost(['resultat' => 0, 'erreurs' => [2]]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpPost(['resultat' => 1, 'credits' => 7.25]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('7.25', $result->message);
        $this->assertSame('7.25', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['resultat' => 0], 401);

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

    public function testTestConnectionRejectsResultat0WithHttp200(): void
    {
        // Spot-Hit returns HTTP 200 with resultat=0 on bad keys (per SDK behaviour).
        $this->configure();
        $this->mockHttpPost(['resultat' => 0, 'erreurs' => [3]]);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    // --- Status callback ---

    public function testStatusCallbackUrlContainsDerivedToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();
        $expectedToken = hash_hmac('sha256', 'spothit-callback', self::API_KEY);

        $this->assertStringContainsString('token=' . $expectedToken, $url);
        $this->assertStringContainsString('callbacks/spothit/status', $url);
    }

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $token = hash_hmac('sha256', 'spothit-callback', self::API_KEY);
        $request = $this->buildRequest('GET', '/x', ['token' => $token]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $request = $this->buildRequest('GET', '/x', ['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    /**
     * @dataProvider statutMappingProvider
     */
    public function testParseStatusCallbackMapsStatutToEnum(
        string $statut,
        string $expectedStatus,
        bool $expectedPermanent,
    ): void {
        $request = $this->buildRequest('GET', '/x', [
            'statut'     => $statut,
            'id_message' => self::MSG_ID,
            'numero'     => '+33612345678',
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame(self::MSG_ID, $updates[0]->providerId);
        $this->assertSame($expectedStatus, $updates[0]->status);
        $this->assertSame($expectedPermanent, $updates[0]->permanent);
    }

    public static function statutMappingProvider(): array
    {
        return [
            'pending'     => ['0', 'queued', false],
            'delivered'   => ['1', 'delivered', false],
            'sent'        => ['2', 'sent', false],
            'in-progress' => ['3', 'sent', false],
            'failed'      => ['4', 'failed', true],
            'expired'     => ['5', 'failed', true],
        ];
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('GET', '/x', ['statut' => '1']);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
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
