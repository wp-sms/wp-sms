<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\ClickSendProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class ClickSendProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'api-user';
    private const API_KEY = 'api-key';

    protected function createProvider(): AbstractProvider
    {
        return new ClickSendProvider();
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
            'username' => self::USERNAME,
            'api_key'  => self::API_KEY,
        ];

        if ($webhookToken !== null) {
            $shared['webhook_token'] = $webhookToken;
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'clicksend' => [
                'shared'   => $shared,
                'channels' => array_merge([
                    'sms' => ['from' => '+15551234567'],
                    'rcs' => ['from' => '+15557654321'],
                ], $channelOverrides),
            ],
        ];
    }

    private function message(string $channel = 'sms', array $meta = []): Message
    {
        return new Message($channel, '+15559876543', 'Hello ClickSend', null, $meta);
    }

    private function mockPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => wp_json_encode($responseBody),
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

    private function successResponse(string $messageId = 'cs-message-1'): array
    {
        return [
            'response_code' => 'SUCCESS',
            'response_msg'  => 'Messages queued for delivery.',
            'data'          => [
                'blocked_count' => 0,
                'messages'      => [[
                    'message_id'    => $messageId,
                    'status'        => 'SUCCESS',
                    'message_price' => '0.0792',
                ]],
            ],
        ];
    }

    private function request(array $params = [], ?array $json = null): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/clicksend/status');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        if ($json !== null) {
            $request->set_body(wp_json_encode($json));
        }

        return $request;
    }

    public function testIdentityChannelsSchemaAndManualVerificationFlag(): void
    {
        $provider = $this->createProvider();
        $schema = $provider->getConfigSchema();

        $this->assertSame('clicksend', $provider->getId());
        $this->assertSame(['sms', 'rcs'], $provider->getSupportedChannels());
        $this->assertFalse(ClickSendProvider::TESTED);
        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertArrayHasKey('webhook_token', $schema['shared']);
        $this->assertFalse($schema['channels']['sms']['from']['required']);
        $this->assertFalse($schema['channels']['rcs']['from']['required']);
    }

    public function testSmsSendUsesSmsEndpointBasicAuthAndJsonBody(): void
    {
        $this->configureProvider();
        $this->mockPost($this->successResponse('sms-1'));

        $result = $this->createProvider()->send($this->message());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('sms-1', $result->providerId);
        $this->assertSame(0.0792, $result->cost);
        $this->assertSame('https://rest.clicksend.com/v3/sms/send', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Basic ' . base64_encode(self::USERNAME . ':' . self::API_KEY), $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame([[
            'source' => 'wp-sms',
            'body'   => 'Hello ClickSend',
            'to'     => '+15559876543',
            'from'   => '+15551234567',
        ]], $body['messages']);
    }

    public function testSmsSendAllowsBlankSenderForClickSendDefaults(): void
    {
        $this->configureProvider(['sms' => []]);
        $this->mockPost($this->successResponse());

        $result = $this->createProvider()->send($this->message());
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);

        $this->assertTrue($result->success);
        $this->assertArrayNotHasKey('from', $body['messages'][0]);
    }

    public function testRcsRoutesThroughSmsEndpoint(): void
    {
        $this->configureProvider();
        $this->mockPost($this->successResponse('rcs-1'));

        $result = $this->createProvider()->send($this->message('rcs'));
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);

        $this->assertTrue($result->success);
        $this->assertSame('rcs-1', $result->providerId);
        $this->assertSame('https://rest.clicksend.com/v3/sms/send', $GLOBALS['_test_wp_remote_post_last_url']);
        $this->assertSame('+15557654321', $body['messages'][0]['from']);
    }

    public function testMmsUsesMmsEndpointAndFirstMediaUrl(): void
    {
        $this->configureProvider();
        $this->mockPost($this->successResponse('mms-1'));

        $result = $this->createProvider()->send($this->message('sms', [
            'subject'    => 'Promo MMS',
            'media_urls' => ['https://example.com/a.gif', 'https://example.com/b.gif'],
        ]));
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);

        $this->assertTrue($result->success);
        $this->assertSame('mms-1', $result->providerId);
        $this->assertSame('https://rest.clicksend.com/v3/mms/send', $GLOBALS['_test_wp_remote_post_last_url']);
        $this->assertSame('https://example.com/a.gif', $body['media_file']);
        $this->assertSame('Promo MMS', $body['messages'][0]['subject']);
        $this->assertSame('+15551234567', $body['messages'][0]['from']);
        $this->assertArrayNotHasKey(1, $body['messages']);
    }

    public function testMmsFailsWithoutSender(): void
    {
        $this->configureProvider(['sms' => []]);

        $result = $this->createProvider()->send($this->message('sms', [
            'media_urls' => ['https://example.com/a.gif'],
        ]));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('MMS requires', $result->error);
    }

    public function testAccountBalanceAndTestConnectionSuccess(): void
    {
        $this->configureProvider();
        $this->mockGet([
            'response_code' => 'SUCCESS',
            'data'          => [
                'balance'   => '4.998000',
                '_currency' => ['currency_name_short' => 'AUD'],
            ],
        ]);

        $provider = $this->createProvider();
        $this->assertSame('4.998000 AUD', $provider->getCredit());

        $connection = $provider->testConnection();
        $this->assertTrue($connection->success);
        $this->assertSame('4.998000', $connection->details['balance']);
        $this->assertSame('AUD', $connection->details['currency']);
    }

    public function testTestConnectionHandlesAuthAndProviderErrors(): void
    {
        $this->configureProvider();
        $this->mockGet(['response_msg' => 'Unauthorized'], 401);

        $authFailure = $this->createProvider()->testConnection();
        $this->assertFalse($authFailure->success);
        $this->assertStringContainsString('Invalid ClickSend', $authFailure->message);

        $this->mockGet(['response_code' => 'ACCOUNT_NOT_ACTIVATED', 'response_msg' => 'Account not activated']);
        $providerFailure = $this->createProvider()->testConnection();
        $this->assertFalse($providerFailure->success);
        $this->assertSame('Account not activated', $providerFailure->message);
    }

    public function testSendFailsForMissingCredentialsInvalidAuthProviderErrorAndBlockedMessage(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertFalse($this->createProvider()->send($this->message())->success);

        $this->configureProvider();
        $this->mockPost(['response_msg' => 'Unauthorized'], 401);
        $authFailure = $this->createProvider()->send($this->message());
        $this->assertFalse($authFailure->success);
        $this->assertStringContainsString('Invalid ClickSend', $authFailure->error);

        $this->mockPost(['response_code' => 'INVALID_RECIPIENT', 'response_msg' => 'Invalid recipient']);
        $providerFailure = $this->createProvider()->send($this->message());
        $this->assertFalse($providerFailure->success);
        $this->assertSame('Invalid recipient', $providerFailure->error);

        $this->mockPost([
            'response_code' => 'SUCCESS',
            'response_msg'  => 'Messages queued for delivery.',
            'data'          => [
                'blocked_count' => 1,
                'messages'      => [[
                    'message_id'  => 'blocked-1',
                    'status'      => 'INVALID_RECIPIENT',
                    'status_text' => 'Recipient blocked',
                    'error_code'  => 'INVALID_RECIPIENT',
                ]],
            ],
        ]);

        $blocked = $this->createProvider()->send($this->message());
        $this->assertFalse($blocked->success);
        $this->assertSame('Recipient blocked', $blocked->error);
        $this->assertSame('INVALID_RECIPIENT', $blocked->meta['clicksend_status']);
        $this->assertSame(1, $blocked->meta['blocked_count']);
    }

    public function testStatusCallbackTokenValidationAndParsing(): void
    {
        $this->configureProvider(webhookToken: 'secret-token');
        $provider = $this->createProvider();

        $this->assertFalse($provider->validateStatusCallback($this->request()));
        $this->assertFalse($provider->validateStatusCallback($this->request(['token' => 'wrong'])));
        $this->assertTrue($provider->validateStatusCallback($this->request(['token' => 'secret-token'])));
        $this->assertStringContainsString('token=secret-token', $provider->getStatusCallbackUrl());

        $updates = $provider->parseStatusCallback($this->request(['token' => 'secret-token'], [
            'message_id'  => 'dlr-1',
            'status_code' => '201',
            'status_text' => 'Success: Message received on handset.',
            'error_code'  => null,
            'error_text'  => null,
        ]));

        $this->assertCount(1, $updates);
        $this->assertSame('dlr-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testStatusCallbackNormalizesPermanentFailures(): void
    {
        $this->configureProvider();

        $updates = $this->createProvider()->parseStatusCallback($this->request([
            'message_id'  => 'dlr-failed',
            'status_code' => '301',
            'status_text' => 'Rejected by the recipient network',
            'error_code'  => '301',
            'error_text'  => 'Rejected',
        ]));

        $this->assertSame('failed', $updates[0]->status);
        $this->assertSame('301', $updates[0]->errorCode);
        $this->assertSame('Rejected', $updates[0]->errorMessage);
        $this->assertTrue($updates[0]->permanent);
    }

    public function testStatusCallbackReturnsEmptyWhenMessageIdMissing(): void
    {
        $this->configureProvider();

        $this->assertSame([], $this->createProvider()->parseStatusCallback($this->request([
            'status_code' => '201',
        ])));
    }

    public function testInboundTokenValidationAndParsedFields(): void
    {
        $this->configureProvider(webhookToken: 'secret-token');
        $provider = $this->createProvider();

        $this->assertFalse($provider->validateInboundCallback($this->request()));
        $this->assertTrue($provider->validateInboundCallback($this->request(['token' => 'secret-token'])));
        $this->assertStringContainsString('token=secret-token', $provider->getInboundCallbackUrl());

        $messages = $provider->parseInboundCallback($this->request(['token' => 'secret-token'], [
            'message_id'          => 'inbound-1',
            'original_message_id' => 'outbound-1',
            'from'                => '+15559876543',
            'to'                  => '+15551234567',
            'body'                => 'Reply body',
            'timestamp'           => 1722565661,
        ]));

        $this->assertCount(1, $messages);
        $this->assertSame('inbound-1', $messages[0]->providerId);
        $this->assertSame('outbound-1', $messages[0]->meta['original_message_id']);
        $this->assertSame('+15559876543', $messages[0]->from);
        $this->assertSame('+15551234567', $messages[0]->to);
        $this->assertSame('Reply body', $messages[0]->body);
        $this->assertSame(1722565661, $messages[0]->meta['timestamp']);
    }

    public function testInboundReturnsEmptyWhenFromMissing(): void
    {
        $this->configureProvider();

        $this->assertSame([], $this->createProvider()->parseInboundCallback($this->request([
            'message_id' => 'inbound-1',
        ])));
    }
}
