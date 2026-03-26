<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Email;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Email\MailtrapGateway;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class MailtrapGatewayTest extends AbstractProviderTestCase
{
    protected function createProvider(): AbstractProvider
    {
        return new MailtrapGateway();
    }

    private function configureProvider(array $overrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mailtrap' => array_merge_recursive([
                'shared' => [
                    'api_token'      => 'test_api_token_123',
                    'webhook_secret' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
                ],
                'channels' => [
                    'email' => [
                        'from_email' => 'noreply@example.com',
                        'from_name'  => 'Test Sender',
                    ],
                ],
            ], $overrides),
        ];
    }

    private function createMessage(string $recipient = 'user@example.com', string $body = '<p>Hello</p>', array $meta = [], ?string $campaignId = null): Message
    {
        return new Message('email', $recipient, $body, null, array_merge(['subject' => 'Test Subject'], $meta), $campaignId);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function createWebhookRequest(array $events, ?string $signature = null, ?string $secret = null): \WP_REST_Request
    {
        $body = json_encode(['events' => $events]);
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/mailtrap/status');
        $request->set_body($body);

        if ($signature === null && $secret !== null) {
            $signature = hash_hmac('sha256', $body, $secret);
        }

        if ($signature !== null) {
            $request->set_header('Mailtrap-Signature', $signature);
        }

        return $request;
    }

    // --- Schema / Config ---

    public function testSupportsEmailChannel(): void
    {
        $provider = $this->createProvider();
        $this->assertSame(['email'], $provider->getSupportedChannels());
    }

    public function testConfigSchemaHasEmailChannelFields(): void
    {
        $provider = $this->createProvider();
        $schema = $provider->getConfigSchema();

        $this->assertArrayHasKey('channels', $schema);
        $this->assertArrayHasKey('email', $schema['channels']);
        $this->assertArrayHasKey('from_email', $schema['channels']['email']);
        $this->assertArrayHasKey('from_name', $schema['channels']['email']);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configureProvider();
        $provider = $this->createProvider();

        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfiguredForEmailButNotSms(): void
    {
        $this->configureProvider();
        $provider = $this->createProvider();

        $this->assertTrue($provider->isConfiguredForChannel('email'));
        $this->assertFalse($provider->isConfiguredForChannel('sms'));
    }

    // --- Sending ---

    public function testSendReturnsSentWithMessageId(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'success'     => true,
            'message_ids' => ['mt-msg-001'],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('mt-msg-001', $result->providerId);
    }

    public function testSendReturnsFailedOnHttpError(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'errors' => ['Invalid "from" email address'],
        ], 400);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid "from" email address', $result->error);
    }

    public function testSendReturnsFailedWhenCredentialsNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendUsesTransactionalEndpointForNonCampaignMessage(): void
    {
        $this->configureProvider();
        $this->mockHttpPost(['success' => true, 'message_ids' => ['mt-1']]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage());

        $url = $GLOBALS['_test_wp_remote_post_last_url'] ?? '';
        $this->assertStringStartsWith('https://send.api.mailtrap.io/', $url);
    }

    public function testSendUsesBulkEndpointForCampaignMessage(): void
    {
        $this->configureProvider();
        $this->mockHttpPost(['success' => true, 'message_ids' => ['mt-2']]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage(campaignId: 'campaign-42'));

        $url = $GLOBALS['_test_wp_remote_post_last_url'] ?? '';
        $this->assertStringStartsWith('https://bulk.api.mailtrap.io/', $url);
    }

    public function testSendBuildsCorrectPayload(): void
    {
        $this->configureProvider();
        $this->mockHttpPost(['success' => true, 'message_ids' => ['mt-3']]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage('user@example.com', '<b>Hello</b>', ['subject' => 'My Subject']));

        $args = $GLOBALS['_test_wp_remote_post_last_args'] ?? [];
        $payload = json_decode($args['body'], true);

        $this->assertSame(['email' => 'noreply@example.com', 'name' => 'Test Sender'], $payload['from']);
        $this->assertSame([['email' => 'user@example.com']], $payload['to']);
        $this->assertSame('My Subject', $payload['subject']);
        $this->assertSame('<b>Hello</b>', $payload['html']);
        $this->assertSame('Hello', $payload['text']);
    }

    public function testSendReturnsRetryableOnRateLimit(): void
    {
        $this->configureProvider();
        $this->mockHttpPost(['errors' => ['Rate limit exceeded']], 429);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    public function testSendTrimsWhitespaceFromApiToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mailtrap' => [
                'shared' => [
                    'api_token' => '  test_token_with_spaces  ',
                ],
                'channels' => [
                    'email' => ['from_email' => 'noreply@example.com'],
                ],
            ],
        ];
        $this->mockHttpPost(['success' => true, 'message_ids' => ['mt-4']]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage());

        $args = $GLOBALS['_test_wp_remote_post_last_args'] ?? [];
        $authHeader = $args['headers']['Authorization'] ?? '';
        $this->assertSame('Bearer test_token_with_spaces', $authHeader);
    }

    public function testSendHandlesEmptySubject(): void
    {
        $this->configureProvider();
        $this->mockHttpPost(['success' => true, 'message_ids' => ['mt-5']]);

        $provider = $this->createProvider();
        $message = new Message('email', 'user@example.com', '<p>Body</p>', null, []);
        $provider->send($message);

        $args = $GLOBALS['_test_wp_remote_post_last_args'] ?? [];
        $payload = json_decode($args['body'], true);
        $this->assertSame('', $payload['subject']);
    }

    public function testSendDetectsSuppressedRecipient(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'errors' => ['Recipient address is suppressed'],
        ], 400);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertTrue($provider->isOptOutError($result));
    }

    // --- Webhooks ---

    public function testValidateStatusCallbackAcceptsValidSignature(): void
    {
        $this->configureProvider();
        $provider = $this->createProvider();
        $secret = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';

        $request = $this->createWebhookRequest(
            [['event' => 'delivery', 'message_id' => 'mt-1']],
            secret: $secret,
        );

        $this->assertTrue($provider->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsInvalidSignature(): void
    {
        $this->configureProvider();
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest(
            [['event' => 'delivery', 'message_id' => 'mt-1']],
            signature: 'invalid_signature',
        );

        $this->assertFalse($provider->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingSignature(): void
    {
        $this->configureProvider();
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest(
            [['event' => 'delivery', 'message_id' => 'mt-1']],
        );

        $this->assertFalse($provider->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenNoSecretConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mailtrap' => [
                'shared' => [
                    'api_token' => 'test_token',
                ],
                'channels' => [
                    'email' => ['from_email' => 'noreply@example.com'],
                ],
            ],
        ];
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest(
            [['event' => 'delivery', 'message_id' => 'mt-1']],
            signature: 'any_signature',
        );

        $this->assertFalse($provider->validateStatusCallback($request));
    }

    public function testParseStatusCallbackReturnsDeliveredStatus(): void
    {
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest([
            ['event' => 'delivery', 'message_id' => 'mt-abc'],
        ], signature: 'unused');

        $updates = $provider->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('mt-abc', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
    }

    public function testParseStatusCallbackNormalizesStatuses(): void
    {
        $provider = $this->createProvider();

        $statusMap = [
            'delivery'    => 'delivered',
            'bounce'      => 'failed',
            'soft bounce' => 'failed',
            'reject'      => 'failed',
            'suspension'  => 'failed',
            'spam'        => 'failed',
            'unsubscribe' => 'failed',
        ];

        foreach ($statusMap as $mailtrapEvent => $normalizedStatus) {
            $request = $this->createWebhookRequest([
                ['event' => $mailtrapEvent, 'message_id' => 'mt-test'],
            ], signature: 'unused');

            $updates = $provider->parseStatusCallback($request);
            $this->assertCount(1, $updates, "Expected 1 update for {$mailtrapEvent}");
            $this->assertSame($normalizedStatus, $updates[0]->status, "Expected {$mailtrapEvent} → {$normalizedStatus}");
        }
    }

    public function testParseStatusCallbackHandlesBatchedEvents(): void
    {
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest([
            ['event' => 'delivery', 'message_id' => 'mt-1'],
            ['event' => 'bounce',   'message_id' => 'mt-2'],
            ['event' => 'delivery', 'message_id' => 'mt-3'],
        ], signature: 'unused');

        $updates = $provider->parseStatusCallback($request);

        $this->assertCount(3, $updates);
        $this->assertSame('mt-1', $updates[0]->providerId);
        $this->assertSame('mt-2', $updates[1]->providerId);
        $this->assertSame('mt-3', $updates[2]->providerId);
    }

    public function testParseStatusCallbackMarksBounceAsPermanent(): void
    {
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest([
            ['event' => 'bounce',      'message_id' => 'mt-b1'],
            ['event' => 'soft bounce', 'message_id' => 'mt-b2'],
        ], signature: 'unused');

        $updates = $provider->parseStatusCallback($request);

        $this->assertTrue($updates[0]->permanent, 'bounce should be permanent');
        $this->assertFalse($updates[1]->permanent, 'soft bounce should not be permanent');
    }

    public function testParseStatusCallbackMarksSpamAsComplaint(): void
    {
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest([
            ['event' => 'spam',        'message_id' => 'mt-s1'],
            ['event' => 'unsubscribe', 'message_id' => 'mt-s2'],
            ['event' => 'delivery',    'message_id' => 'mt-s3'],
        ], signature: 'unused');

        $updates = $provider->parseStatusCallback($request);

        $this->assertTrue($updates[0]->complaint, 'spam should be complaint');
        $this->assertTrue($updates[1]->complaint, 'unsubscribe should be complaint');
        $this->assertFalse($updates[2]->complaint, 'delivery should not be complaint');
    }

    public function testParseStatusCallbackIgnoresOpenAndClickEvents(): void
    {
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest([
            ['event' => 'open',  'message_id' => 'mt-o1'],
            ['event' => 'click', 'message_id' => 'mt-c1'],
        ], signature: 'unused');

        $updates = $provider->parseStatusCallback($request);

        $this->assertEmpty($updates);
    }

    public function testGetStatusCallbackUrlReturnsExpectedUrl(): void
    {
        $provider = $this->createProvider();
        $this->assertSame('http://localhost/wp-json/wsms/v1/callbacks/mailtrap/status', $provider->getStatusCallbackUrl());
    }

    // --- Connection ---

    public function testTestConnectionFailsWhenTokenMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mailtrap' => [
                'shared' => [],
                'channels' => [],
            ],
        ];

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', strtolower($result->message));
    }

    public function testTestConnectionReturnsErrorForInvalidToken(): void
    {
        $this->configureProvider();
        $this->mockHttpPost(['error' => 'Unauthorized'], 401);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionReturnsErrorForForbiddenToken(): void
    {
        $this->configureProvider();
        $this->mockHttpPost(['error' => 'Forbidden'], 403);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('permission', strtolower($result->message));
    }

    public function testTestConnectionReturnsOkForValidToken(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'errors' => ['from is missing', 'to is missing'],
        ], 422);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertTrue($result->success);
    }

    public function testTestConnectionHandlesRateLimit(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([], 429);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Rate limited', $result->message);
    }

    // --- Webhook edge cases ---

    public function testParseStatusCallbackReturnsEmptyForEmptyEventsArray(): void
    {
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest([], signature: 'unused');
        $updates = $provider->parseStatusCallback($request);

        $this->assertEmpty($updates);
    }

    public function testParseStatusCallbackSkipsEventsWithoutMessageId(): void
    {
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest([
            ['event' => 'delivery'],
            ['event' => 'bounce', 'message_id' => 'mt-valid'],
        ], signature: 'unused');

        $updates = $provider->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('mt-valid', $updates[0]->providerId);
    }

    public function testParseStatusCallbackSkipsUnknownEventTypes(): void
    {
        $provider = $this->createProvider();

        $request = $this->createWebhookRequest([
            ['event' => 'custom_event', 'message_id' => 'mt-unknown'],
            ['event' => 'delivery',     'message_id' => 'mt-known'],
        ], signature: 'unused');

        $updates = $provider->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('mt-known', $updates[0]->providerId);
    }
}
