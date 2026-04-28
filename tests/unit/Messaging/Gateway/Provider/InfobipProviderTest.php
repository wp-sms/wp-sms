<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\InfobipProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class InfobipProviderTest extends AbstractProviderTestCase
{
    private const BASE_URL = 'https://abc123.api.infobip.com';
    private const API_KEY = 'test-api-key';
    private const WEBHOOK_TOKEN = 'whk-token';
    private const SMS_FROM = 'WSMSTest';
    private const WA_FROM = '447860099299';
    private const RCS_FROM = 'rcs-agent-1';
    private const EMAIL_FROM = 'noreply@example.com';

    protected function createProvider(): AbstractProvider
    {
        return new InfobipProvider();
    }

    private function configureAll(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $shared = array_merge([
            'base_url'      => self::BASE_URL,
            'api_key'       => self::API_KEY,
            'webhook_token' => self::WEBHOOK_TOKEN,
        ], $sharedOverrides);

        $channels = array_replace_recursive([
            'sms'      => ['from' => self::SMS_FROM],
            'whatsapp' => ['from' => self::WA_FROM],
            'rcs'      => ['from' => self::RCS_FROM],
            'email'    => ['from_email' => self::EMAIL_FROM, 'from_name' => 'Test Site'],
        ], $channelOverrides);

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'infobip' => [
                'shared'   => $shared,
                'channels' => $channels,
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+15559876543', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
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

    private function lastPostBody(): array
    {
        return json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
    }

    // --- Identity, schema, TESTED const ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(InfobipProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('infobip', $p->getId());
        $this->assertSame(['sms', 'whatsapp', 'rcs', 'email'], $p->getSupportedChannels());
    }

    public function testConfigSchemaCoversAllFourChannels(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertArrayHasKey('whatsapp', $schema['channels']);
        $this->assertArrayHasKey('rcs', $schema['channels']);
        $this->assertArrayHasKey('email', $schema['channels']);
        $this->assertTrue($schema['shared']['base_url']['required']);
        $this->assertTrue($schema['shared']['api_key']['required']);
        $this->assertEmpty($schema['shared']['webhook_token']['required'] ?? false);
    }

    public function testIsConfiguredForChannelHandlesEachIndependently(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'infobip' => [
                'shared'   => ['base_url' => self::BASE_URL, 'api_key' => self::API_KEY],
                'channels' => ['email' => ['from_email' => self::EMAIL_FROM]],
            ],
        ];

        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('email'));
        $this->assertFalse($p->isConfiguredForChannel('sms'));
        $this->assertFalse($p->isConfiguredForChannel('whatsapp'));
        $this->assertFalse($p->isConfiguredForChannel('rcs'));
    }

    public function testSendFailsWhenSharedCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testNormalizesBaseUrlWithoutScheme(): void
    {
        $this->configureAll(['base_url' => 'abc123.api.infobip.com']);
        $this->mockHttpPost(['messages' => [['messageId' => 'x', 'status' => ['groupId' => 1]]]]);

        $this->createProvider()->send($this->createMessage());

        $this->assertStringStartsWith(
            'https://abc123.api.infobip.com/sms/2/text/advanced',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    // --- SMS send ---

    public function testSendSmsHitsAdvancedEndpointWithAppAuth(): void
    {
        $this->configureAll();
        $this->mockHttpPost([
            'messages' => [[
                'messageId' => 'sms-msg-1',
                'status'    => ['groupId' => 1, 'name' => 'PENDING_ENROUTE', 'description' => 'queued'],
            ]],
        ]);

        $result = $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('sms-msg-1', $result->providerId);

        $this->assertSame(self::BASE_URL . '/sms/2/text/advanced', $GLOBALS['_test_wp_remote_post_last_url']);
        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('App ' . self::API_KEY, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = $this->lastPostBody();
        $this->assertSame(self::SMS_FROM, $body['messages'][0]['from']);
        $this->assertSame('+15559876543', $body['messages'][0]['destinations'][0]['to']);
        $this->assertSame('Hi', $body['messages'][0]['text']);
        $this->assertStringContainsString('callbacks/infobip/status', $body['messages'][0]['notifyUrl']);
        $this->assertStringContainsString('token=' . self::WEBHOOK_TOKEN, $body['messages'][0]['notifyUrl']);
    }

    public function testSendSmsAttachesMediaUrlsToDestination(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['messages' => [['messageId' => 'mms-1', 'status' => ['groupId' => 1]]]]);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'see pic', [
            'media_urls' => ['https://example.com/a.jpg'],
        ]));

        $body = $this->lastPostBody();
        $this->assertSame(['https://example.com/a.jpg'], $body['messages'][0]['destinations'][0]['mediaUrl']);
    }

    public function testSendSmsSetsFlashFlagFromMeta(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['messages' => [['messageId' => 'flash-1', 'status' => ['groupId' => 1]]]]);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'urgent', ['flash' => true]));

        $body = $this->lastPostBody();
        $this->assertTrue($body['messages'][0]['flash']);
    }

    public function testSendSmsAttachesDltPrincipalEntityIdFromConfig(): void
    {
        $this->configureAll([], [
            'sms' => ['from' => self::SMS_FROM, 'dlt_principal_entity_id' => 'PE12345'],
        ]);
        $this->mockHttpPost(['messages' => [['messageId' => 'dlt-1', 'status' => ['groupId' => 1]]]]);

        $this->createProvider()->send($this->createMessage('sms', '+919876543210', 'Hi'));

        $body = $this->lastPostBody();
        $this->assertSame('PE12345', $body['messages'][0]['regional']['indiaDlt']['principalEntityId']);
    }

    public function testSendSmsReturnsSentForGroupIdThree(): void
    {
        $this->configureAll();
        $this->mockHttpPost([
            'messages' => [[
                'messageId' => 'delivered-1',
                'status'    => ['groupId' => 3, 'name' => 'DELIVERED_TO_HANDSET'],
                'price'     => ['pricePerMessage' => 0.012, 'currency' => 'EUR'],
            ]],
        ]);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertSame('sent', $result->status);
        $this->assertSame('delivered-1', $result->providerId);
        $this->assertSame(0.012, $result->cost);
    }

    public function testSendSmsReturnsFailedForRejectedGroupId(): void
    {
        $this->configureAll();
        $this->mockHttpPost([
            'messages' => [[
                'messageId' => 'rej-1',
                'status'    => ['groupId' => 5, 'name' => 'REJECTED_PREFIX_MISSING', 'description' => 'Prefix is missing'],
            ]],
        ]);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertSame(5, $result->meta['infobip_group_id']);
        $this->assertSame('REJECTED_PREFIX_MISSING', $result->meta['infobip_status_name']);
        $this->assertFalse($result->retryable);
    }

    public function testSendSmsReturnsRetryableFailedForExpiredGroupId(): void
    {
        $this->configureAll();
        $this->mockHttpPost([
            'messages' => [[
                'messageId' => 'exp-1',
                'status'    => ['groupId' => 4, 'name' => 'EXPIRED_EXPIRED', 'description' => 'expired'],
            ]],
        ]);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    public function testSendSmsMaps401ToInvalidCredentials(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['requestError' => ['serviceException' => ['text' => 'unauth']]], 401);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Infobip', $result->error);
    }

    // --- WhatsApp ---

    public function testSendWhatsappTextHitsTextEndpoint(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['messageId' => 'wa-1', 'status' => ['groupId' => 1]]);

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', 'Hi WA'));

        $this->assertSame(self::BASE_URL . '/whatsapp/1/message/text', $GLOBALS['_test_wp_remote_post_last_url']);
        $body = $this->lastPostBody();
        $this->assertSame(self::WA_FROM, $body['from']);
        $this->assertSame('+15559876543', $body['to']);
        $this->assertSame('Hi WA', $body['content']['text']);
    }

    public function testSendWhatsappMediaSwitchesEndpointByMediaType(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['messageId' => 'wa-doc', 'status' => ['groupId' => 1]]);

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', 'caption here', [
            'media_urls' => ['https://example.com/file.pdf'],
            'media_type' => 'document',
        ]));

        $this->assertSame(self::BASE_URL . '/whatsapp/1/message/document', $GLOBALS['_test_wp_remote_post_last_url']);
        $body = $this->lastPostBody();
        $this->assertSame('https://example.com/file.pdf', $body['content']['mediaUrl']);
        $this->assertSame('caption here', $body['content']['caption']);
    }

    public function testSendWhatsappTemplateModeBuildsTemplatePayload(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['messages' => [['messageId' => 'wa-tpl', 'status' => ['groupId' => 1]]]]);

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', '', [
            'template_mode'        => true,
            'provider_template_id' => 'verify_otp',
            'template_language'    => 'en',
            'template_variables'   => ['1' => '482916'],
        ]));

        $this->assertSame(self::BASE_URL . '/whatsapp/1/message/template', $GLOBALS['_test_wp_remote_post_last_url']);
        $body = $this->lastPostBody();
        $msg = $body['messages'][0];
        $this->assertSame('verify_otp', $msg['content']['templateName']);
        $this->assertSame('en', $msg['content']['language']);
        $this->assertSame(['482916'], $msg['content']['templateData']['body']['placeholders']);
    }

    public function testSendWhatsappCatalogTemplateResolvesViaCatalogManager(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['messages' => [['messageId' => 'wa-cat', 'status' => ['groupId' => 1]]]]);

        $catalog = $this->createMock(\WSms\Messaging\Catalog\TemplateCatalogManager::class);
        $catalog->method('resolveMapping')->with('otp', 'infobip')->willReturn(new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'otp_template',
            gatewayId: 'infobip',
            language: 'en',
            variableMap: ['otp_code' => '1'],
        ));

        $provider = new InfobipProvider();
        $provider->setCatalogManager($catalog);

        $provider->send($this->createMessage('whatsapp', '+15559876543', '', [
            'template_type'      => 'otp',
            'template_variables' => ['otp_code' => '999111'],
        ]));

        $body = $this->lastPostBody();
        $msg = $body['messages'][0];
        $this->assertSame('otp_template', $msg['content']['templateName']);
        $this->assertSame(['999111'], $msg['content']['templateData']['body']['placeholders']);
    }

    // --- RCS ---

    public function testSendRcsHitsRcsMessageEndpoint(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['messageId' => 'rcs-1', 'status' => ['groupId' => 1]]);

        $this->createProvider()->send($this->createMessage('rcs', '+15559876543', 'Hi RCS'));

        $this->assertSame(self::BASE_URL . '/rcs/3/message', $GLOBALS['_test_wp_remote_post_last_url']);
        $body = $this->lastPostBody();
        $this->assertSame(self::RCS_FROM, $body['from']);
        $this->assertSame('TEXT', $body['content']['type']);
        $this->assertSame('Hi RCS', $body['content']['text']);
    }

    public function testSendRcsMediaSwitchesContentToFile(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['messageId' => 'rcs-file', 'status' => ['groupId' => 1]]);

        $this->createProvider()->send($this->createMessage('rcs', '+15559876543', '', [
            'media_urls' => ['https://example.com/x.png'],
        ]));

        $body = $this->lastPostBody();
        $this->assertSame('FILE', $body['content']['type']);
        $this->assertSame('https://example.com/x.png', $body['content']['file']['url']);
    }

    // --- Email ---

    public function testSendEmailHitsEmailSendEndpointWithFormBody(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['messages' => [['messageId' => 'em-1', 'status' => ['groupId' => 1]]]]);

        $this->createProvider()->send($this->createMessage('email', 'user@example.com', '<p>Hi</p>', [
            'subject' => 'Welcome',
        ]));

        $this->assertSame(self::BASE_URL . '/email/3/send', $GLOBALS['_test_wp_remote_post_last_url']);
        $args = $GLOBALS['_test_wp_remote_post_last_args'];

        // Form-encoded — body is a PHP array, not a JSON string.
        $this->assertIsArray($args['body']);
        $this->assertSame('Test Site <' . self::EMAIL_FROM . '>', $args['body']['from']);
        $this->assertSame('user@example.com', $args['body']['to']);
        $this->assertSame('Welcome', $args['body']['subject']);
        $this->assertSame('<p>Hi</p>', $args['body']['html']);
        $this->assertSame('Hi', $args['body']['text']);
        $this->assertStringContainsString('callbacks/infobip/status', $args['body']['notifyUrl']);
        $this->assertSame('App ' . self::API_KEY, $args['headers']['Authorization']);
    }

    public function testSendEmailFallsBackToBareEmailWhenNoFromName(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'infobip' => [
                'shared'   => ['base_url' => self::BASE_URL, 'api_key' => self::API_KEY, 'webhook_token' => self::WEBHOOK_TOKEN],
                'channels' => ['email' => ['from_email' => self::EMAIL_FROM]],
            ],
        ];
        $this->mockHttpPost(['messages' => [['messageId' => 'em-2', 'status' => ['groupId' => 1]]]]);

        $this->createProvider()->send($this->createMessage('email', 'user@example.com', 'Hi', ['subject' => 'x']));

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::EMAIL_FROM, $args['body']['from']);
    }

    public function testSendEmailFailsWhenFromEmailMissing(): void
    {
        $this->configureAll([], [
            'email' => ['from_email' => '', 'from_name' => 'Test'],
        ]);

        $result = $this->createProvider()->send($this->createMessage('email', 'user@example.com', 'Hi'));
        $this->assertFalse($result->success);
        $this->assertStringContainsString('email sender not configured', $result->error);
    }

    // --- Status callback ---

    public function testStatusCallbackRejectsWhenNoTokenConfigured(): void
    {
        $this->configureAll(['webhook_token' => '']);
        $request = $this->buildRequest('POST', '/x', ['token' => 'whatever'], [], '{}');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsWithoutToken(): void
    {
        $this->configureAll();
        $request = $this->buildRequest('POST', '/x', [], [], '{}');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsMismatchedToken(): void
    {
        $this->configureAll();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong'], [], '{}');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configureAll();
        $request = $this->buildRequest('POST', '/x', ['token' => self::WEBHOOK_TOKEN], [], '{}');

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackParsesDeliveredResults(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'results' => [
                [
                    'messageId' => 'msg-1',
                    'status'    => ['groupId' => 3, 'name' => 'DELIVERED_TO_HANDSET', 'description' => 'Delivered'],
                ],
            ],
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(1, $updates);
        $this->assertSame('msg-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testStatusCallbackMarksPermanentForUndeliverableAndRejected(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'results' => [
                ['messageId' => 'a', 'status' => ['groupId' => 2, 'name' => 'UNDELIVERABLE_NOT_DELIVERED']],
                ['messageId' => 'b', 'status' => ['groupId' => 5, 'name' => 'REJECTED_NOT_ENOUGH_CREDITS']],
            ],
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(2, $updates);
        $this->assertTrue($updates[0]->permanent);
        $this->assertTrue($updates[1]->permanent);
        $this->assertSame('failed', $updates[0]->status);
    }

    public function testStatusCallbackMarksUnsubscribeForBlockedRejection(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'results' => [[
                'messageId' => 'opt-1',
                'status'    => ['groupId' => 5, 'name' => 'REJECTED_BLACKLISTED', 'description' => 'opted out'],
            ]],
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->unsubscribe);
        $this->assertTrue($update->permanent);
    }

    public function testStatusCallbackReturnsEmptyForUnknownPayload(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode(['random' => 'data']));
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testInboundCallbackParsesSmsResults(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'results' => [[
                'messageId'  => 'in-1',
                'from'       => '+15559876543',
                'to'         => self::SMS_FROM,
                'text'       => 'Yes please',
                'receivedAt' => '2026-04-28T10:00:00Z',
                'smsCount'   => 1,
            ]],
        ]));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('+15559876543', $msg->from);
        $this->assertSame(self::SMS_FROM, $msg->to);
        $this->assertSame('Yes please', $msg->body);
        $this->assertSame('in-1', $msg->providerId);
        $this->assertNull($msg->optOutType);
    }

    public function testInboundCallbackDetectsStopKeyword(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'results' => [[
                'messageId' => 'stop-1',
                'from'      => '+15559876543',
                'to'        => self::SMS_FROM,
                'text'      => 'STOP',
            ]],
        ]));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('sms_stop', $msg->optOutType);
    }

    public function testInboundCallbackBranchesToWhatsappShape(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'results' => [[
                'messageId' => 'wa-in',
                'from'      => '+15559876543',
                'to'        => self::WA_FROM,
                'message'   => ['text' => 'Hi back'],
            ]],
        ]));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('Hi back', $msg->body);
        $this->assertSame('+15559876543', $msg->from);
    }

    // --- Test connection ---

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->testConnection();
        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionMaps401ToInvalidKey(): void
    {
        $this->configureAll();
        $this->mockHttpGet(['requestError' => ['serviceException' => ['text' => 'unauth']]], 401);

        $result = $this->createProvider()->testConnection();
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionReturnsBalanceOnSuccess(): void
    {
        $this->configureAll();
        $this->mockHttpGet(['balance' => 12.5, 'currency' => 'EUR']);

        $result = $this->createProvider()->testConnection();
        $this->assertTrue($result->success);
        $this->assertStringContainsString('12.50', $result->message);
        $this->assertStringContainsString('EUR', $result->message);
    }

    // --- getCredit ---

    public function testGetCreditFormatsBalance(): void
    {
        $this->configureAll();
        $this->mockHttpGet(['balance' => 7, 'currency' => 'USD']);

        $this->assertSame('7.00 USD', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWithoutBaseUrl(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'infobip' => ['shared' => ['api_key' => 'x']],
        ];
        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- SupportsTemplates / fetch ---

    public function testRequiresTemplateForChannelReturnsFalse(): void
    {
        $this->assertFalse($this->createProvider()->requiresTemplateForChannel('whatsapp'));
        $this->assertFalse($this->createProvider()->requiresTemplateForChannel('rcs'));
    }

    public function testVariableStyleIsPositional(): void
    {
        $this->assertSame(VariableStyle::Positional, $this->createProvider()->getVariableStyle());
    }

    public function testBuildTemplatePayloadOrdersByPosition(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'welcome',
            providerTemplateId: 'tpl_welcome',
            gatewayId: 'infobip',
            language: 'en',
            variableMap: [],
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, ['2' => 'Bob', '1' => 'Alice', '3' => 'Acme']);
        $this->assertSame(['Alice', 'Bob', 'Acme'], $payload['content']['templateData']['body']['placeholders']);
        $this->assertSame('tpl_welcome', $payload['content']['templateName']);
        $this->assertSame('en', $payload['content']['language']);
    }

    public function testFetchTemplatesFiltersApprovedAndCountsVariables(): void
    {
        $this->configureAll();
        $this->mockHttpGet([
            'templates' => [
                [
                    'name'      => 'verify_otp',
                    'language'  => 'en',
                    'status'    => 'APPROVED',
                    'category'  => 'AUTHENTICATION',
                    'structure' => ['body' => ['text' => 'Your code is {{1}}. Expires in {{2}} minutes.']],
                ],
                [
                    'name'      => 'pending_template',
                    'language'  => 'en',
                    'status'    => 'PENDING',
                    'category'  => 'UTILITY',
                    'structure' => ['body' => ['text' => 'Hello']],
                ],
            ],
        ]);

        $templates = $this->createProvider()->fetchTemplates();

        $this->assertCount(1, $templates);
        $this->assertSame('verify_otp', $templates[0]->id);
        $this->assertSame(2, $templates[0]->variableCount);
    }

    public function testFetchTemplatesThrowsWhenSenderUnconfigured(): void
    {
        $this->configureAll([], ['whatsapp' => ['from' => '']]);

        $this->expectException(\WSms\Messaging\Catalog\TemplateCatalogException::class);
        $this->createProvider()->fetchTemplates();
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorTrueForRejectedBlacklist(): void
    {
        $result = DeliveryResult::failed('rejected', [
            'infobip_group_id'    => 5,
            'infobip_status_name' => 'REJECTED_BLACKLISTED',
        ]);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForGenericRejection(): void
    {
        $result = DeliveryResult::failed('rejected', [
            'infobip_group_id'    => 5,
            'infobip_status_name' => 'REJECTED_PREFIX_MISSING',
        ]);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForNonRejectedFailure(): void
    {
        $result = DeliveryResult::failed('expired', [
            'infobip_group_id'    => 4,
            'infobip_status_name' => 'EXPIRED_EXPIRED',
        ]);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    // --- Regulatory IDs ---

    public function testBuildRegulatoryPayloadEmitsIndiaDlt(): void
    {
        $payload = $this->createProvider()->buildRegulatoryPayload([
            'principal_entity_id' => 'PE12345',
            'content_template_id' => 'CT99',
        ]);
        $this->assertSame('PE12345', $payload['regional']['indiaDlt']['principalEntityId']);
        $this->assertSame('CT99', $payload['regional']['indiaDlt']['contentTemplateId']);
    }

    public function testBuildRegulatoryPayloadEmptyWithoutEntityId(): void
    {
        $this->assertSame([], $this->createProvider()->buildRegulatoryPayload([]));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = [], ?string $body = null): \WP_REST_Request
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
