<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmstoProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmstoProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'smsto-test-api-key';
    private const SECRET  = 'smsto-test-webhook-secret';
    private const SMS_SENDER   = 'MyCompany';
    private const VIBER_SENDER = 'MyViberBiz';

    protected function createProvider(): AbstractProvider
    {
        return new SmstoProvider();
    }

    private function configure(?string $callbackSecret = self::SECRET, array $channelOverrides = []): void
    {
        $shared = ['api_key' => self::API_KEY];
        if ($callbackSecret !== null) {
            $shared['callback_secret'] = $callbackSecret;
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsto' => [
                'shared'   => $shared,
                'channels' => array_merge([
                    'sms'   => ['sender_id' => self::SMS_SENDER],
                    'viber' => ['sender_id' => self::VIBER_SENDER],
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

    private function expectedAuthHeader(): string
    {
        return 'Bearer ' . self::API_KEY;
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smsto', $p->getId());
        $this->assertSame(['sms', 'viber'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmstoProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('callback_secret', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['callback_secret']['type']);
        $this->assertFalse($schema['shared']['callback_secret']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertArrayHasKey('sender_id', $schema['channels']['viber']);
    }

    public function testIsConfiguredForChannelSmsTrueWithApiKeyOnlyButViberNeedsSender(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsto' => ['shared' => ['api_key' => self::API_KEY], 'channels' => []],
        ];

        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('sms'));
        $this->assertFalse($p->isConfiguredForChannel('viber'));
        $this->assertFalse($p->isConfiguredForChannel('whatsapp'));
    }

    public function testIsConfiguredForChannelViberTrueOnceSenderSet(): void
    {
        $this->configure();
        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('viber'));
    }

    // --- Send: SMS ---

    public function testSmsSendQueuedReturnsMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success'    => true,
            'message'    => 'Created. Sending in progress.',
            'message_id' => 'msg-abc-001',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-abc-001', $result->providerId);
    }

    public function testSmsSendPostsToCorrectUrlWithBearerAuthAndBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true, 'message_id' => 'm1']);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'Hi'));

        $this->assertSame('https://api.sms.to/sms/send', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedAuthHeader(), $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('+15559876543', $body['to']);
        $this->assertSame('Hi', $body['message']);
        $this->assertSame(self::SMS_SENDER, $body['sender_id']);
        $this->assertStringContainsString('callbacks/smsto/status', $body['callback_url']);
    }

    public function testSmsSendOmitsSenderIdWhenNotConfigured(): void
    {
        $this->configure(channelOverrides: ['sms' => []]);
        $this->mockHttpPost(['success' => true, 'message_id' => 'm1']);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('sender_id', $body);
    }

    public function testSmsFlashMetaRoutesToFlashEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true, 'message_id' => 'flash-1']);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'Urgent', ['flash' => true]));

        $this->assertSame('https://api.sms.to/fsms/send', $GLOBALS['_test_wp_remote_post_last_url']);
    }

    public function testSmsScheduledMetaForwardsScheduledFields(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true, 'message_id' => 'sched-1']);

        $this->createProvider()->send($this->createMessage('sms', '+15559876543', 'Later', [
            'scheduled_for' => '2026-05-01T12:00:00Z',
            'timezone'      => 'UTC',
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('2026-05-01T12:00:00Z', $body['scheduled_for']);
        $this->assertSame('UTC', $body['timezone']);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'unauth'], 401);

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

    public function testSendBubblesUpProviderError(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => false, 'message' => 'Phone number is invalid'], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Phone number is invalid', $result->error);
        $this->assertSame(422, $result->meta['smsto_status']);
    }

    public function testSendUsesFirstValidationErrorWhenErrorsArrayPresent(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success' => false,
            'errors'  => ['to' => ['The to field is required.']],
        ], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('The to field is required.', $result->error);
    }

    // --- Send: Viber ---

    public function testViberSendPostsToViberEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true, 'message_id' => 'vib-1']);

        $this->createProvider()->send($this->createMessage('viber', '+15559876543', 'Hi viber'));

        $this->assertSame('https://api.sms.to/viber/send', $GLOBALS['_test_wp_remote_post_last_url']);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('+15559876543', $body['to']);
        $this->assertSame('Hi viber', $body['message']);
        $this->assertSame(self::VIBER_SENDER, $body['sender_id']);
    }

    public function testViberSendIncludesImageFromMediaUrls(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true, 'message_id' => 'vib-2']);

        $this->createProvider()->send($this->createMessage('viber', '+15559876543', 'Look', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('https://example.com/photo.jpg', $body['viber_image_url']);
    }

    public function testViberSendIncludesAllOptionalViberFields(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true, 'message_id' => 'vib-3']);

        $this->createProvider()->send($this->createMessage('viber', '+15559876543', 'Promo', [
            'viber_image_url'  => 'https://example.com/img.png',
            'viber_target_url' => 'https://example.com/landing',
            'viber_caption'    => 'Click me',
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('https://example.com/img.png', $body['viber_image_url']);
        $this->assertSame('https://example.com/landing', $body['viber_target_url']);
        $this->assertSame('Click me', $body['viber_caption']);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsFormattedBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => '12.5000']);

        $this->assertSame('12.5000 EUR', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => '5.0000']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5.0000', $result->message);
        $this->assertSame('5.0000', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
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

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsValidSignature(): void
    {
        $this->configure();
        $payload = wp_json_encode([
            'messageId' => 'msg-1',
            'status'    => 'DELIVERED',
            'phone'     => '+15559876543',
        ]);
        $timestamp = '1714400000';
        $signature = hash_hmac('sha256', $payload . '.' . $timestamp, self::SECRET);

        $request = $this->buildSignedRequest($payload, $signature, $timestamp);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadSignature(): void
    {
        $this->configure();
        $payload = '{"messageId":"x","status":"DELIVERED"}';
        $request = $this->buildSignedRequest($payload, 'totally-bogus', '1714400000');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingHeader(): void
    {
        $this->configure();
        $request = $this->buildSignedRequest('{}', null, null);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenNoSecretConfigured(): void
    {
        $this->configure(callbackSecret: null);
        $payload = '{"messageId":"x","status":"DELIVERED"}';
        $request = $this->buildSignedRequest($payload, 'anything', '1714400000');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsStatuses(): void
    {
        $cases = [
            'QUEUED'      => 'queued',
            'SENT'        => 'sent',
            'DELIVERED'   => 'delivered',
            'READ'        => 'delivered',
            'FAILED'      => 'failed',
            'UNDELIVERED' => 'failed',
            'REJECTED'    => 'failed',
            'EXPIRED'     => 'failed',
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => $expected) {
            $request = $this->buildJsonRequest([
                'messageId' => 'uuid-' . $raw,
                'status'    => $raw,
            ]);
            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expected, $updates[0]->status, "wrong mapping for {$raw}");
        }
    }

    public function testParseStatusCallbackFlagsUnsubscribeOnOptOut(): void
    {
        $request = $this->buildJsonRequest([
            'messageId' => 'opt-1',
            'status'    => 'OPTOUT',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->unsubscribe);
        $this->assertTrue($update->permanent);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildJsonRequest([]);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackFallsBackToFormParams(): void
    {
        $request = $this->buildFormRequest([
            'messageId' => 'form-1',
            'status'    => 'DELIVERED',
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(1, $updates);
        $this->assertSame('form-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = $this->buildJsonRequest([
            'from'         => '+15559876543',
            'to'           => '+15551234567',
            'message'      => 'Hello back',
            'parts'        => 1,
            'receivedDate' => '2026-04-29T12:34:56Z',
            'messageId'    => 'in-1',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+15559876543', $msg->from);
        $this->assertSame('+15551234567', $msg->to);
        $this->assertSame('Hello back', $msg->body);
        $this->assertSame('in-1', $msg->providerId);
        $this->assertSame('2026-04-29T12:34:56Z', $msg->meta['received_at']);
    }

    public function testParseInboundCallbackEmptyWithoutFrom(): void
    {
        $request = $this->buildJsonRequest([]);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorTrueWhenMessageMentionsOptOut(): void
    {
        $p = $this->createProvider();

        $this->assertTrue($p->isOptOutError(DeliveryResult::failed('Recipient has opted out')));
        $this->assertTrue($p->isOptOutError(DeliveryResult::failed('Phone is on the opt-out list')));
        $this->assertTrue($p->isOptOutError(DeliveryResult::failed('User unsubscribed')));
    }

    public function testIsOptOutErrorFalseForUnrelatedErrors(): void
    {
        $p = $this->createProvider();
        $this->assertFalse($p->isOptOutError(DeliveryResult::failed('Invalid number')));
        $this->assertFalse($p->isOptOutError(DeliveryResult::failed('')));
    }

    // --- Helpers ---

    private function buildJsonRequest(array $jsonBody): \WP_REST_Request
    {
        return new class($jsonBody) extends \WP_REST_Request {
            private array $jsonBody;
            public function __construct(array $jsonBody) {
                parent::__construct('POST', '/x');
                $this->jsonBody = $jsonBody;
            }
            public function get_method(): string { return 'POST'; }
            public function get_json_params(): array { return $this->jsonBody; }
            public function get_body(): ?string { return json_encode($this->jsonBody); }
        };
    }

    private function buildFormRequest(array $formParams): \WP_REST_Request
    {
        $request = new class($formParams) extends \WP_REST_Request {
            public function __construct(array $params) {
                parent::__construct('POST', '/x');
                foreach ($params as $k => $v) $this->set_param($k, $v);
            }
            public function get_method(): string { return 'POST'; }
            public function get_json_params(): array { return []; }
            public function get_body(): ?string { return ''; }
        };
        return $request;
    }

    private function buildSignedRequest(string $rawBody, ?string $signature, ?string $timestamp): \WP_REST_Request
    {
        $headerValue = null;
        if ($signature !== null && $timestamp !== null) {
            $headerValue = "t={$timestamp},s={$signature}";
        }

        return new class($rawBody, $headerValue) extends \WP_REST_Request {
            private string $rawBody;
            private ?string $headerValue;
            public function __construct(string $rawBody, ?string $headerValue) {
                parent::__construct('POST', '/x');
                $this->rawBody = $rawBody;
                $this->headerValue = $headerValue;
                if ($headerValue !== null) {
                    $this->set_header('x-smsto-signature', $headerValue);
                }
            }
            public function get_method(): string { return 'POST'; }
            public function get_body(): ?string { return $this->rawBody; }
            public function get_header(string $key): ?string {
                if (strtolower($key) === 'x-smsto-signature') {
                    return $this->headerValue;
                }
                return parent::get_header($key);
            }
        };
    }
}
