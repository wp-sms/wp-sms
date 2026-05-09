<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BulkSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BulkSmsProviderTest extends AbstractProviderTestCase
{
    private const TOKEN_ID = 'token-id-123';
    private const TOKEN_SECRET = 'token-secret-456';

    protected function createProvider(): AbstractProvider
    {
        return new BulkSmsProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get'],
        );

        parent::tearDown();
    }

    private function configureProvider(array $channelOverrides = [], ?string $webhookToken = null): void
    {
        $shared = [
            'token_id'     => self::TOKEN_ID,
            'token_secret' => self::TOKEN_SECRET,
        ];

        if ($webhookToken !== null) {
            $shared['webhook_token'] = $webhookToken;
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulksms' => [
                'shared'   => $shared,
                'channels' => array_merge([
                    'sms' => ['from' => 'WPSMS'],
                ], $channelOverrides),
            ],
        ];
    }

    private function message(array $meta = []): Message
    {
        return new Message('sms', '+15559876543', 'Hello BulkSMS', null, $meta);
    }

    private function mockPost(array|string $responseBody, int $statusCode = 201): void
    {
        $body = is_string($responseBody) ? $responseBody : wp_json_encode($responseBody);
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => wp_json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function successResponse(string $messageId = 'bs-message-1', string $statusType = 'ACCEPTED'): array
    {
        return [[
            'id'     => $messageId,
            'type'   => 'SENT',
            'status' => ['type' => $statusType],
        ]];
    }

    private function request(array $params = [], ?array $json = null): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/bulksms/status');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        if ($json !== null) {
            $request->set_body(wp_json_encode($json));
        }

        return $request;
    }

    public function testIdentityChannelsAndSchema(): void
    {
        $provider = $this->createProvider();
        $schema = $provider->getConfigSchema();

        $this->assertSame('bulksms', $provider->getId());
        $this->assertSame(['sms'], $provider->getSupportedChannels());
        $this->assertArrayHasKey('token_id', $schema['shared']);
        $this->assertArrayHasKey('token_secret', $schema['shared']);
        $this->assertArrayHasKey('webhook_token', $schema['shared']);
        $this->assertTrue($schema['shared']['token_id']['required']);
        $this->assertTrue($schema['shared']['token_secret']['required']);
        $this->assertFalse($schema['shared']['webhook_token']['required']);
        $this->assertFalse($schema['channels']['sms']['from']['required']);
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BulkSmsProvider::TESTED);
    }

    public function testSendUsesBasicAuthAndArrayPayloadWithSenderId(): void
    {
        $this->configureProvider();
        $this->mockPost($this->successResponse('msg-1'));

        $result = $this->createProvider()->send($this->message());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-1', $result->providerId);
        $this->assertSame('https://api.bulksms.com/v1/messages?auto-unicode=true', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(
            'Basic ' . base64_encode(self::TOKEN_ID . ':' . self::TOKEN_SECRET),
            $args['headers']['Authorization'],
        );
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame([[
            'to'   => '+15559876543',
            'body' => 'Hello BulkSMS',
            'from' => 'WPSMS',
        ]], $body);
    }

    public function testSendUsesRepliableSenderWhenFromIsBlank(): void
    {
        $this->configureProvider(['sms' => ['from' => '']]);
        $this->mockPost($this->successResponse());

        $result = $this->createProvider()->send($this->message());
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);

        $this->assertTrue($result->success);
        $this->assertSame(['type' => 'REPLIABLE'], $body[0]['from']);
    }

    public function testSendReturnsSentForSentStatusType(): void
    {
        $this->configureProvider();
        $this->mockPost($this->successResponse('msg-2', 'SENT'));

        $result = $this->createProvider()->send($this->message());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('msg-2', $result->providerId);
    }

    public function testSendFailsForMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsOnAuthError(): void
    {
        $this->configureProvider();
        $this->mockPost(['title' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid BulkSMS', $result->error);
    }

    public function testSendFailsOnApiError(): void
    {
        $this->configureProvider();
        $this->mockPost(['detail' => 'Insufficient credit'], 422);

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credit', $result->error);
    }

    public function testGetCreditReturnsBalance(): void
    {
        $this->configureProvider();
        $this->mockGet([
            'credits' => ['balance' => 12.5],
        ]);

        $this->assertSame('12.5', $this->createProvider()->getCredit());
    }

    public function testTestConnectionSuccess(): void
    {
        $this->configureProvider();
        $this->mockGet([
            'credits' => ['balance' => 12.5],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame(12.5, $result->details['balance']);
        $this->assertStringContainsString('12.5', $result->message);
    }

    public function testTestConnectionRejectsBadCredentials(): void
    {
        $this->configureProvider();
        $this->mockGet(['title' => 'Unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid BulkSMS', $result->message);
    }

    public function testStatusCallbackParsesDeliveredFromArrayPayload(): void
    {
        $this->configureProvider();

        $updates = $this->createProvider()->parseStatusCallback($this->request([], [[
            'id'     => 'dlr-1',
            'to'     => '+15559876543',
            'status' => ['type' => 'DELIVERED'],
        ]]));

        $this->assertCount(1, $updates);
        $this->assertSame('dlr-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
        $this->assertFalse($updates[0]->unsubscribe);
    }

    public function testStatusCallbackParsesFailedBlockedAsPermanentOptOut(): void
    {
        $this->configureProvider();

        $updates = $this->createProvider()->parseStatusCallback($this->request([], [[
            'id'     => 'dlr-blocked',
            'status' => ['type' => 'FAILED', 'subtype' => 'BLOCKED'],
        ]]));

        $this->assertCount(1, $updates);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertTrue($updates[0]->permanent);
        $this->assertTrue($updates[0]->unsubscribe);
        $this->assertSame('BLOCKED', $updates[0]->errorCode);
    }

    public function testStatusCallbackMarksHandsetErrorAndNotSentAsPermanent(): void
    {
        $this->configureProvider();

        $updates = $this->createProvider()->parseStatusCallback($this->request([], [
            ['id' => 'h1', 'status' => ['type' => 'FAILED', 'subtype' => 'HANDSET_ERROR']],
            ['id' => 'h2', 'status' => ['type' => 'FAILED', 'subtype' => 'NOT_SENT']],
            ['id' => 'h3', 'status' => ['type' => 'FAILED', 'subtype' => 'EXPIRED']],
        ]));

        $this->assertCount(3, $updates);
        $this->assertTrue($updates[0]->permanent);
        $this->assertTrue($updates[1]->permanent);
        $this->assertFalse($updates[2]->permanent);
        $this->assertFalse($updates[0]->unsubscribe);
    }

    public function testStatusCallbackTokenValidation(): void
    {
        $this->configureProvider(webhookToken: 'secret-token');
        $provider = $this->createProvider();

        $this->assertFalse($provider->validateStatusCallback($this->request()));
        $this->assertFalse($provider->validateStatusCallback($this->request(['token' => 'wrong'])));
        $this->assertTrue($provider->validateStatusCallback($this->request(['token' => 'secret-token'])));
        $this->assertStringContainsString('token=secret-token', $provider->getStatusCallbackUrl());
    }

    public function testInboundCallbackParsesReceivedMessage(): void
    {
        $this->configureProvider();

        $messages = $this->createProvider()->parseInboundCallback($this->request([], [[
            'id'                   => 'mo-1',
            'type'                 => 'RECEIVED',
            'from'                 => '+15559876543',
            'to'                   => 'WPSMS',
            'body'                 => 'STOP',
            'relatedSentMessageId' => 'sent-99',
        ]]));

        $this->assertCount(1, $messages);
        $this->assertSame('mo-1', $messages[0]->providerId);
        $this->assertSame('+15559876543', $messages[0]->from);
        $this->assertSame('WPSMS', $messages[0]->to);
        $this->assertSame('STOP', $messages[0]->body);
        $this->assertSame('sent-99', $messages[0]->meta['related_sent_message_id']);
    }

    public function testInboundCallbackSkipsNonReceivedMessages(): void
    {
        $this->configureProvider();

        $messages = $this->createProvider()->parseInboundCallback($this->request([], [
            ['id' => 'm1', 'type' => 'SENT', 'from' => '+15559876543', 'to' => 'WPSMS', 'body' => 'ignored'],
            ['id' => 'm2', 'type' => 'RECEIVED', 'from' => '+15551112222', 'to' => 'WPSMS', 'body' => 'kept'],
        ]));

        $this->assertCount(1, $messages);
        $this->assertSame('m2', $messages[0]->providerId);
        $this->assertSame('kept', $messages[0]->body);
    }

    public function testInboundCallbackTokenMismatchRejects(): void
    {
        $this->configureProvider(webhookToken: 'secret-token');
        $provider = $this->createProvider();

        $this->assertFalse($provider->validateInboundCallback($this->request(['token' => 'wrong'])));
        $this->assertTrue($provider->validateInboundCallback($this->request(['token' => 'secret-token'])));
        $this->assertStringContainsString('token=secret-token', $provider->getInboundCallbackUrl());
    }
}
