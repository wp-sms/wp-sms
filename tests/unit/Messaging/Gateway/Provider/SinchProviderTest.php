<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SinchProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SinchProviderTest extends AbstractProviderTestCase
{
    private const SPI = 'spi-test-1234';
    private const API_TOKEN = 'sms-bearer-token';
    private const SMS_FROM = '+15551234567';
    private const CALLBACK_SECRET = 'sms-hmac-secret';

    private const PROJECT_ID = 'proj-abc';
    private const KEY_ID = 'key-xyz';
    private const KEY_SECRET = 'key-secret-123';
    private const WA_APP_ID = 'app-wa-1';
    private const RCS_APP_ID = 'app-rcs-1';
    private const WA_WEBHOOK_SECRET = 'wa-webhook-secret';
    private const RCS_WEBHOOK_SECRET = 'rcs-webhook-secret';

    protected function createProvider(): AbstractProvider
    {
        return new SinchProvider();
    }

    private function configureSms(array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'sinch' => [
                'shared'   => ['region' => 'us'],
                'channels' => [
                    'sms' => array_merge([
                        'service_plan_id' => self::SPI,
                        'api_token'       => self::API_TOKEN,
                        'from'            => self::SMS_FROM,
                    ], $smsOverrides),
                ],
            ],
        ];
    }

    private function configureWhatsapp(array $waOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'sinch' => [
                'shared'   => ['region' => 'us'],
                'channels' => [
                    'whatsapp' => array_merge([
                        'project_id'        => self::PROJECT_ID,
                        'access_key_id'     => self::KEY_ID,
                        'access_key_secret' => self::KEY_SECRET,
                        'app_id'            => self::WA_APP_ID,
                        'webhook_secret'    => self::WA_WEBHOOK_SECRET,
                    ], $waOverrides),
                ],
            ],
        ];
    }

    private function configureRcs(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'sinch' => [
                'shared'   => ['region' => 'us'],
                'channels' => [
                    'rcs' => [
                        'project_id'        => self::PROJECT_ID,
                        'access_key_id'     => self::KEY_ID,
                        'access_key_secret' => self::KEY_SECRET,
                        'app_id'            => self::RCS_APP_ID,
                        'webhook_secret'    => self::RCS_WEBHOOK_SECRET,
                    ],
                ],
            ],
        ];
    }

    private function configureAll(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'sinch' => [
                'shared'   => ['region' => 'us'],
                'channels' => [
                    'sms' => [
                        'service_plan_id' => self::SPI,
                        'api_token'       => self::API_TOKEN,
                        'from'            => self::SMS_FROM,
                        'callback_secret' => self::CALLBACK_SECRET,
                    ],
                    'whatsapp' => [
                        'project_id'        => self::PROJECT_ID,
                        'access_key_id'     => self::KEY_ID,
                        'access_key_secret' => self::KEY_SECRET,
                        'app_id'            => self::WA_APP_ID,
                        'webhook_secret'    => self::WA_WEBHOOK_SECRET,
                    ],
                    'rcs' => [
                        'project_id'        => self::PROJECT_ID,
                        'access_key_id'     => self::KEY_ID,
                        'access_key_secret' => self::KEY_SECRET,
                        'app_id'            => self::RCS_APP_ID,
                        'webhook_secret'    => self::RCS_WEBHOOK_SECRET,
                    ],
                ],
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+15559876543', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 201): void
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

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('sinch', $p->getId());
        $this->assertSame(['sms', 'whatsapp', 'rcs'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SinchProvider::TESTED);
    }

    public function testConfigSchemaCovers_three_channels(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertArrayHasKey('whatsapp', $schema['channels']);
        $this->assertArrayHasKey('rcs', $schema['channels']);
        $this->assertSame('select', $schema['shared']['region']['type']);
    }

    public function testIsConfiguredForChannelHandlesEachIndependently(): void
    {
        $this->configureWhatsapp();
        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('whatsapp'));
        $this->assertFalse($p->isConfiguredForChannel('sms'));
        $this->assertFalse($p->isConfiguredForChannel('rcs'));
    }

    // --- Send: SMS ---

    public function testSmsSendQueuedReturnsBatchId(): void
    {
        $this->configureSms();
        $this->mockHttpPost(['id' => 'batch-001', 'to' => ['+15559876543']]);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('batch-001', $result->providerId);
    }

    public function testSmsSendUsesUsRegionUrlAndBearerAuth(): void
    {
        $this->configureSms();
        $this->mockHttpPost(['id' => 'b-1']);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'Hi'));

        $this->assertSame(
            'https://us.sms.api.sinch.com/xms/v1/' . self::SPI . '/batches',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer ' . self::API_TOKEN, $args['headers']['Authorization']);
        $body = json_decode($args['body'], true);
        $this->assertSame(['+15559876543'], $body['to']);
        $this->assertSame('Hi', $body['body']);
        $this->assertSame(self::SMS_FROM, $body['from']);
        $this->assertStringContainsString('callbacks/sinch/status', $body['callback_url']);
    }

    public function testSmsSendUsesEuRegionWhenConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'sinch' => [
                'shared'   => ['region' => 'eu'],
                'channels' => [
                    'sms' => ['service_plan_id' => self::SPI, 'api_token' => self::API_TOKEN, 'from' => self::SMS_FROM],
                ],
            ],
        ];
        $this->mockHttpPost(['id' => 'b-eu']);

        $this->createProvider()->send($this->createMessage());

        $this->assertStringStartsWith('https://eu.sms.api.sinch.com/', $GLOBALS['_test_wp_remote_post_last_url']);
    }

    public function testSmsWithMediaUrlsUsesMtMediaType(): void
    {
        $this->configureSms();
        $this->mockHttpPost(['id' => 'b-mms']);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'See pic', [
            'media_urls' => ['https://example.com/a.jpg'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('mt_media', $body['type']);
        $this->assertSame('https://example.com/a.jpg', $body['parameters']['media']['url']);
    }

    public function testSmsSendReturnsFailedOn401(): void
    {
        $this->configureSms();
        $this->mockHttpPost(['code' => 'unauth'], 401);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSmsSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Send: WhatsApp via Conversations API ---

    public function testWhatsappSendUsesConversationsApiAndBasicAuth(): void
    {
        $this->configureWhatsapp();
        $this->mockHttpPost(['message_id' => 'msg-wa-1', 'accepted_time' => '2026-04-28T10:00:00Z'], 200);

        $result = $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', 'Hi WA'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-wa-1', $result->providerId);

        $this->assertSame(
            'https://us.conversation.api.sinch.com/v1/projects/' . self::PROJECT_ID . '/messages:send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $expectedAuth = 'Basic ' . base64_encode(self::KEY_ID . ':' . self::KEY_SECRET);
        $this->assertSame($expectedAuth, $args['headers']['Authorization']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::WA_APP_ID, $body['app_id']);
        $this->assertSame('WHATSAPP', $body['recipient']['identified_by']['channel_identities'][0]['channel']);
        $this->assertSame('+15559876543', $body['recipient']['identified_by']['channel_identities'][0]['identity']);
        $this->assertSame('Hi WA', $body['message']['text_message']['text']);
        $this->assertSame(['WHATSAPP'], $body['channel_priority_order']);
    }

    public function testWhatsappSendWithTemplateUsesTemplateMessage(): void
    {
        $this->configureWhatsapp();
        $this->mockHttpPost(['message_id' => 'msg-tpl-1'], 200);

        $template = ['template_id' => 'tpl-abc', 'parameters' => ['name' => 'Alice']];
        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', '', [
            'template' => $template,
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame($template, $body['message']['template_message']);
    }

    public function testWhatsappSendWithMediaUsesMediaMessage(): void
    {
        $this->configureWhatsapp();
        $this->mockHttpPost(['message_id' => 'msg-media-1'], 200);

        $this->createProvider()->send($this->createMessage('whatsapp', '+15559876543', 'caption', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('https://example.com/photo.jpg', $body['message']['media_message']['url']);
    }

    public function testWhatsappSendReturnsFailedOn401(): void
    {
        $this->configureWhatsapp();
        $this->mockHttpPost(['error' => ['message' => 'unauth']], 401);

        $result = $this->createProvider()->send($this->createMessage('whatsapp'));
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    // --- Send: RCS ---

    public function testRcsSendUsesRcsChannel(): void
    {
        $this->configureRcs();
        $this->mockHttpPost(['message_id' => 'msg-rcs-1'], 200);

        $this->createProvider()->send($this->createMessage('rcs', '+15559876543', 'Hi RCS'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::RCS_APP_ID, $body['app_id']);
        $this->assertSame('RCS', $body['recipient']['identified_by']['channel_identities'][0]['channel']);
        $this->assertSame(['RCS'], $body['channel_priority_order']);
    }

    // --- Test connection ---

    public function testTestConnectionPrefersSmsApiWhenConfigured(): void
    {
        $this->configureAll();
        $this->mockHttpGet(['batches' => []]);

        $result = $this->createProvider()->testConnection();
        $this->assertTrue($result->success);
        $this->assertStringContainsString('SMS API', $result->message);
    }

    public function testTestConnectionFallsBackToConversationsApi(): void
    {
        $this->configureWhatsapp();
        $this->mockHttpGet(['apps' => []]);

        $result = $this->createProvider()->testConnection();
        $this->assertTrue($result->success);
        $this->assertStringContainsString('Conversations', $result->message);
    }

    public function testTestConnectionRequiresAtLeastOneChannel(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->testConnection();
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Configure', $result->message);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configureSms();
        $this->mockHttpGet(['error' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    // --- Status callback parsing ---

    public function testParseSmsDeliveryReportProducesUpdates(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'type'                => 'delivery_report_sms',
            'batch_id'            => 'batch-xyz',
            'total_message_count' => 1,
            'statuses'            => [
                ['code' => 0, 'count' => 1, 'status' => 'Delivered', 'recipients' => ['+15559876543']],
            ],
        ]));

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(1, $updates);
        $this->assertSame('batch-xyz', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
    }

    public function testParseSmsDeliveryReportMarksAbortedAsPermanent(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'type'     => 'delivery_report_sms',
            'batch_id' => 'b-fail',
            'statuses' => [
                ['code' => 403, 'count' => 1, 'status' => 'Aborted', 'recipients' => ['+15559876543']],
            ],
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('403', $update->errorCode);
    }

    public function testParseConversationsDeliveryReport(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'app_id'                  => self::WA_APP_ID,
            'message_delivery_report' => [
                'message_id' => 'msg-wa-1',
                'status'     => 'DELIVERED',
            ],
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('msg-wa-1', $update->providerId);
        $this->assertSame('delivered', $update->status);
    }

    public function testParseConversationsDeliveryReportFailedWithReason(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'message_delivery_report' => [
                'message_id' => 'msg-fail',
                'status'     => 'FAILED',
                'reason'     => ['code' => 'UNSUBSCRIBED_RECIPIENT', 'description' => 'User opted out'],
            ],
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertSame('UNSUBSCRIBED_RECIPIENT', $update->errorCode);
        $this->assertTrue($update->permanent);
        $this->assertStringContainsString('User opted out', $update->errorMessage);
    }

    public function testParseStatusCallbackReturnsEmptyForUnknownPayload(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode(['random' => 'data']));
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback parsing ---

    public function testParseSmsInboundProducesInboundMessage(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'type' => 'mo_text',
            'id'   => 'mo-1',
            'from' => '+15559876543',
            'to'   => self::SMS_FROM,
            'body' => 'STOP',
            'received_at' => '2026-04-28T10:00:00Z',
        ]));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('+15559876543', $msg->from);
        $this->assertSame(self::SMS_FROM, $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('mo-1', $msg->providerId);
    }

    public function testParseConversationsInbound(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'app_id'  => self::WA_APP_ID,
            'message' => [
                'id'              => 'in-wa-1',
                'contact_message' => ['text_message' => ['text' => 'Hi back']],
                'channel_identity'=> ['channel' => 'WHATSAPP', 'identity' => '+15559876543'],
                'conversation_id' => 'conv-1',
                'contact_id'      => 'contact-1',
            ],
        ]));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('+15559876543', $msg->from);
        $this->assertSame(self::WA_APP_ID, $msg->to);
        $this->assertSame('Hi back', $msg->body);
        $this->assertSame('in-wa-1', $msg->providerId);
        $this->assertSame('WHATSAPP', $msg->meta['channel']);
        $this->assertSame('conv-1', $msg->meta['conversation_id']);
    }

    public function testParseConversationsInboundMediaCarriesUrl(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode([
            'app_id'  => self::WA_APP_ID,
            'message' => [
                'id'              => 'in-media-1',
                'contact_message' => [
                    'media_message' => ['url' => 'https://media.example/x.jpg', 'caption' => 'pic'],
                ],
                'channel_identity'=> ['channel' => 'WHATSAPP', 'identity' => '+15559876543'],
            ],
        ]));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('pic', $msg->body);
        $this->assertSame(['https://media.example/x.jpg'], $msg->meta['media_urls']);
    }

    public function testParseInboundCallbackReturnsEmptyForUnknownPayload(): void
    {
        $request = $this->buildRequest('POST', '/x', [], [], json_encode(['random' => 'data']));
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Signature verification ---

    public function testValidateConversationsSignatureAcceptsValid(): void
    {
        $this->configureWhatsapp();
        $body = '{"app_id":"app-wa-1","message_delivery_report":{"message_id":"x","status":"DELIVERED"}}';
        $nonce = 'nonce-1';
        $timestamp = '1745842500';
        $signature = base64_encode(hash_hmac('sha256', $body . '.' . $nonce . '.' . $timestamp, self::WA_WEBHOOK_SECRET, true));

        $request = $this->buildRequest('POST', '/x', [], [
            'x-sinch-webhook-signature'           => $signature,
            'x-sinch-webhook-signature-nonce'     => $nonce,
            'x-sinch-webhook-signature-timestamp' => $timestamp,
        ], $body);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateConversationsSignatureRejectsBadSignature(): void
    {
        $this->configureWhatsapp();
        $request = $this->buildRequest('POST', '/x', [], [
            'x-sinch-webhook-signature'           => 'bogus',
            'x-sinch-webhook-signature-nonce'     => 'n',
            'x-sinch-webhook-signature-timestamp' => '1',
        ], '{}');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateConversationsSignatureTriesBothChannelSecrets(): void
    {
        // Configure both whatsapp and rcs; sign with the RCS secret. Should still validate.
        $this->configureAll();
        $body = '{"app_id":"app-rcs-1","message_delivery_report":{"message_id":"r","status":"DELIVERED"}}';
        $nonce = 'rcs-nonce';
        $timestamp = '1745842600';
        $signature = base64_encode(hash_hmac('sha256', $body . '.' . $nonce . '.' . $timestamp, self::RCS_WEBHOOK_SECRET, true));

        $request = $this->buildRequest('POST', '/x', [], [
            'x-sinch-webhook-signature'           => $signature,
            'x-sinch-webhook-signature-nonce'     => $nonce,
            'x-sinch-webhook-signature-timestamp' => $timestamp,
        ], $body);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateSmsSignatureAcceptsValid(): void
    {
        $this->configureAll();
        $body = '{"type":"delivery_report_sms","batch_id":"x"}';
        $timestamp = '1745842700';
        $signature = hash_hmac('sha256', $timestamp . $body, self::CALLBACK_SECRET);

        $request = $this->buildRequest('POST', '/x', [], [
            'x-sinch-signature' => $signature,
            'x-sinch-timestamp' => $timestamp,
        ], $body);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateSmsSignatureRejectsWhenSecretMissingButHeaderPresent(): void
    {
        $this->configureSms(); // no callback_secret
        $request = $this->buildRequest('POST', '/x', [], [
            'x-sinch-signature' => 'abc',
            'x-sinch-timestamp' => '1',
        ], '{}');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateAcceptsUnsignedSmsWhenNoSecretConfigured(): void
    {
        $this->configureSms(); // no callback_secret, no signature header
        $request = $this->buildRequest('POST', '/x', [], [], '{}');
        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateRejectsUnsignedWhenSmsSecretConfigured(): void
    {
        $this->configureSms(['callback_secret' => self::CALLBACK_SECRET]);
        $request = $this->buildRequest('POST', '/x', [], [], '{}');
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorTrueForUnsubscribedRecipient(): void
    {
        $result = \WSms\Messaging\Contracts\DeliveryResult::failed('user opted out', ['sinch_code' => 'UNSUBSCRIBED_RECIPIENT']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForGenericFailure(): void
    {
        $result = \WSms\Messaging\Contracts\DeliveryResult::failed('boom', ['sinch_code' => 'INTERNAL_ERROR']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
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
