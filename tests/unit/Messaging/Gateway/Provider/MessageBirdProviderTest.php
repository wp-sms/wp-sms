<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\MessageBirdProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class MessageBirdProviderTest extends AbstractProviderTestCase
{
    private const ACCESS_KEY = 'bird-access-key';
    private const WORKSPACE_ID = 'workspace-123';
    private const SIGNING_KEY = 'webhook-signing-key';

    protected function setUp(): void
    {
        parent::setUp();
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get'],
        );
    }

    protected function createProvider(): AbstractProvider
    {
        return new MessageBirdProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'messagebird' => [
                'shared' => array_merge([
                    'access_key'          => self::ACCESS_KEY,
                    'workspace_id'        => self::WORKSPACE_ID,
                    'organization_id'     => 'org-123',
                    'webhook_signing_key' => self::SIGNING_KEY,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms'      => ['channel_id' => 'sms-channel'],
                    'whatsapp' => ['channel_id' => 'wa-channel'],
                    'rcs'      => ['channel_id' => 'rcs-channel'],
                ], $channelOverrides),
            ],
        ];
    }

    private function message(string $channel = 'sms', string $recipient = '+31612345678', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, 'flow-123', $meta);
    }

    private function mockPost(array $body, int $code = 202): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($body),
            'response' => ['code' => $code],
        ];
    }

    private function request(array $payload, ?string $url = null, string $timestamp = '1700000000'): \WP_REST_Request
    {
        $body = json_encode($payload);
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/messagebird/status');
        $request->set_body($body);
        $request->set_header('messagebird-request-timestamp', $timestamp);
        $request->set_header('messagebird-signature', $this->signature($body, $url ?? $this->createProvider()->getStatusCallbackUrl(), $timestamp));

        return $request;
    }

    private function signature(string $body, string $url, string $timestamp): string
    {
        $checksum = hash('sha256', $body, true);
        $payload = $timestamp . "\n" . $url . "\n" . $checksum;
        return base64_encode(hash_hmac('sha256', $payload, self::SIGNING_KEY, true));
    }

    public function testIdentityChannelsAndTestedFlag(): void
    {
        $provider = $this->createProvider();

        $this->assertSame('messagebird', $provider->getId());
        $this->assertSame(['sms', 'whatsapp', 'rcs'], $provider->getSupportedChannels());
        $this->assertFalse(MessageBirdProvider::TESTED);
    }

    public function testConfigSchemaAndChannelConfiguration(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertTrue($schema['shared']['access_key']['required']);
        $this->assertSame('secret', $schema['shared']['access_key']['type']);
        $this->assertTrue($schema['shared']['workspace_id']['required']);
        $this->assertFalse($schema['shared']['organization_id']['required']);
        $this->assertFalse($schema['shared']['webhook_signing_key']['required']);
        $this->assertTrue($schema['channels']['sms']['channel_id']['required']);
        $this->assertTrue($schema['channels']['whatsapp']['channel_id']['required']);
        $this->assertTrue($schema['channels']['rcs']['channel_id']['required']);

        $this->configure([], ['whatsapp' => [], 'rcs' => []]);
        $provider = $this->createProvider();

        $this->assertTrue($provider->isConfigured());
        $this->assertTrue($provider->isConfiguredForChannel('sms'));
        $this->assertFalse($provider->isConfiguredForChannel('whatsapp'));
        $this->assertFalse($provider->isConfiguredForChannel('rcs'));
    }

    public function testFeaturesAdvertiseChannelsApiCapabilities(): void
    {
        $features = $this->createProvider()->getFeatures();

        $this->assertTrue($features['mms']);
        $this->assertFalse($features['flash_sms']);
        $this->assertTrue($features['delivery_receipt']);
        $this->assertTrue($features['incoming']);
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['media']);
        $this->assertTrue($features['test_connection']);
    }

    public function testSmsSendUsesChannelsApiWithAccessKeyAuth(): void
    {
        $this->configure();
        $this->mockPost(['id' => 'bird-msg-1', 'status' => 'accepted']);

        $result = $this->createProvider()->send($this->message('sms', '+31612345678', 'Hi SMS'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('bird-msg-1', $result->providerId);
        $this->assertSame(
            'https://api.bird.com/workspaces/workspace-123/channels/sms-channel/messages',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('AccessKey ' . self::ACCESS_KEY, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('+31612345678', $body['receiver']['contacts'][0]['identifierValue']);
        $this->assertSame('phonenumber', $body['receiver']['contacts'][0]['identifierKey']);
        $this->assertSame(['type' => 'text', 'text' => ['text' => 'Hi SMS']], $body['body']);
        $this->assertSame('flow-123', $body['reference']);
    }

    public function testWhatsAppAndRcsUseTheirOwnChannelIds(): void
    {
        $this->configure();
        $this->mockPost(['id' => 'wa-msg', 'status' => 'accepted']);

        $wa = $this->createProvider()->send($this->message('whatsapp', '+31611111111', 'Hi WA'));
        $this->assertTrue($wa->success);
        $this->assertSame('https://api.bird.com/workspaces/workspace-123/channels/wa-channel/messages', $GLOBALS['_test_wp_remote_post_last_url']);

        $this->mockPost(['id' => 'rcs-msg', 'status' => 'accepted']);
        $rcs = $this->createProvider()->send($this->message('rcs', '+31622222222', 'Hi RCS'));
        $this->assertTrue($rcs->success);
        $this->assertSame('https://api.bird.com/workspaces/workspace-123/channels/rcs-channel/messages', $GLOBALS['_test_wp_remote_post_last_url']);
    }

    public function testSmsMediaUsesBirdImageBodyWithMediaUrls(): void
    {
        $this->configure();
        $this->mockPost(['id' => 'mms-msg', 'status' => 'accepted']);

        $this->createProvider()->send($this->message('sms', '+31612345678', 'Image text', [
            'media_urls' => ['https://example.com/a.jpg', 'https://example.com/b.png'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('image', $body['body']['type']);
        $this->assertSame('Image text', $body['body']['image']['text']);
        $this->assertSame(
            [['mediaUrl' => 'https://example.com/a.jpg'], ['mediaUrl' => 'https://example.com/b.png']],
            $body['body']['image']['images'],
        );
    }

    public function testTemplatePayloadUsesNameLocaleAndSortedParameters(): void
    {
        $provider = $this->createProvider();
        $payload = $provider->buildTemplatePayload(
            new TemplateMapping('otp', 'login_code', 'messagebird', 'en_US', []),
            ['2' => 'Foad', '1' => '123456'],
        );

        $this->assertSame(VariableStyle::Positional, $provider->getVariableStyle());
        $this->assertFalse($provider->requiresTemplateForChannel('whatsapp'));
        $this->assertSame('login_code', $payload['template']['name']);
        $this->assertSame('en_US', $payload['template']['locale']);
        $this->assertSame([
            ['type' => 'string', 'key' => '1', 'value' => '123456'],
            ['type' => 'string', 'key' => '2', 'value' => 'Foad'],
        ], $payload['template']['parameters']);
    }

    public function testTemplateModeSendsTemplateInsteadOfBody(): void
    {
        $this->configure();
        $this->mockPost(['id' => 'tpl-msg', 'status' => 'accepted']);

        $this->createProvider()->send($this->message('whatsapp', '+31612345678', '', [
            'template_mode'        => true,
            'provider_template_id' => 'welcome_template',
            'template_language'    => 'en',
            'template_variables'   => ['1' => 'Ada'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayHasKey('template', $body);
        $this->assertArrayNotHasKey('body', $body);
        $this->assertSame('welcome_template', $body['template']['name']);
        $this->assertSame('Ada', $body['template']['parameters'][0]['value']);
    }

    public function testSendHandlesAuthAndProviderErrors(): void
    {
        $this->configure();
        $this->mockPost(['message' => 'Unauthorized'], 401);

        $auth = $this->createProvider()->send($this->message());
        $this->assertFalse($auth->success);
        $this->assertStringContainsString('Invalid', $auth->error);

        $this->mockPost([
            'errors' => [
                ['code' => 'invalid_receiver', 'message' => 'Receiver is invalid'],
            ],
        ], 422);

        $failed = $this->createProvider()->send($this->message());
        $this->assertFalse($failed->success);
        $this->assertSame('Receiver is invalid', $failed->error);
        $this->assertSame('invalid_receiver', $failed->meta['messagebird_code']);
    }

    public function testTestConnectionUsesCheapListMessagesGet(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) {
            $this->assertSame('https://api.bird.com/workspaces/workspace-123/channels/sms-channel/messages?limit=1', $url);
            $this->assertSame('AccessKey ' . self::ACCESS_KEY, $args['headers']['Authorization']);
            return ['body' => json_encode(['results' => []]), 'response' => ['code' => 200]];
        };

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('workspace-123', $result->details['workspace_id']);
        $this->assertSame('sms-channel', $result->details['channel_id']);
    }

    public function testTestConnectionHandlesInvalidCredentials(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = ['body' => json_encode(['message' => 'Forbidden']), 'response' => ['code' => 403]];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testStatusCallbackSignatureValidationAndParsing(): void
    {
        $this->configure();
        $provider = $this->createProvider();
        $request = $this->request([
            'service' => 'channels',
            'event'   => 'sms.outbound',
            'payload' => [
                'id'        => 'bird-msg-1',
                'status'    => 'delivery_failed',
                'failure'   => ['code' => 42, 'description' => 'Carrier rejected'],
                'direction' => 'outgoing',
            ],
        ], $provider->getStatusCallbackUrl());

        $this->assertTrue($provider->validateStatusCallback($request));

        $updates = $provider->parseStatusCallback($request);
        $this->assertCount(1, $updates);
        $this->assertSame('bird-msg-1', $updates[0]->providerId);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertSame('42', $updates[0]->errorCode);
        $this->assertSame('Carrier rejected', $updates[0]->errorMessage);
        $this->assertTrue($updates[0]->permanent);

        $request->set_header('messagebird-signature', 'invalid');
        $this->assertFalse($provider->validateStatusCallback($request));
    }

    public function testInboundCallbackSignatureValidationAndParsing(): void
    {
        $this->configure();
        $provider = $this->createProvider();
        $request = $this->request([
            'service' => 'channels',
            'event'   => 'whatsapp.inbound',
            'payload' => [
                'id'        => 'inbound-1',
                'channelId' => 'wa-channel',
                'direction' => 'incoming',
                'sender'    => ['contact' => ['identifierValue' => '+31612345678']],
                'receiver'  => ['connector' => ['identifierValue' => '+31600000000']],
                'body'      => ['type' => 'text', 'text' => ['text' => 'STOP']],
                'status'    => 'delivered',
            ],
        ], $provider->getInboundCallbackUrl());

        $this->assertTrue($provider->validateInboundCallback($request));

        $messages = $provider->parseInboundCallback($request);
        $this->assertCount(1, $messages);
        $this->assertSame('+31612345678', $messages[0]->from);
        $this->assertSame('+31600000000', $messages[0]->to);
        $this->assertSame('STOP', $messages[0]->body);
        $this->assertSame('inbound-1', $messages[0]->providerId);
        $this->assertSame('wa-channel', $messages[0]->meta['channel_id']);
    }

    public function testOptOutInteractionDetection(): void
    {
        $this->configure();
        $provider = $this->createProvider();
        $request = $this->request([
            'event'   => 'sms.interaction',
            'payload' => [
                'id'        => 'interaction-1',
                'messageId' => 'bird-msg-1',
                'type'      => 'unsubscribe-request',
            ],
        ], $provider->getStatusCallbackUrl());

        $updates = $provider->parseStatusCallback($request);
        $this->assertSame('bird-msg-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertTrue($updates[0]->unsubscribe);

        $this->assertTrue($provider->isOptOutError(DeliveryResult::failed('suppressed recipient', [
            'messagebird_code' => 'contact_suppression',
        ])));
    }
}
