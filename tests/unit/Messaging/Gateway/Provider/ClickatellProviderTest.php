<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\ClickatellProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class ClickatellProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'test-api-key-123';
    private const TOKEN   = 'webhook-secret-token';

    protected function createProvider(): AbstractProvider
    {
        return new ClickatellProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_get_last_url'],
            $GLOBALS['_test_wp_remote_get_last_args'],
        );

        parent::tearDown();
    }

    private function configureProvider(?string $token = self::TOKEN, array $channelOverrides = []): void
    {
        $shared = ['api_key' => self::API_KEY];
        if ($token !== null) {
            $shared['callback_token'] = $token;
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'clickatell' => [
                'shared'   => $shared,
                'channels' => array_merge([
                    'sms'      => ['from' => '+15551234567'],
                    'whatsapp' => ['from' => '+15557654321'],
                ], $channelOverrides),
            ],
        ];
    }

    private function message(string $channel = 'sms'): Message
    {
        return new Message($channel, '+15559876543', 'Hello Clickatell');
    }

    private function mockPost(array $body, int $code = 202): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => wp_json_encode($body),
            'response' => ['code' => $code],
        ];
    }

    private function mockGet(array $body, int $code = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => wp_json_encode($body),
            'response' => ['code' => $code],
        ];
    }

    private function request(array $params = [], ?array $json = null): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/clickatell/status');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        if ($json !== null) {
            $request->set_body(wp_json_encode($json));
        }
        return $request;
    }

    public function testIdAndChannels(): void
    {
        $provider = $this->createProvider();
        $this->assertSame('clickatell', $provider->getId());
        $this->assertSame(['sms', 'whatsapp'], $provider->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(ClickatellProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertTrue($schema['shared']['api_key']['required']);
        $this->assertTrue($schema['shared']['callback_token']['required']);
        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertArrayHasKey('from', $schema['channels']['whatsapp']);

        // No select fields in this provider — but enforce no associative-options regression.
        foreach ($schema['shared'] as $field) {
            if (($field['type'] ?? '') === 'select' && isset($field['options'])) {
                $this->assertIsList($field['options']);
            }
        }
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key', $result->error);
    }

    public function testSendQueuedOnHttp202(): void
    {
        $this->configureProvider();
        $this->mockPost([
            'messages' => [[
                'apiMessageId' => 'abc-123',
                'accepted'     => true,
                'to'           => '+15559876543',
            ]],
            'type'     => 'SUCCESS',
        ]);

        $result = $this->createProvider()->send($this->message());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('abc-123', $result->providerId);
        $this->assertSame('https://platform.clickatell.com/v1/message', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        // Authorization is the raw key — no Bearer prefix.
        $this->assertSame(self::API_KEY, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);
    }

    public function testSendFailedOnPerMessageError(): void
    {
        $this->configureProvider();
        $this->mockPost([
            'messages' => [[
                'accepted' => false,
                'to'       => '+15559876543',
                'error'    => ['code' => 26, 'description' => 'Recipient opted out'],
            ]],
            'type'     => 'FAIL',
        ], 207);

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertSame('Recipient opted out', $result->error);
        $this->assertSame(26, $result->meta['provider_code']);
        $this->assertFalse($result->retryable, '26 is permanent (opt-out)');
    }

    public function testSendFailedOn401(): void
    {
        $this->configureProvider();
        $this->mockPost(['error' => ['description' => 'Unauthorized']], 401);

        $result = $this->createProvider()->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendUsesSmsChannelByDefault(): void
    {
        $this->configureProvider();
        $this->mockPost([
            'messages' => [['apiMessageId' => 'sms-1', 'accepted' => true]],
        ]);

        $this->createProvider()->send($this->message('sms'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('sms', $body['messages'][0]['channel']);
        $this->assertSame('+15559876543', $body['messages'][0]['to']);
        $this->assertSame('+15551234567', $body['messages'][0]['from']);
    }

    public function testSendUsesWhatsappChannel(): void
    {
        $this->configureProvider();
        $this->mockPost([
            'messages' => [['apiMessageId' => 'wa-1', 'accepted' => true]],
        ]);

        $this->createProvider()->send($this->message('whatsapp'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('whatsapp', $body['messages'][0]['channel']);
        $this->assertSame('+15557654321', $body['messages'][0]['from']);
    }

    public function testGetCreditFormatsBalance(): void
    {
        $this->configureProvider();
        $this->mockGet(['balance' => 12.5, 'currency' => 'USD']);

        $this->assertSame('12.50 USD', $this->createProvider()->getCredit());
    }

    public function testTestConnectionOk(): void
    {
        $this->configureProvider();
        $this->mockGet(['balance' => 12.5, 'currency' => 'USD']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('12.50', $result->details['balance']);
        $this->assertSame('USD', $result->details['currency']);
    }

    public function testTestConnectionInvalidKey(): void
    {
        $this->configureProvider();
        $this->mockGet(['error' => 'unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configureProvider();
        $this->assertFalse($this->createProvider()->validateStatusCallback($this->request()));
    }

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configureProvider();
        $this->assertTrue($this->createProvider()->validateStatusCallback($this->request(['token' => self::TOKEN])));
    }

    public function testParseStatusCallbackMapsStatusCodes(): void
    {
        $this->configureProvider();

        $updates = $this->createProvider()->parseStatusCallback($this->request(
            ['token' => self::TOKEN],
            [
                'event' => [
                    'messageStatusUpdate' => [
                        ['messageId' => 'm-1', 'statusCode' => 5, 'channel' => 'sms', 'timestamp' => 1000],
                        ['messageId' => 'm-2', 'statusCode' => 1, 'channel' => 'sms', 'timestamp' => 1001],
                    ],
                ],
            ],
        ));

        $this->assertCount(2, $updates);
        $this->assertSame('m-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);

        $this->assertSame('m-2', $updates[1]->providerId);
        $this->assertSame('failed', $updates[1]->status);
    }

    public function testParseStatusCallbackMarksOptOutPermanent(): void
    {
        $this->configureProvider();

        $updates = $this->createProvider()->parseStatusCallback($this->request(
            ['token' => self::TOKEN],
            [
                'event' => [
                    'messageStatusUpdate' => [[
                        'messageId'  => 'm-opt',
                        'statusCode' => 1,
                        'error'      => ['code' => 26, 'description' => 'Recipient opted out'],
                    ]],
                ],
            ],
        ));

        $this->assertCount(1, $updates);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertSame('26', $updates[0]->errorCode);
        $this->assertTrue($updates[0]->permanent);
    }

    public function testValidateInboundCallbackRejectsMissingToken(): void
    {
        $this->configureProvider();
        $this->assertFalse($this->createProvider()->validateInboundCallback($this->request()));
    }

    public function testParseInboundCallbackExtractsMoText(): void
    {
        $this->configureProvider();

        $messages = $this->createProvider()->parseInboundCallback($this->request(
            ['token' => self::TOKEN],
            [
                'event' => [
                    'moText' => [[
                        'messageId' => 'mo-1',
                        'from'      => '+15559876543',
                        'to'        => '+15551234567',
                        'content'   => 'Hello back',
                        'channel'   => 'sms',
                        'timestamp' => 1722565661,
                    ]],
                ],
            ],
        ));

        $this->assertCount(1, $messages);
        $this->assertSame('mo-1', $messages[0]->providerId);
        $this->assertSame('+15559876543', $messages[0]->from);
        $this->assertSame('+15551234567', $messages[0]->to);
        $this->assertSame('Hello back', $messages[0]->body);
        $this->assertSame('sms', $messages[0]->meta['channel']);
    }

    public function testIsOptOutErrorTrueForCode26(): void
    {
        $this->configureProvider();
        $result = DeliveryResult::failed('opted out', ['provider_code' => 26]);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForOtherCodes(): void
    {
        $this->configureProvider();
        $result = DeliveryResult::failed('other', ['provider_code' => 20]);
        $this->assertFalse($this->createProvider()->isOptOutError($result));

        $resultNone = DeliveryResult::failed('no code');
        $this->assertFalse($this->createProvider()->isOptOutError($resultNone));
    }

    public function testGetStatusCallbackUrlIncludesToken(): void
    {
        $this->configureProvider();
        $this->assertStringContainsString('token=' . self::TOKEN, $this->createProvider()->getStatusCallbackUrl());
    }

    public function testGetInboundCallbackUrlIncludesToken(): void
    {
        $this->configureProvider();
        $this->assertStringContainsString('token=' . self::TOKEN, $this->createProvider()->getInboundCallbackUrl());
    }
}
