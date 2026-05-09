<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\GupshupProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class GupshupProviderTest extends AbstractProviderTestCase
{
    private const SMS_USERID        = '2000123456';
    private const SMS_PASSWORD      = 'sms-test-password';
    private const SMS_SENDER_ID     = 'WSMSAB';
    private const SMS_WEBHOOK_TOKEN = 'sms-webhook-token-xyz';
    private const WA_API_KEY        = 'wa-test-api-key';
    private const WA_APP_NAME       = 'WsmsTestApp';
    private const WA_SOURCE         = '+15551234567';
    private const WA_WEBHOOK_TOKEN  = 'wa-webhook-token-abc';
    private const RCS_USERID        = '2000999888';
    private const RCS_PASSWORD      = 'rcs-test-password';
    private const RCS_AGENT_ID      = 'WsmsRcsAgent';
    private const RCS_WEBHOOK_TOKEN = 'rcs-webhook-token-pqr';
    private const ENTITY_ID         = '1101000000000123456';
    private const DLT_TEMPLATE_ID   = '1707170000000099999';

    protected function createProvider(): AbstractProvider
    {
        return new GupshupProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
        parent::tearDown();
    }

    private function configure(array $smsOverrides = [], array $waOverrides = [], array $rcsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gupshup' => [
                'shared'   => [],
                'channels' => [
                    'sms' => array_merge([
                        'userid'        => self::SMS_USERID,
                        'password'      => self::SMS_PASSWORD,
                        'sender_id'     => self::SMS_SENDER_ID,
                        'webhook_token' => self::SMS_WEBHOOK_TOKEN,
                    ], $smsOverrides),
                    'whatsapp' => array_merge([
                        'api_key'       => self::WA_API_KEY,
                        'app_name'      => self::WA_APP_NAME,
                        'source_number' => self::WA_SOURCE,
                        'webhook_token' => self::WA_WEBHOOK_TOKEN,
                    ], $waOverrides),
                    'rcs' => array_merge([
                        'userid'        => self::RCS_USERID,
                        'password'      => self::RCS_PASSWORD,
                        'agent_id'      => self::RCS_AGENT_ID,
                        'webhook_token' => self::RCS_WEBHOOK_TOKEN,
                    ], $rcsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '919999988888', string $body = 'Hello', array $meta = []): Message
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

    private function mockHttpGet(array $responseBody, int $statusCode = 200, ?callable $capture = null): void
    {
        $payload = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];

        if ($capture) {
            $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($payload, $capture) {
                $capture($url, $args);
                return $payload;
            };
        } else {
            $GLOBALS['_test_wp_remote_get'] = $payload;
        }
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(GupshupProvider::TESTED);
    }

    public function testGetIdReturnsGupshup(): void
    {
        $this->assertSame('gupshup', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSmsWhatsappAndRcs(): void
    {
        $this->assertSame(['sms', 'whatsapp', 'rcs'], $this->createProvider()->getSupportedChannels());
    }

    public function testConfigSchemaHasRcsCredentials(): void
    {
        $rcs = $this->createProvider()->getConfigSchema()['channels']['rcs'];

        $this->assertTrue($rcs['userid']['required']);
        $this->assertSame('secret', $rcs['password']['type']);
        $this->assertTrue($rcs['password']['required']);
        $this->assertFalse($rcs['agent_id']['required'] ?? true);
        $this->assertSame('secret', $rcs['webhook_token']['type']);
    }

    public function testConfigSchemaHasPerChannelCredentials(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame([], $schema['shared']);

        $sms = $schema['channels']['sms'];
        $this->assertTrue($sms['userid']['required']);
        $this->assertSame('secret', $sms['password']['type']);
        $this->assertTrue($sms['password']['required']);
        $this->assertTrue($sms['sender_id']['required']);
        $this->assertSame('secret', $sms['webhook_token']['type']);
        $this->assertFalse($sms['webhook_token']['required'] ?? true);

        $wa = $schema['channels']['whatsapp'];
        $this->assertSame('secret', $wa['api_key']['type']);
        $this->assertTrue($wa['api_key']['required']);
        $this->assertTrue($wa['app_name']['required']);
        $this->assertTrue($wa['source_number']['required']);
        $this->assertSame('secret', $wa['webhook_token']['type']);
    }

    public function testIsConfiguredForChannelSmsButNotWhatsappWhenWaMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gupshup' => [
                'shared'   => [],
                'channels' => [
                    'sms' => [
                        'userid'    => self::SMS_USERID,
                        'password'  => self::SMS_PASSWORD,
                        'sender_id' => self::SMS_SENDER_ID,
                    ],
                    'whatsapp' => [],
                ],
            ],
        ];

        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('sms'));
        $this->assertFalse($p->isConfiguredForChannel('whatsapp'));
    }

    // --- Send: SMS ---

    public function testSendSmsBuildsCorrectRequest(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'response' => [
                'id'      => 'sms-msg-id-001',
                'phone'   => '919999988888',
                'details' => 'Message sent successfully',
                'status'  => 'success',
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage('sms', '919999988888', 'Hi there'));

        $this->assertTrue($result->success);
        $this->assertSame('sms-msg-id-001', $result->providerId);

        $this->assertSame(
            'https://enterprise.smsgupshup.com/GatewayAPI/rest',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('SendMessage',     $body['method']);
        $this->assertSame(self::SMS_USERID,  $body['userid']);
        $this->assertSame(self::SMS_PASSWORD, $body['password']);
        $this->assertSame('plain',           $body['auth_scheme']);
        $this->assertSame('919999988888',    $body['send_to']);
        $this->assertSame('Hi there',        $body['msg']);
        $this->assertSame('json',            $body['format']);
        $this->assertSame(self::SMS_SENDER_ID, $body['mask']);
    }

    public function testSendSmsIncludesDltMetaWhenProvided(): void
    {
        $this->configure();
        $this->mockHttpPost(['response' => ['id' => 'sms-dlt-1', 'status' => 'success']]);

        $this->createProvider()->send($this->createMessage('sms', '919999988888', 'Your OTP is 482916', [
            'regulatory' => [
                'principal_entity_id' => self::ENTITY_ID,
                'dlt_template_id'     => self::DLT_TEMPLATE_ID,
            ],
        ]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(self::ENTITY_ID,       $body['principalEntityId']);
        $this->assertSame(self::DLT_TEMPLATE_ID, $body['dltTemplateId']);
    }

    public function testSendSmsFlashMsgType(): void
    {
        $this->configure();
        $this->mockHttpPost(['response' => ['id' => 'sms-flash-1', 'status' => 'success']]);

        $this->createProvider()->send($this->createMessage('sms', '919999988888', 'Flash!', ['flash' => true]));

        $this->assertSame('flash', $GLOBALS['_test_wp_remote_post_last_args']['body']['msg_type']);
    }

    public function testSendSmsReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendSmsBubblesUpProviderErrorMessage(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'response' => [
                'status'  => 'error',
                'details' => 'Authentication failed',
                'id'      => null,
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication failed', $result->error);
    }

    // --- Send: WhatsApp ---

    public function testSendWhatsAppBuildsCorrectRequest(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status'    => 'submitted',
            'messageId' => 'wa-msg-id-001',
        ]);

        $result = $this->createProvider()->send($this->createMessage('whatsapp', '919999988888', 'Hi WA'));

        $this->assertTrue($result->success);
        $this->assertSame('wa-msg-id-001', $result->providerId);

        $this->assertSame(
            'https://api.gupshup.io/wa/api/v1/msg',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::WA_API_KEY, $args['headers']['apikey']);

        $body = $args['body'];
        $this->assertSame('whatsapp',      $body['channel']);
        $this->assertSame(self::WA_SOURCE, $body['source']);
        $this->assertSame('919999988888',  $body['destination']);
        $this->assertSame(self::WA_APP_NAME, $body['src.name']);

        $message = json_decode($body['message'], true);
        $this->assertSame('text', $message['type']);
        $this->assertSame('Hi WA', $message['text']);
    }

    public function testSendWhatsAppMediaSendsImagePayload(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'submitted', 'messageId' => 'wa-media-1']);

        $this->createProvider()->send($this->createMessage('whatsapp', '919999988888', 'Caption', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $message = json_decode($body['message'], true);
        $this->assertSame('image', $message['type']);
        $this->assertSame('https://example.com/photo.jpg', $message['originalUrl']);
        $this->assertSame('Caption', $message['caption']);
    }

    public function testSendWhatsAppTemplateModeBuildsTemplatePayload(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'submitted', 'messageId' => 'wa-tpl-1']);

        $this->createProvider()->send($this->createMessage('whatsapp', '919999988888', '', [
            'template_mode'        => true,
            'provider_template_id' => 'auth_otp_v1',
            'template_language'    => 'en_US',
            'template_variables'   => ['1' => '482916'],
        ]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $template = json_decode($body['template'], true);
        $this->assertSame('auth_otp_v1', $template['id']);
        $this->assertSame(['482916'], $template['params']);
    }

    public function testSendWhatsAppFailsWhenApiKeyMissing(): void
    {
        $this->configure(waOverrides: ['api_key' => '']);

        $result = $this->createProvider()->send($this->createMessage('whatsapp', '919999988888', 'Hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendWhatsAppReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'error', 'message' => 'Authentication Failed'], 401);

        $result = $this->createProvider()->send($this->createMessage('whatsapp'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    // --- Send: RCS ---

    public function testSendRcsBuildsJsonMsgPayload(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'response' => [
                'id'      => 'rcs-msg-id-001',
                'phone'   => '919999988888',
                'status'  => 'success',
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage('rcs', '919999988888', 'Hi via RCS'));

        $this->assertTrue($result->success);
        $this->assertSame('rcs-msg-id-001', $result->providerId);

        $this->assertSame(
            'https://enterprise.smsgupshup.com/GatewayAPI/rest',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('SendMessage',     $body['method']);
        $this->assertSame(self::RCS_USERID,  $body['userid']);
        $this->assertSame(self::RCS_PASSWORD, $body['password']);
        $this->assertSame('plain',           $body['auth_scheme']);
        $this->assertSame('919999988888',    $body['send_to']);
        $this->assertSame('TEXT',            $body['msg_type']);

        $msg = json_decode($body['msg'], true);
        $this->assertSame('text', $msg['type']);
        $this->assertSame('Hi via RCS', $msg['text']);
    }

    public function testSendRcsImageBuildsImageJsonPayload(): void
    {
        $this->configure();
        $this->mockHttpPost(['response' => ['id' => 'rcs-img-1', 'status' => 'success']]);

        $this->createProvider()->send($this->createMessage('rcs', '919999988888', 'See pic', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $msg = json_decode($body['msg'], true);
        $this->assertSame('image', $msg['type']);
        $this->assertSame('https://example.com/photo.jpg', $msg['url']);
        $this->assertSame('See pic', $msg['text']);
    }

    public function testSendRcsFailsWhenCredentialsMissing(): void
    {
        $this->configure(rcsOverrides: ['userid' => '', 'password' => '']);

        $result = $this->createProvider()->send($this->createMessage('rcs'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendRcsBubblesUpProviderError(): void
    {
        $this->configure();
        $this->mockHttpPost(['response' => ['status' => 'error', 'details' => 'Agent not approved']]);

        $result = $this->createProvider()->send($this->createMessage('rcs'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Agent not approved', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditCallsCheckBalance(): void
    {
        $this->configure();
        $captured = ['url' => null, 'args' => null];
        $this->mockHttpGet(
            [
                'response' => [
                    'status'  => 'success',
                    'balance' => '493.20',
                ],
            ],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url']  = $url;
                $captured['args'] = $args;
            },
        );

        $balance = $this->createProvider()->getCredit();

        $this->assertNotNull($balance);
        $this->assertStringContainsString('493.20', $balance);
        $this->assertStringContainsString('enterprise.smsgupshup.com', $captured['url']);
        $this->assertStringContainsString('method=CHECK_BALANCE', $captured['url']);
    }

    public function testGetCreditReturnsNullWhenSmsUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gupshup' => [
                'shared'   => [],
                'channels' => ['whatsapp' => ['api_key' => self::WA_API_KEY, 'app_name' => self::WA_APP_NAME, 'source_number' => self::WA_SOURCE]],
            ],
        ];

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionSucceedsWithCheckBalance(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'response' => [
                'status'  => 'success',
                'balance' => '500.00',
            ],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500.00', $result->message);
    }

    public function testTestConnectionFailsOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresAtLeastOneChannel(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Configure', $result->message);
    }

    // --- Status callback ---

    public function testStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptsMatchingSmsToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => self::SMS_WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptsMatchingWaToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => self::WA_WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptsMatchingRcsToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => self::RCS_WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackParsesDeliveredEventForWhatsApp(): void
    {
        $request = $this->buildRequest('POST', '/x', []);
        $request->set_body(json_encode([
            'type'    => 'message-event',
            'payload' => [
                'id'   => 'wa-id-1',
                'type' => 'delivered',
            ],
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(1, $updates);
        $this->assertSame('wa-id-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testStatusCallbackMarksFailedAsPermanent(): void
    {
        $request = $this->buildRequest('POST', '/x', []);
        $request->set_body(json_encode([
            'type'    => 'message-event',
            'payload' => [
                'id'      => 'wa-id-bad',
                'type'    => 'failed',
                'payload' => ['code' => 471, 'reason' => 'recipient blocked'],
            ],
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertTrue($updates[0]->permanent);
        $this->assertSame('471', $updates[0]->errorCode);
    }

    public function testStatusCallbackParsesSmsDeliveryReport(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'externalId' => 'sms-id-1',
            'status'     => 'SUCCESS',
            'phoneNo'    => '919999988888',
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(1, $updates);
        $this->assertSame('sms-id-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
    }

    public function testStatusCallbackMarksSmsUnknownSubscriberPermanent(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'externalId' => 'sms-id-bad',
            'status'     => 'UNKNOWN_SUBSCRIBER',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
    }

    // --- Inbound callback ---

    public function testInboundCallbackParsesTextMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', ['token' => self::WA_WEBHOOK_TOKEN]);
        $request->set_body(json_encode([
            'type'    => 'message',
            'payload' => [
                'id'          => 'mo-1',
                'type'        => 'text',
                'source'      => '919999988888',
                'destination' => self::WA_SOURCE,
                'payload'     => ['text' => 'Hello back'],
            ],
        ]));

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('919999988888',     $msg->from);
        $this->assertSame(self::WA_SOURCE,    $msg->to);
        $this->assertSame('Hello back',       $msg->body);
        $this->assertSame('mo-1',             $msg->providerId);
    }

    public function testInboundCallbackCapturesMediaUrls(): void
    {
        $request = $this->buildRequest('POST', '/x', []);
        $request->set_body(json_encode([
            'type'    => 'message',
            'payload' => [
                'id'          => 'mo-img',
                'type'        => 'image',
                'source'      => '919999988888',
                'destination' => self::WA_SOURCE,
                'payload'     => [
                    'url'     => 'https://media.gupshup.io/photo.jpg',
                    'caption' => 'A photo',
                ],
            ],
        ]));

        $messages = $this->createProvider()->parseInboundCallback($request);
        $msg = $messages[0];
        $this->assertSame(['https://media.gupshup.io/photo.jpg'], $msg->meta['media_urls']);
        $this->assertSame('A photo', $msg->body);
    }

    public function testInboundCallbackParsesSmsMoFormat(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'phoneNo' => '919999988888',
            'message' => 'STOP',
            'sender'  => self::SMS_SENDER_ID,
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('919999988888',     $messages[0]->from);
        $this->assertSame(self::SMS_SENDER_ID, $messages[0]->to);
        $this->assertSame('STOP',             $messages[0]->body);
    }

    // --- SupportsTemplates ---

    public function testRequiresTemplateForChannelTrueForWhatsapp(): void
    {
        $p = $this->createProvider();
        $this->assertTrue($p->requiresTemplateForChannel('whatsapp'));
        $this->assertFalse($p->requiresTemplateForChannel('sms'));
        $this->assertFalse($p->requiresTemplateForChannel('rcs'));
    }

    public function testVariableStyleIsPositional(): void
    {
        $this->assertSame(VariableStyle::Positional, $this->createProvider()->getVariableStyle());
    }

    public function testBuildTemplatePayloadOrdersVariables(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'welcome',
            providerTemplateId: 'tpl_welcome',
            gatewayId: 'gupshup',
            language: 'en',
            variableMap: [],
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, [
            '2' => 'Bob', '1' => 'Alice', '3' => 'Acme',
        ]);

        $this->assertSame('tpl_welcome', $payload['id']);
        $this->assertSame(['Alice', 'Bob', 'Acme'], $payload['params']);
    }

    // --- SupportsTemplateFetch ---

    public function testFetchTemplatesParsesWhatsAppList(): void
    {
        $this->configure();
        $captured = ['url' => null, 'args' => null];
        $this->mockHttpGet(
            [
                'status'    => 'success',
                'templates' => [
                    [
                        'id'           => 'tpl-id-1',
                        'elementName'  => 'welcome_msg',
                        'languageCode' => 'en_US',
                        'category'     => 'UTILITY',
                        'status'       => 'APPROVED',
                        'data'         => 'Hi {{1}}, welcome to {{2}}!',
                    ],
                    [
                        'id'           => 'tpl-id-2',
                        'elementName'  => 'pending_msg',
                        'languageCode' => 'en',
                        'category'     => 'AUTHENTICATION',
                        'status'       => 'PENDING',
                        'data'         => 'Your code is {{1}}',
                    ],
                ],
            ],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url']  = $url;
                $captured['args'] = $args;
            },
        );

        $templates = $this->createProvider()->fetchTemplates();

        $this->assertCount(2, $templates);

        $this->assertSame('tpl-id-1', $templates[0]->id);
        $this->assertSame('welcome_msg', $templates[0]->name);
        $this->assertSame('en_US', $templates[0]->language);
        $this->assertSame(2, $templates[0]->variableCount);
        $this->assertSame(TemplateStatus::Approved, $templates[0]->status);

        $this->assertSame(TemplateStatus::Pending, $templates[1]->status);
        $this->assertSame(1, $templates[1]->variableCount);

        $this->assertStringContainsString('api.gupshup.io', $captured['url']);
        $this->assertStringContainsString('/wa/app/' . self::WA_APP_NAME . '/template', $captured['url']);
        $this->assertSame(self::WA_API_KEY, $captured['args']['headers']['apikey']);
    }

    public function testFetchTemplatesReturnsEmptyWhenWhatsappNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gupshup' => [
                'shared'   => [],
                'channels' => ['sms' => ['userid' => self::SMS_USERID, 'password' => self::SMS_PASSWORD, 'sender_id' => self::SMS_SENDER_ID]],
            ],
        ];

        $this->assertSame([], $this->createProvider()->fetchTemplates());
    }

    // --- SupportsRegulatoryIds ---

    public function testRegulatoryConfigSchemaExposesDltFields(): void
    {
        $schema = $this->createProvider()->getRegulatoryConfigSchema();
        $this->assertArrayHasKey('principal_entity_id', $schema);
        $this->assertArrayHasKey('dlt_template_id', $schema);
    }

    public function testBuildRegulatoryPayloadShapesGupshupParams(): void
    {
        $payload = $this->createProvider()->buildRegulatoryPayload([
            'principal_entity_id' => self::ENTITY_ID,
            'dlt_template_id'     => self::DLT_TEMPLATE_ID,
        ]);

        $this->assertSame(self::ENTITY_ID, $payload['principalEntityId']);
        $this->assertSame(self::DLT_TEMPLATE_ID, $payload['dltTemplateId']);
    }

    public function testBuildRegulatoryPayloadEmptyWhenNoIds(): void
    {
        $this->assertSame([], $this->createProvider()->buildRegulatoryPayload([]));
    }

    // --- SupportsOptOutDetection ---

    public function testIsOptOutErrorRecognizesSmsCode1008(): void
    {
        $result = DeliveryResult::failed('opted out', ['gupshup_error_code' => '1008']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorRecognizesUnknownSubscriber(): void
    {
        $result = DeliveryResult::failed('unknown subscriber', ['gupshup_error_code' => 'UNKNOWN_SUBSCRIBER']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorRecognizesWhatsappCode471(): void
    {
        $result = DeliveryResult::failed('blocked', ['gupshup_error_code' => '471']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForOtherCodes(): void
    {
        $result = DeliveryResult::failed('boom', ['gupshup_error_code' => '500']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseWhenNoCode(): void
    {
        $result = DeliveryResult::failed('boom');
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = []): \WP_REST_Request
    {
        return new class($method, $route, $params, $headers) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, array $params, array $headers) {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) $this->set_param($k, $v);
                foreach ($headers as $k => $v) $this->set_header($k, $v);
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }
}
