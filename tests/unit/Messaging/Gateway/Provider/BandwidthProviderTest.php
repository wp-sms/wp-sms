<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BandwidthProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BandwidthProviderTest extends AbstractProviderTestCase
{
    private const ACCOUNT_ID = '9900000';
    private const API_TOKEN = 'bw-token';
    private const API_SECRET = 'bw-secret';
    private const SMS_FROM = '+19195551212';
    private const APPLICATION_ID = '93de2206-9669-4e07-948d-329f4b722ee2';
    private const RCS_SENDER = 'MyBrand';
    private const CALLBACK_USER = 'cb-user';
    private const CALLBACK_PASS = 'cb-pass';

    protected function createProvider(): AbstractProvider
    {
        return new BandwidthProvider();
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

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bandwidth' => [
                'shared' => array_merge([
                    'account_id'        => self::ACCOUNT_ID,
                    'api_token'         => self::API_TOKEN,
                    'api_secret'        => self::API_SECRET,
                    'callback_username' => self::CALLBACK_USER,
                    'callback_password' => self::CALLBACK_PASS,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms' => [
                        'from_number'    => self::SMS_FROM,
                        'application_id' => self::APPLICATION_ID,
                    ],
                    'rcs' => [
                        'sender_id'         => self::RCS_SENDER,
                        'application_id'    => self::APPLICATION_ID,
                        'sms_fallback_from' => self::SMS_FROM,
                    ],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+19195550000', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 202): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    private function expectedBasicAuth(): string
    {
        return 'Basic ' . base64_encode(self::API_TOKEN . ':' . self::API_SECRET);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();

        $this->assertSame('bandwidth', $p->getId());
        $this->assertSame(['sms', 'rcs'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BandwidthProvider::TESTED);
    }

    public function testConfigSchemaContainsCredentialsAndChannels(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('account_id', $schema['shared']);
        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_secret']['type']);
        $this->assertArrayHasKey('callback_username', $schema['shared']);
        $this->assertArrayHasKey('from_number', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from_number']['dynamic']);
        $this->assertArrayHasKey('sender_id', $schema['channels']['rcs']);
    }

    public function testIsConfiguredForEachChannelIndependently(): void
    {
        $this->configure(channelOverrides: ['rcs' => []]);

        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('sms'));
        $this->assertFalse($p->isConfiguredForChannel('rcs'));
    }

    public function testSmsSendPostsBandwidthMessageRequest(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 'msg-123']);

        $result = $this->createProvider()->send($this->createMessage('sms', '+19195550001', 'Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-123', $result->providerId);
        $this->assertSame(
            'https://messaging.bandwidth.com/api/v2/users/' . self::ACCOUNT_ID . '/messages',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedBasicAuth(), $args['headers']['Authorization']);
        $this->assertSame('application/json; charset=utf-8', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(['+19195550001'], $body['to']);
        $this->assertSame(self::SMS_FROM, $body['from']);
        $this->assertSame('Hi', $body['text']);
        $this->assertSame(self::APPLICATION_ID, $body['applicationId']);
    }

    public function testSmsSendIncludesMediaUrlsTagAndPriority(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 'msg-media']);

        $this->createProvider()->send($this->createMessage('sms', '+19195550001', 'Photo', [
            'media_urls' => ['https://example.com/a.jpg'],
            'tag'        => 'order-123',
            'priority'   => 'high',
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(['https://example.com/a.jpg'], $body['media']);
        $this->assertSame('order-123', $body['tag']);
        $this->assertSame('high', $body['priority']);
    }

    public function testRcsSendUsesMultiChannelRbmPayloadWithSmsFallback(): void
    {
        $this->configure();
        $this->mockHttpPost(['data' => ['id' => 'mc-123']]);

        $result = $this->createProvider()->send($this->createMessage('rcs', '+19195550001', 'Rich hello', [
            'media_urls' => ['https://example.com/hero.png'],
            'tag'        => 'rcs-1',
        ]));

        $this->assertTrue($result->success);
        $this->assertSame('mc-123', $result->providerId);
        $this->assertSame(
            'https://messaging.bandwidth.com/api/v2/users/' . self::ACCOUNT_ID . '/messages/multiChannel',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('+19195550001', $body['to']);
        $this->assertSame('rcs-1', $body['tag']);
        $this->assertCount(2, $body['channelList']);
        $this->assertSame('RBM', $body['channelList'][0]['channel']);
        $this->assertSame(self::RCS_SENDER, $body['channelList'][0]['from']);
        $this->assertSame('Rich hello', $body['channelList'][0]['content']['text']);
        $this->assertSame('https://example.com/hero.png', $body['channelList'][0]['content']['media'][0]['fileUrl']);
        $this->assertSame('SMS', $body['channelList'][1]['channel']);
        $this->assertSame(self::SMS_FROM, $body['channelList'][1]['from']);
    }

    public function testRcsSendOmitsFallbackWhenNotConfigured(): void
    {
        $this->configure(channelOverrides: [
            'rcs' => [
                'sender_id'      => self::RCS_SENDER,
                'application_id' => self::APPLICATION_ID,
            ],
        ]);
        $this->mockHttpPost(['data' => ['id' => 'mc-124']]);

        $this->createProvider()->send($this->createMessage('rcs'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertCount(1, $body['channelList']);
    }

    public function testSendReturnsFailedOnMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedOnProviderErrorAndCapturesCode(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'type'        => 'request-validation',
            'description' => 'Rejected due to user opt-out',
            'errorCode'   => 4475,
        ], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Rejected due to user opt-out', $result->error);
        $this->assertSame('4475', $result->meta['bandwidth_error_code']);
    }

    public function testSendMarksRateLimitRetryable(): void
    {
        $this->configure();
        $this->mockHttpPost(['description' => 'Too many requests'], 429);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    public function testIsOptOutErrorTrueForBandwidth4475(): void
    {
        $result = DeliveryResult::failed('opted out', ['bandwidth_error_code' => '4475']);

        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForOtherErrors(): void
    {
        $result = DeliveryResult::failed('spam', ['bandwidth_error_code' => '4470']);

        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    public function testGetCreditReturnsNull(): void
    {
        $this->configure();

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOk(): void
    {
        $this->configure();
        $this->mockHttpGet(['totalCount' => 0, 'messages' => []]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Connected', $result->message);
        $this->assertSame(self::ACCOUNT_ID, $result->details['account_id']);
    }

    public function testTestConnectionMaps401(): void
    {
        $this->configure();
        $this->mockHttpGet(['type' => 'unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testValidateCallbacksRequireConfiguredBasicAuth(): void
    {
        $this->configure();
        $ok = $this->buildJsonRequest([['type' => 'message-delivered']], [
            'authorization' => 'Basic ' . base64_encode(self::CALLBACK_USER . ':' . self::CALLBACK_PASS),
        ]);
        $bad = $this->buildJsonRequest([['type' => 'message-delivered']], [
            'authorization' => 'Basic ' . base64_encode('wrong:creds'),
        ]);

        $p = $this->createProvider();
        $this->assertTrue($p->validateStatusCallback($ok));
        $this->assertTrue($p->validateInboundCallback($ok));
        $this->assertFalse($p->validateStatusCallback($bad));
        $this->assertFalse($p->validateInboundCallback($bad));
    }

    public function testValidateCallbacksRejectWhenCallbackAuthNotConfigured(): void
    {
        $this->configure(['callback_username' => '', 'callback_password' => '']);
        $request = $this->buildJsonRequest([], [
            'authorization' => 'Basic ' . base64_encode(self::CALLBACK_USER . ':' . self::CALLBACK_PASS),
        ]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsDeliveryAndOptOutFailure(): void
    {
        $request = $this->buildJsonRequest([
            [
                'type'    => 'message-delivered',
                'message' => ['id' => 'msg-1'],
            ],
            [
                'type'        => 'message-failed',
                'description' => 'Rejected due to user opt-out',
                'errorCode'   => 4475,
                'message'     => ['id' => 'msg-2'],
            ],
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(2, $updates);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertSame('failed', $updates[1]->status);
        $this->assertSame('4475', $updates[1]->errorCode);
        $this->assertTrue($updates[1]->permanent);
        $this->assertTrue($updates[1]->unsubscribe);
    }

    public function testParseStatusCallbackIgnoresInboundEvents(): void
    {
        $request = $this->buildJsonRequest([[
            'type'    => 'message-received',
            'message' => ['id' => 'msg-in'],
        ]]);

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseInboundCallbackBuildsMessagesWithMedia(): void
    {
        $request = $this->buildJsonRequest([[
            'type'    => 'message-received',
            'to'      => '+19195551212',
            'message' => [
                'id'            => 'in-1',
                'from'          => '+19195550001',
                'to'            => ['+19195551212'],
                'text'          => 'Hello back',
                'channel'       => 'mms',
                'applicationId' => self::APPLICATION_ID,
                'media'         => ['https://example.com/in.jpg'],
            ],
        ]]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+19195550001', $messages[0]->from);
        $this->assertSame('+19195551212', $messages[0]->to);
        $this->assertSame('Hello back', $messages[0]->body);
        $this->assertSame('in-1', $messages[0]->providerId);
        $this->assertSame(['https://example.com/in.jpg'], $messages[0]->meta['media_urls']);
    }

    public function testParseInboundCallbackReadsRbmContentText(): void
    {
        $request = $this->buildJsonRequest([[
            'type'    => 'message-received',
            'message' => [
                'id'      => 'rbm-in-1',
                'from'    => '+19195550001',
                'to'      => ['Bandwidth'],
                'channel' => 'RBM',
                'content' => ['text' => 'RCS reply'],
            ],
        ]]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertSame('RCS reply', $messages[0]->body);
        $this->assertSame('RBM', $messages[0]->meta['channel']);
    }

    public function testGetConfigOptionsReturnsNumbersFromXml(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<TelephoneNumbersResponse>
  <TelephoneNumbers>
    <TelephoneNumber>19195551212</TelephoneNumber>
    <TelephoneNumber>19195551313</TelephoneNumber>
  </TelephoneNumbers>
</TelephoneNumbersResponse>
XML;

        $captured = [];
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use ($xml, &$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return ['body' => $xml, 'response' => ['code' => 200]];
        };

        $options = $this->createProvider()->getConfigOptions('from_number', 'sms', [
            'shared' => [
                'api_token'  => self::API_TOKEN,
                'api_secret' => self::API_SECRET,
            ],
            'channels' => [],
        ]);

        $this->assertSame('https://api.bandwidth.com/api/tns', $captured['url']);
        $this->assertSame($this->expectedBasicAuth(), $captured['args']['headers']['Authorization']);
        $this->assertSame([
            ['value' => '+19195551212', 'label' => '+19195551212'],
            ['value' => '+19195551313', 'label' => '+19195551313'],
        ], $options);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_id', 'rcs', []));
    }

    private function buildJsonRequest(array $jsonBody, array $headers = []): \WP_REST_Request
    {
        return new class($jsonBody, $headers) extends \WP_REST_Request {
            private array $jsonBody;

            public function __construct(array $jsonBody, array $headers)
            {
                parent::__construct('POST', '/x');
                $this->jsonBody = $jsonBody;
                $this->set_body(json_encode($jsonBody));
                foreach ($headers as $key => $value) {
                    $this->set_header($key, $value);
                }
            }

            public function get_method(): string
            {
                return 'POST';
            }

            public function get_json_params(): array
            {
                return $this->jsonBody;
            }
        };
    }
}
