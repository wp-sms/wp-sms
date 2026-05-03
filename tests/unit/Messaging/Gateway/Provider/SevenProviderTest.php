<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SevenProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SevenProviderTest extends AbstractProviderTestCase
{
    private const API_KEY       = 'sevenio-api-key-1234567890';
    private const WEBHOOK_TOKEN = 'webhook-token-abcdef';
    private const SMS_FROM      = 'WSMS';
    private const RCS_FROM      = 'rcs-agent';
    private const WA_FROM       = '+491701234567';

    protected function createProvider(): AbstractProvider
    {
        return new SevenProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $defaultChannels = [
            'sms'      => ['from' => self::SMS_FROM],
            'rcs'      => ['from' => self::RCS_FROM],
            'whatsapp' => ['from' => self::WA_FROM],
        ];
        foreach ($channelOverrides as $channel => $overrides) {
            $defaultChannels[$channel] = array_merge($defaultChannels[$channel] ?? [], $overrides);
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'seven' => [
                'shared' => array_merge([
                    'api_key'       => self::API_KEY,
                    'webhook_token' => self::WEBHOOK_TOKEN,
                ], $sharedOverrides),
                'channels' => $defaultChannels,
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+491701234567', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    /**
     * Mock seven.io's accepted-dispatch envelope: top-level `success: "100"` + a single message entry.
     */
    private function mockAccepted(string $providerId = 'msg-001', float $totalPrice = 0.0750, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode([
                'success'     => '100',
                'total_price' => $totalPrice,
                'balance'     => 9.99,
                'sms_type'    => 'direct',
                'messages'    => [[
                    'id'        => $providerId,
                    'success'   => true,
                    'recipient' => '+491701234567',
                    'sender'    => self::SMS_FROM,
                    'price'     => $totalPrice,
                ]],
            ]),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockRawPostResponse(array $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($body),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($body),
            'response' => ['code' => $statusCode],
        ];
    }

    private function buildRequest(array $headers = [], ?string $jsonBody = null): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/x');
        foreach ($headers as $k => $v) {
            $request->set_header($k, $v);
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
        $this->assertSame('seven', $p->getId());
        $this->assertSame(['sms', 'rcs', 'whatsapp'], $p->getSupportedChannels());
    }

    public function testNameIsLowercaseSeven(): void
    {
        $this->assertSame('seven', $this->createProvider()->getName());
    }

    public function testTestedFlagIsTrueAfterManualVerification(): void
    {
        $this->assertTrue(SevenProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);
        $this->assertSame('secret', $schema['shared']['webhook_token']['type']);
        $this->assertFalse($schema['shared']['webhook_token']['required']);

        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertArrayHasKey('rcs', $schema['channels']);
        $this->assertArrayHasKey('whatsapp', $schema['channels']);

        $this->assertFalse($schema['channels']['sms']['from']['required']);
        $this->assertTrue($schema['channels']['whatsapp']['from']['required']);
    }

    // --- Send: SMS ---

    public function testSmsSendQueuedReturnsProviderId(): void
    {
        $this->configure();
        $this->mockAccepted('msg-sms-1', 0.075);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-sms-1', $result->providerId);
    }

    public function testSmsSendPostsFormEncodedToSmsEndpoint(): void
    {
        $this->configure();
        $this->mockAccepted();

        $this->createProvider()->send($this->createMessage('sms', '+491701234567', 'Hello'));

        $this->assertSame('https://gateway.seven.io/api/sms', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['X-Api-Key']);
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $form);
        $this->assertSame('+491701234567', $form['to']);
        $this->assertSame('Hello', $form['text']);
        $this->assertSame(self::SMS_FROM, $form['from']);
        $this->assertSame('0', $form['performance_tracking']);
    }

    public function testSmsSendOmitsFromWhenEmpty(): void
    {
        $this->configure([], ['sms' => ['from' => '']]);
        $this->mockAccepted();

        $this->createProvider()->send($this->createMessage());

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertArrayNotHasKey('from', $form);
    }

    public function testSmsSendIncludesFlashFromMeta(): void
    {
        $this->configure();
        $this->mockAccepted();

        $this->createProvider()->send($this->createMessage('sms', '+491701234567', 'Urgent', ['flash' => true]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('1', $form['flash']);
    }

    public function testSmsSendReturnsFailedOnApiSuccessNotAccepted(): void
    {
        $this->configure();
        $this->mockRawPostResponse([
            'success'  => '202',
            'messages' => [[
                'id'         => null,
                'success'    => false,
                'error'      => '202',
                'error_text' => 'Invalid recipient',
                'recipient'  => '+491701234567',
            ]],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid recipient', $result->error);
        $this->assertSame('202', $result->meta['seven_error_code']);
    }

    public function testSmsSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['error' => 'unauth'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    /**
     * seven.io returns HTTP 200 with a bare quoted error code (e.g. "900") on auth failure.
     */
    public function testSmsSendRecognizesBareStringErrorCode(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => '"900"',
            'response' => ['code' => 200],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid seven.io API key', $result->error);
        $this->assertSame('900', $result->meta['seven_error_code']);
    }

    public function testSmsSendTreatsBareStringHundredAsSuccess(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => '"100"',
            'response' => ['code' => 200],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
    }

    public function testSmsSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Send: RCS ---

    public function testRcsSendHitsRcsEndpoint(): void
    {
        $this->configure();
        $this->mockAccepted('rcs-id-1');

        $this->createProvider()->send($this->createMessage('rcs', '+491701234567', 'Hi via RCS'));

        $this->assertSame('https://gateway.seven.io/api/rcs/messages', $GLOBALS['_test_wp_remote_post_last_url']);

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('+491701234567', $form['to']);
        $this->assertSame('Hi via RCS', $form['text']);
        $this->assertSame(self::RCS_FROM, $form['from']);
    }

    // --- Send: WhatsApp ---

    public function testWabaFreetextUsesTypeText(): void
    {
        $this->configure();
        $this->mockAccepted('waba-id-1');

        $this->createProvider()->send($this->createMessage('whatsapp', '+491701234567', 'Hello WA'));

        $this->assertSame('https://gateway.seven.io/api/waba/messages', $GLOBALS['_test_wp_remote_post_last_url']);

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame(self::WA_FROM, $form['from']);
        $this->assertSame('+491701234567', $form['to']);
        $this->assertSame('text', $form['type']);
        $this->assertSame('Hello WA', $form['text']);
    }

    public function testWabaTemplateModeProducesTemplatePayload(): void
    {
        $this->configure();
        $this->mockAccepted('waba-tpl-1');

        $this->createProvider()->send($this->createMessage('whatsapp', '+491701234567', '', [
            'template_mode'        => true,
            'provider_template_id' => 'tpl_auth_001',
            'template_language'    => 'de',
            'template_variables'   => ['1' => '482916'],
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('template', $form['type']);
        $this->assertSame('tpl_auth_001', $form['template']);
        $this->assertSame('de', $form['language']);
        $components = json_decode($form['components'], true);
        $this->assertSame('body', $components[0]['type']);
        $this->assertSame('482916', $components[0]['parameters'][0]['text']);
    }

    public function testWabaMediaUsesAutoDetectedTypeAndCaption(): void
    {
        $this->configure();
        $this->mockAccepted('waba-media-1');

        $this->createProvider()->send($this->createMessage('whatsapp', '+491701234567', 'see attached', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('image', $form['type']);
        $this->assertSame('https://example.com/photo.jpg', $form['url']);
        $this->assertSame('see attached', $form['caption']);
    }

    public function testWabaMediaDetectsVideoExtension(): void
    {
        $this->configure();
        $this->mockAccepted();

        $this->createProvider()->send($this->createMessage('whatsapp', '+491701234567', '', [
            'media_urls' => ['https://example.com/clip.mp4'],
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('video', $form['type']);
    }

    public function testWabaSendFailsWhenSenderMissing(): void
    {
        $this->configure([], ['whatsapp' => ['from' => '']]);

        $result = $this->createProvider()->send($this->createMessage('whatsapp'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('WhatsApp sender not configured', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditFormatsAmountWithCurrency(): void
    {
        $this->configure();
        $this->mockHttpGet(['amount' => 12.345, 'currency' => 'EUR']);

        $this->assertSame('12.35 EUR', $this->createProvider()->getCredit());
    }

    public function testTestConnectionOkOn2xxWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['amount' => 5.0, 'currency' => 'EUR']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5.00', $result->message);
    }

    public function testTestConnectionErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauth'], 401);

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

    /**
     * seven.io returns HTTP 200 with the body "900" when the API key is wrong.
     */
    public function testTestConnectionRecognizesBareStringErrorCode(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => '"900"',
            'response' => ['code' => 200],
        ];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid seven.io API key', $result->message);
    }

    // --- Status callback validation/parsing ---

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['x-wsms-token' => self::WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMismatchedToken(): void
    {
        $this->configure();
        $request = $this->buildRequest(['x-wsms-token' => 'wrong-token']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenTokenMissingFromConfig(): void
    {
        $this->configure(['webhook_token' => '']);
        $request = $this->buildRequest(['x-wsms-token' => 'anything']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackForSmsDlrDelivered(): void
    {
        $request = $this->buildRequest([], json_encode([
            'event_type' => 'dlr',
            'msg_id'     => 'msg-001',
            'status'     => 'DELIVERED',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('msg-001', $update->providerId);
        $this->assertSame('delivered', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testParseStatusCallbackForSmsDlrFailedIsPermanent(): void
    {
        $request = $this->buildRequest([], json_encode([
            'event_type'  => 'dlr',
            'msg_id'      => 'msg-002',
            'status'      => 'FAILED',
            'description' => 'Recipient unreachable',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertStringContainsString('Recipient unreachable', $update->errorMessage);
    }

    public function testParseStatusCallbackForVoiceStatus(): void
    {
        $request = $this->buildRequest([], json_encode([
            'event_type' => 'voice_status',
            'msg_id'     => 'voice-1',
            'status'     => 'DELIVERED',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('voice-1', $update->providerId);
        $this->assertSame('delivered', $update->status);
    }

    public function testParseStatusCallbackForRcsStatus(): void
    {
        $request = $this->buildRequest([], json_encode([
            'event_type' => 'rcs',
            'msg_id'     => 'rcs-1',
            'status'     => 'DELIVERED',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('rcs-1', $update->providerId);
        $this->assertSame('delivered', $update->status);
    }

    public function testParseStatusCallbackEmptyForUnknownEvent(): void
    {
        $request = $this->buildRequest([], json_encode(['event_type' => 'something_else']));
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback validation/parsing ---

    public function testValidateInboundCallbackUsesSameTokenCheck(): void
    {
        $this->configure();
        $ok = $this->buildRequest(['x-wsms-token' => self::WEBHOOK_TOKEN]);
        $bad = $this->buildRequest(['x-wsms-token' => 'nope']);

        $p = $this->createProvider();
        $this->assertTrue($p->validateInboundCallback($ok));
        $this->assertFalse($p->validateInboundCallback($bad));
    }

    public function testParseInboundCallbackForSmsMo(): void
    {
        $request = $this->buildRequest([], json_encode([
            'event_type' => 'sms_mo',
            'id'         => 'mo-1',
            'sender'     => '+491701234567',
            'system'     => 'WSMS',
            'text'       => 'STOP',
            'received_at'=> '2026-04-30T10:00:00Z',
        ]));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('+491701234567', $msg->from);
        $this->assertSame('WSMS', $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('mo-1', $msg->providerId);
    }

    public function testParseInboundCallbackEmptyForMissingPayload(): void
    {
        $request = $this->buildRequest([], json_encode([]));
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
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

    public function testBuildTemplatePayloadOrdersBodyParameters(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'tpl_auth_001',
            gatewayId: 'seven',
            language: 'en',
            variableMap: [],
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, ['2' => 'Bob', '1' => 'Alice']);

        $this->assertSame('template', $payload['type']);
        $this->assertSame('tpl_auth_001', $payload['template']);
        $this->assertSame('en', $payload['language']);

        $components = json_decode($payload['components'], true);
        $this->assertSame('Alice', $components[0]['parameters'][0]['text']);
        $this->assertSame('Bob', $components[0]['parameters'][1]['text']);
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorReturnsFalseByDefault(): void
    {
        $result = DeliveryResult::failed('boom', ['seven_error_code' => '202', 'seven_error_text' => 'Invalid recipient']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorTrueWhenErrorTextContainsOptOut(): void
    {
        $result = DeliveryResult::failed('blocked', ['seven_error_text' => 'Recipient OPT_OUT']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }
}
