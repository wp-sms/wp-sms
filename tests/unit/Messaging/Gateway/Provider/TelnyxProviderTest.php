<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\TelnyxProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class TelnyxProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'KEY01TEST_apikey_value';
    private const SMS_FROM = '+15551234567';
    private const WA_FROM = '+14155550100';
    private const RCS_AGENT = '11111111-2222-3333-4444-555555555555';

    /** Base64 of the keypair generated in setUpKeyPair. */
    private string $publicKeyB64 = '';
    private string $secretKey = '';

    protected function createProvider(): AbstractProvider
    {
        return new TelnyxProvider();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKeyPair();
    }

    private function setUpKeyPair(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $this->publicKeyB64 = base64_encode($publicKey);
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'telnyx' => [
                'shared' => array_merge([
                    'api_key'    => self::API_KEY,
                    'public_key' => $this->publicKeyB64,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms'      => ['from_number' => self::SMS_FROM],
                    'whatsapp' => ['from_number' => self::WA_FROM],
                    'rcs'      => ['agent_id' => self::RCS_AGENT],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+15559876543', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
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

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(TelnyxProvider::TESTED);
    }

    public function testGetIdReturnsTelnyx(): void
    {
        $this->assertSame('telnyx', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsThreeChannels(): void
    {
        $this->assertSame(['sms', 'whatsapp', 'rcs'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetConfigSchemaIncludesSharedAndChannels(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);
        $this->assertArrayHasKey('public_key', $schema['shared']);

        $this->assertArrayHasKey('from_number', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from_number']['dynamic']);
        $this->assertArrayHasKey('messaging_profile_id', $schema['channels']['sms']);

        $this->assertArrayHasKey('from_number', $schema['channels']['whatsapp']);
        $this->assertTrue($schema['channels']['whatsapp']['from_number']['required']);

        $this->assertArrayHasKey('agent_id', $schema['channels']['rcs']);
        $this->assertTrue($schema['channels']['rcs']['agent_id']['required']);
    }

    public function testIsConfiguredForChannelReturnsFalseWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'telnyx' => [
                'shared'   => ['api_key' => ''],
                'channels' => ['sms' => ['from_number' => self::SMS_FROM]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testIsConfiguredForChannelReturnsTrueWhenSmsConfigured(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send: SMS ---

    public function testSendSmsPostsToCorrectEndpointWithBearerAuth(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'msg-uuid-001']]);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'Hi'));

        $this->assertSame(
            'https://api.telnyx.com/v2/messages',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::API_KEY, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::SMS_FROM, $body['from']);
        $this->assertSame('+15559876543', $body['to']);
        $this->assertSame('Hi', $body['text']);
        $this->assertTrue($body['auto_detect']);
        $this->assertStringContainsString('callbacks/telnyx/status', $body['webhook_url']);
        $this->assertArrayNotHasKey('type', $body);
        $this->assertArrayNotHasKey('media_urls', $body);
    }

    public function testSendSmsSetsTypeMmsWhenMediaUrlsPresent(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'msg-mms-1']]);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'See pic', [
            'media_urls' => ['https://example.com/a.jpg'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('MMS', $body['type']);
        $this->assertSame(['https://example.com/a.jpg'], $body['media_urls']);
    }

    public function testSendSmsReturnsDeliveryResultWithProviderIdFromDataId(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'msg-success-id']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-success-id', $result->providerId);
    }

    public function testSendSmsReturnsFailureWith40300CodeAndOptOutDetected(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => [
                ['code' => '40300', 'title' => 'Blocked due to STOP message', 'detail' => 'opt-out'],
            ],
        ], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('40300', $result->meta['telnyx_code']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testSendSmsRequiresFromOrMessagingProfile(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'telnyx' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender Number or Messaging Profile', $result->error);
    }

    public function testSendSmsUsesMessagingProfileIdWhenNoFromNumber(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'telnyx' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['sms' => ['messaging_profile_id' => '40017a55-aaaa-bbbb-cccc-dddddddddddd']],
            ],
        ];
        $this->mockHttpPost(['data' => ['id' => 'msg-profile-1']]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('from', $body);
        $this->assertSame('40017a55-aaaa-bbbb-cccc-dddddddddddd', $body['messaging_profile_id']);
    }

    // --- Send: WhatsApp ---

    public function testSendWhatsappWithTextFreeForm(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'msg-wa-1']]);

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', 'Hi WA'));

        $this->assertSame('https://api.telnyx.com/v2/messages/whatsapp', $GLOBALS['_test_wp_remote_post_last_url']);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::WA_FROM, $body['from']);
        $this->assertSame('+15559876543', $body['to']);
        $this->assertSame('Hi WA', $body['text']);
        $this->assertArrayNotHasKey('template', $body);
    }

    public function testSendWhatsappWithTemplateMode(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'msg-wa-tpl-1']]);

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', '', [
            'template_mode'        => true,
            'provider_template_id' => 'auth_otp_v1',
            'template_language'    => 'en_US',
            'template_variables'   => ['1' => '482916'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('auth_otp_v1', $body['template']['name']);
        $this->assertSame('en_US', $body['template']['language']['code']);
        $this->assertSame('body', $body['template']['components'][0]['type']);
        $this->assertSame('482916', $body['template']['components'][0]['parameters'][0]['text']);
        $this->assertArrayNotHasKey('text', $body);
    }

    public function testSendWhatsappWithCatalogResolvedTemplate(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'msg-wa-cat-1']]);

        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'verify_code_tpl',
            gatewayId: 'telnyx',
            language: 'en_US',
            variableMap: ['otp_code' => '1'],
        );

        $catalog = $this->createMock(TemplateCatalogManager::class);
        $catalog->method('resolveMapping')->with('otp', 'telnyx')->willReturn($mapping);

        $provider = new TelnyxProvider();
        $provider->setCatalogManager($catalog);

        $provider->send($this->createMessage('whatsapp', '+15559876543', '', [
            'template_type'      => 'otp',
            'template_variables' => ['otp_code' => '999111'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('verify_code_tpl', $body['template']['name']);
        $this->assertSame('999111', $body['template']['components'][0]['parameters'][0]['text']);
    }

    // --- Send: RCS ---

    public function testSendRcsPostsAgentMessageWithText(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'msg-rcs-1']]);

        $this->createProvider()->send($this->createMessage('rcs', '+15559876543', 'Hello RCS'));

        $this->assertSame('https://api.telnyx.com/v2/messages/rcs', $GLOBALS['_test_wp_remote_post_last_url']);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::RCS_AGENT, $body['agent_id']);
        $this->assertSame('+15559876543', $body['to']);
        $this->assertSame('Hello RCS', $body['agent_message']['content_message']['text']);
    }

    // --- Credit / Test Connection ---

    public function testGetCreditReturnsFormattedBalance(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'data' => ['balance' => '12.5000', 'currency' => 'USD'],
        ]);

        $this->assertSame('12.5000 USD', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpGet(['errors' => [['code' => '10001']]], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'data' => ['balance' => '5.0000', 'currency' => 'USD'],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5.0000', $result->message);
        $this->assertStringContainsString('USD', $result->message);
        $this->assertSame('5.0000', $result->details['balance']);
    }

    public function testTestConnectionRejects401WithInvalidApiKeyMessage(): void
    {
        $this->configure();
        $this->mockHttpGet(['errors' => [['code' => '10001']]], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    // --- Status callback (Ed25519) ---

    public function testValidateStatusCallbackAcceptsValidEd25519Signature(): void
    {
        $this->configure();
        $body = json_encode(['data' => ['event_type' => 'message.sent', 'payload' => ['id' => 'm-1']]]);
        $timestamp = (string) time();

        $signature = base64_encode(sodium_crypto_sign_detached($timestamp . '|' . $body, $this->secretKey));
        $request = $this->buildSignedRequest($body, $timestamp, $signature);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsTamperedBody(): void
    {
        $this->configure();
        $body = json_encode(['data' => ['event_type' => 'message.sent', 'payload' => ['id' => 'm-1']]]);
        $timestamp = (string) time();

        $signature = base64_encode(sodium_crypto_sign_detached($timestamp . '|' . $body, $this->secretKey));
        // Send a different body than what was signed.
        $tamperedBody = json_encode(['data' => ['event_type' => 'message.sent', 'payload' => ['id' => 'tampered']]]);
        $request = $this->buildSignedRequest($tamperedBody, $timestamp, $signature);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsExpiredTimestamp(): void
    {
        $this->configure();
        $body = json_encode(['data' => ['event_type' => 'message.sent']]);
        $timestamp = (string) (time() - 3600); // 1 hour old, well past the 300s tolerance.

        $signature = base64_encode(sodium_crypto_sign_detached($timestamp . '|' . $body, $this->secretKey));
        $request = $this->buildSignedRequest($body, $timestamp, $signature);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackReturnsFalseWhenPublicKeyMissing(): void
    {
        $this->configure(sharedOverrides: ['public_key' => '']);
        $body = json_encode(['data' => ['event_type' => 'message.sent']]);
        $timestamp = (string) time();

        $signature = base64_encode(sodium_crypto_sign_detached($timestamp . '|' . $body, $this->secretKey));
        $request = $this->buildSignedRequest($body, $timestamp, $signature);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsMessageSentToSent(): void
    {
        $request = $this->buildJsonRequest([
            'data' => [
                'event_type' => 'message.sent',
                'payload'    => ['id' => 'msg-sent-1', 'to' => [['phone_number' => '+1', 'status' => 'sent']]],
            ],
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('msg-sent-1', $updates[0]->providerId);
        $this->assertSame('sent', $updates[0]->status);
    }

    public function testParseStatusCallbackMapsFinalizedDeliveredToDelivered(): void
    {
        $request = $this->buildJsonRequest([
            'data' => [
                'event_type' => 'message.finalized',
                'payload'    => [
                    'id' => 'msg-final-d-1',
                    'to' => [['phone_number' => '+1', 'status' => 'delivered']],
                ],
            ],
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('delivered', $updates[0]->status);
    }

    public function testParseStatusCallbackMapsFinalizedFailedToFailedWithErrorCode(): void
    {
        $request = $this->buildJsonRequest([
            'data' => [
                'event_type' => 'message.finalized',
                'payload'    => [
                    'id'     => 'msg-final-f-1',
                    'to'     => [['phone_number' => '+1', 'status' => 'delivery_failed']],
                    'errors' => [['code' => '40300', 'title' => 'Blocked due to STOP message']],
                ],
            ],
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertSame('40300', $updates[0]->errorCode);
        $this->assertTrue($updates[0]->permanent);
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackBuildsInboundMessage(): void
    {
        $request = $this->buildJsonRequest([
            'data' => [
                'event_type' => 'message.received',
                'payload'    => [
                    'id'    => 'in-1',
                    'from'  => ['phone_number' => '+15559876543'],
                    'to'    => [['phone_number' => '+15551234567']],
                    'text'  => 'STOP',
                    'media' => [['url' => 'https://media.telnyx.com/abc.jpg']],
                ],
            ],
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+15559876543', $msg->from);
        $this->assertSame('+15551234567', $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('in-1', $msg->providerId);
        $this->assertSame(['https://media.telnyx.com/abc.jpg'], $msg->meta['media_urls']);
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsReturnsMessagingPhoneNumbers(): void
    {
        $this->mockHttpGet([
            'data' => [
                ['phone_number' => '+15551234567'],
                ['phone_number' => '+15557654321'],
            ],
        ]);

        $config = ['shared' => ['api_key' => self::API_KEY], 'channels' => []];
        $options = $this->createProvider()->getConfigOptions('from_number', 'sms', $config);

        $this->assertCount(2, $options);
        $this->assertSame('+15551234567', $options[0]['value']);
        $this->assertSame('+15551234567', $options[0]['label']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('unknown', 'sms', []));
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorReturnsTrueFor40300(): void
    {
        $result = DeliveryResult::failed('blocked', ['telnyx_code' => '40300']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorReturnsFalseForOtherCodes(): void
    {
        $result = DeliveryResult::failed('bad number', ['telnyx_code' => '40005']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    // --- SupportsTemplates ---

    public function testRequiresTemplateForChannelReturnsFalse(): void
    {
        $this->assertFalse($this->createProvider()->requiresTemplateForChannel('whatsapp'));
    }

    public function testVariableStyleIsPositional(): void
    {
        $this->assertSame(VariableStyle::Positional, $this->createProvider()->getVariableStyle());
    }

    // --- Helpers ---

    private function buildSignedRequest(string $body, string $timestamp, string $signature): \WP_REST_Request
    {
        // Anonymous subclass overriding get_method() so verifyEd25519Signature paths
        // mirror real-world POST behaviour.
        return new class($body, $timestamp, $signature) extends \WP_REST_Request {
            public function __construct(string $body, string $timestamp, string $signature)
            {
                parent::__construct('POST', '/x');
                $this->set_body($body);
                $this->set_header('telnyx-signature-ed25519', $signature);
                $this->set_header('telnyx-timestamp', $timestamp);
            }

            public function get_method(): string
            {
                return 'POST';
            }
        };
    }

    private function buildJsonRequest(array $payload): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/x');
        $request->set_body(json_encode($payload));
        return $request;
    }
}
