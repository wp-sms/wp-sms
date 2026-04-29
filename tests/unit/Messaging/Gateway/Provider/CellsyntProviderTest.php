<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\CellsyntProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class CellsyntProviderTest extends AbstractProviderTestCase
{
    private const USERNAME  = 'cellsynt-user';
    private const PASSWORD  = 'cellsynt-pass';
    private const DLR_TOKEN = 'dlr-shared-token';
    private const MO_TOKEN  = 'mo-shared-token';

    protected function createProvider(): AbstractProvider
    {
        return new CellsyntProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'cellsynt' => [
                'shared' => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge([
                        'originator'      => 'WSMS',
                        'originator_type' => 'alpha',
                    ], $channelOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '46700000001', string $body = 'Hello', array $meta = []): Message
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

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(CellsyntProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('cellsynt', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $this->assertSame('secret', $schema['shared']['callback_token_dlr']['type']);
        $this->assertFalse($schema['shared']['callback_token_dlr']['required']);
        $this->assertSame('secret', $schema['shared']['callback_token_mo']['type']);
        $this->assertFalse($schema['shared']['callback_token_mo']['required']);

        $sms = $schema['channels']['sms'];
        $this->assertSame('string', $sms['originator']['type']);
        $this->assertTrue($sms['originator']['required']);
        $this->assertSame('select', $sms['originator_type']['type']);
        $this->assertSame(
            ['alpha', 'numeric', 'shortcode'],
            array_column($sms['originator_type']['options'], 'value'),
        );
        $this->assertSame('boolean', $sms['flash_sms']['type']);
        $this->assertFalse($sms['flash_sms']['default']);
        $this->assertSame('number', $sms['allow_concat']['type']);
        $this->assertSame(6, $sms['allow_concat']['default']);
    }

    public function testFeaturesAdvertiseDlrUnicodeFlashAndIncoming(): void
    {
        $features = $this->createProvider()->getFeatures();
        $this->assertTrue($features['delivery_receipt']);
        $this->assertTrue($features['incoming']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['flash_sms']);
        $this->assertTrue($features['test_connection']);
    }

    // --- Send: happy path ---

    public function testSendSuccessParsesOkPrefixedTrackingId(): void
    {
        $this->configure();
        $this->mockHttpPost('OK: 1234567890abcdef');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('1234567890abcdef', $result->providerId);
    }

    public function testSendStripsCommaSeparatedTrackingIdsKeepsFirst(): void
    {
        $this->configure();
        $this->mockHttpPost('OK: id1,id2,id3');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('id1', $result->providerId);
    }

    public function testSendPostsFormUrlencodedToCanonicalEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage());

        // Pin the canonical endpoint — v7 shipped /sms.php/SendSMS which Cellsynt
        // tolerated but the docs and SDKs all use bare /sms.php.
        $this->assertSame('https://se-1.cellsynt.net/sms.php', $GLOBALS['_test_wp_remote_post_last_url']);
        $this->assertStringNotContainsString('/SendSMS', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);
    }

    public function testSendBodyIncludesCredsAndDefaults(): void
    {
        $this->configure();
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage('46700000001', 'Hello'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(self::USERNAME, $body['username']);
        $this->assertSame(self::PASSWORD, $body['password']);
        $this->assertSame('46700000001', $body['destination']);
        $this->assertSame('WSMS', $body['originator']);
        $this->assertSame('alpha', $body['originatortype']);
        $this->assertSame('Hello', $body['text']);
        $this->assertSame('text', $body['type']);
        $this->assertSame('UTF-8', $body['charset']);
        $this->assertSame(6, $body['allowconcat']);
    }

    public function testSendUsesConfiguredOriginatorType(): void
    {
        $this->configure([], ['originator' => '46700000000', 'originator_type' => 'numeric']);
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage());

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('numeric', $body['originatortype']);
        $this->assertSame('46700000000', $body['originator']);
    }

    public function testSendUsesConfiguredAllowConcat(): void
    {
        $this->configure([], ['allow_concat' => 3]);
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(3, $GLOBALS['_test_wp_remote_post_last_args']['body']['allowconcat']);
    }

    // --- Unicode auto-detection ---

    public function testSendAutoDetectsUnicodeWhenBodyHasNonAsciiCharacters(): void
    {
        $this->configure();
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage('46700000001', 'Hej 你好'));

        $this->assertSame('unicode', $GLOBALS['_test_wp_remote_post_last_args']['body']['type']);
    }

    public function testSendUsesTextTypeForPureAsciiBody(): void
    {
        $this->configure();
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage('46700000001', 'Hello world'));

        $this->assertSame('text', $GLOBALS['_test_wp_remote_post_last_args']['body']['type']);
    }

    // --- Flash & meta overrides ---

    public function testFlashChannelConfigSetsTypeToFlash(): void
    {
        $this->configure([], ['flash_sms' => true]);
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('flash', $GLOBALS['_test_wp_remote_post_last_args']['body']['type']);
    }

    public function testMetaFlashOverrideEnablesFlash(): void
    {
        $this->configure([], ['flash_sms' => false]);
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage('46700000001', 'Hi', ['flash' => true]));

        $this->assertSame('flash', $GLOBALS['_test_wp_remote_post_last_args']['body']['type']);
    }

    public function testMetaFlashOverrideCanDisableChannelDefault(): void
    {
        $this->configure([], ['flash_sms' => true]);
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage('46700000001', 'Hi', ['flash' => false]));

        $this->assertSame('text', $GLOBALS['_test_wp_remote_post_last_args']['body']['type']);
    }

    public function testMetaAllowConcatOverridesAndClampsToValidRange(): void
    {
        $this->configure([], ['allow_concat' => 6]);
        $this->mockHttpPost('OK: x');

        $p = $this->createProvider();

        $p->send($this->createMessage('46700000001', 'Hi', ['allow_concat' => 2]));
        $this->assertSame(2, $GLOBALS['_test_wp_remote_post_last_args']['body']['allowconcat']);

        $p->send($this->createMessage('46700000001', 'Hi', ['allow_concat' => 99]));
        $this->assertSame(6, $GLOBALS['_test_wp_remote_post_last_args']['body']['allowconcat']);

        $p->send($this->createMessage('46700000001', 'Hi', ['allow_concat' => 0]));
        $this->assertSame(1, $GLOBALS['_test_wp_remote_post_last_args']['body']['allowconcat']);
    }

    public function testMetaTypeOverrideIsHonoured(): void
    {
        $this->configure();
        $this->mockHttpPost('OK: x');

        $this->createProvider()->send($this->createMessage('46700000001', 'Hi', ['type' => 'binary']));

        $this->assertSame('binary', $GLOBALS['_test_wp_remote_post_last_args']['body']['type']);
    }

    // --- Send: failure paths ---

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsWhenOriginatorMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'cellsynt' => [
                'shared'   => ['username' => self::USERNAME, 'password' => self::PASSWORD],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender Number', $result->error);
    }

    public function testSendParsesErrorPrefixedResponse(): void
    {
        $this->configure();
        $this->mockHttpPost('Error: Invalid destination');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid destination', $result->error);
    }

    public function testSendParsesUnprefixedErrorResponse(): void
    {
        $this->configure();
        $this->mockHttpPost('Auth failed');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Auth failed', $result->error);
    }

    public function testSendReturnsAuthErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpPost('', 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
        $this->assertStringContainsString('Cellsynt', $result->error);
    }

    public function testSendReturnsFailedOnEmptyBody(): void
    {
        $this->configure();
        $this->mockHttpPost('', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Empty response', $result->error);
    }

    // --- testConnection ---

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionDetectsValidCredentialsViaParameterError(): void
    {
        $this->configure();
        $this->mockHttpPost('Error: missing destination number');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Connected', $result->message);
    }

    public function testTestConnectionDetectsAuthFailureFromBody(): void
    {
        $this->configure();
        $this->mockHttpPost('Error: Authentication failed');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionDetectsAuthFailureFrom401(): void
    {
        $this->configure();
        $this->mockHttpPost('', 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionSendsMinimalProbeWithEmptyDestination(): void
    {
        $this->configure();
        $this->mockHttpPost('Error: missing destination number');

        $this->createProvider()->testConnection();

        // Empty destination guarantees Cellsynt rejects the request before sending,
        // so the probe never costs a credit.
        $this->assertSame('', $GLOBALS['_test_wp_remote_post_last_args']['body']['destination']);
    }

    // --- Webhook URL-token validation ---

    public function testValidateStatusCallbackRejectsWhenTokenNotConfigured(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure(['callback_token_dlr' => self::DLR_TOKEN]);
        $request = $this->buildRequest([]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMismatchedToken(): void
    {
        $this->configure(['callback_token_dlr' => self::DLR_TOKEN]);
        $request = $this->buildRequest(['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure(['callback_token_dlr' => self::DLR_TOKEN]);
        $request = $this->buildRequest(['token' => self::DLR_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateInboundCallbackUsesSeparateTokenFromDlr(): void
    {
        $this->configure([
            'callback_token_dlr' => self::DLR_TOKEN,
            'callback_token_mo'  => self::MO_TOKEN,
        ]);

        $p = $this->createProvider();

        // DLR token must NOT validate inbound, and vice versa.
        $this->assertFalse($p->validateInboundCallback($this->buildRequest(['token' => self::DLR_TOKEN])));
        $this->assertFalse($p->validateStatusCallback($this->buildRequest(['token' => self::MO_TOKEN])));

        // Each token validates its own channel.
        $this->assertTrue($p->validateInboundCallback($this->buildRequest(['token' => self::MO_TOKEN])));
        $this->assertTrue($p->validateStatusCallback($this->buildRequest(['token' => self::DLR_TOKEN])));
    }

    public function testValidateInboundCallbackRejectsMissingToken(): void
    {
        $this->configure(['callback_token_mo' => self::MO_TOKEN]);

        $this->assertFalse($this->createProvider()->validateInboundCallback($this->buildRequest([])));
    }

    public function testValidateInboundCallbackAcceptsMatchingToken(): void
    {
        $this->configure(['callback_token_mo' => self::MO_TOKEN]);

        $this->assertTrue(
            $this->createProvider()->validateInboundCallback($this->buildRequest(['token' => self::MO_TOKEN])),
        );
    }

    // --- Webhook parsing ---

    public function testParseStatusCallbackMapsDeliveredStatus(): void
    {
        $request = $this->buildRequest(['trackingid' => 'abc123', 'status' => 'delivered']);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('abc123', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testParseStatusCallbackMapsFailedStatusWithPermanentFlag(): void
    {
        $request = $this->buildRequest(['trackingid' => 'abc123', 'status' => 'failed']);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertTrue($updates[0]->permanent);
        $this->assertSame('failed', $updates[0]->errorCode);
    }

    public function testParseStatusCallbackDegradesUnknownStatusToSent(): void
    {
        $request = $this->buildRequest(['trackingid' => 'abc123', 'status' => 'somethingweird']);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('sent', $updates[0]->status);
    }

    public function testParseStatusCallbackEmptyForMissingTrackingId(): void
    {
        $request = $this->buildRequest(['status' => 'delivered']);

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackEmptyForMissingStatus(): void
    {
        $request = $this->buildRequest(['trackingid' => 'abc123']);

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseInboundCallbackBuildsInboundMessage(): void
    {
        $request = $this->buildRequest([
            'originator'  => '46700000001',
            'destination' => '46711111111',
            'text'        => 'STOP',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('46700000001', $messages[0]->from);
        $this->assertSame('46711111111', $messages[0]->to);
        $this->assertSame('STOP', $messages[0]->body);
    }

    public function testParseInboundCallbackAcceptsAliasFieldNames(): void
    {
        $request = $this->buildRequest([
            'from'    => '46700000001',
            'to'      => '46711111111',
            'message' => 'Hi',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('46700000001', $messages[0]->from);
        $this->assertSame('46711111111', $messages[0]->to);
        $this->assertSame('Hi', $messages[0]->body);
    }

    public function testParseInboundCallbackEmptyForMissingFrom(): void
    {
        $request = $this->buildRequest(['text' => 'orphan']);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testGetStatusCallbackUrlReturnsRestRoute(): void
    {
        $url = $this->createProvider()->getStatusCallbackUrl();
        $this->assertStringContainsString('/callbacks/cellsynt/status', $url);
    }

    public function testGetInboundCallbackUrlReturnsRestRoute(): void
    {
        $url = $this->createProvider()->getInboundCallbackUrl();
        $this->assertStringContainsString('/callbacks/cellsynt/inbound', $url);
    }

    // --- Credit ---

    public function testGetCreditAlwaysReturnsNull(): void
    {
        $this->configure();
        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Helpers ---

    private function buildRequest(array $params): \WP_REST_Request
    {
        return new class($params) extends \WP_REST_Request {
            public function __construct(array $params) {
                parent::__construct('POST', '/x');
                foreach ($params as $k => $v) $this->set_param($k, $v);
            }
            public function get_method(): string {
                return 'POST';
            }
        };
    }
}
