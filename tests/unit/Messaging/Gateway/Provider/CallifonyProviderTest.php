<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\CallifonyProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class CallifonyProviderTest extends AbstractProviderTestCase
{
    private const USERNAME  = 'callifony-user';
    private const PASSWORD  = 's3cret!&password';
    private const SENDER_ID = 'WSMSAB';

    protected function createProvider(): AbstractProvider
    {
        return new CallifonyProvider();
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

    private function configure(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'callifony' => [
                'shared'   => [
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ],
                'channels' => [
                    'sms' => ['from' => self::SENDER_ID],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+971501234567', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(CallifonyProvider::TESTED);
    }

    public function testGetIdReturnsSlug(): void
    {
        $this->assertSame('callifony', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    // --- doSend ---

    public function testDoSendSuccessReturnsSentWithProviderId(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['ErrorCode' => 0, 'MessageId' => 'msg-abc-123']));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('msg-abc-123', $result->providerId);
    }

    public function testDoSendSuccessWithoutMessageIdLeavesProviderIdNull(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['ErrorCode' => 0]));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertNull($result->providerId);
    }

    public function testDoSendErrorReturnsFailedWithMappedMessage(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['ErrorCode' => -5]));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid Credentials', $result->error);
        $this->assertSame(-5, $result->meta['callifony_code']);
    }

    public function testDoSendUnknownErrorCodeFallsBack(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['ErrorCode' => -99]));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('-99', $result->error);
    }

    public function testDoSendBuildsExpectedUrlAndJsonBody(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['ErrorCode' => 0]));

        $this->createProvider()->send($this->createMessage('+971501234567', 'Hello world'));

        $url = $GLOBALS['_test_wp_remote_post_last_url'];
        $this->assertStringStartsWith('https://push.globalsms.ae/HTTP/api/Client/SendSMS', $url);

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $query);
        $this->assertSame(self::USERNAME, $query['username']);
        $this->assertSame(self::PASSWORD, $query['password']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::SENDER_ID, $body['source']);
        $this->assertSame('971501234567', $body['destination']);
        $this->assertSame('Hello world', $body['text']);
        $this->assertSame(1, $body['dataCoding']);
    }

    public function testDoSendStripsLeadingPlusFromDestination(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['ErrorCode' => 0]));

        $this->createProvider()->send($this->createMessage('+971501234567', 'Hi'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('971501234567', $body['destination']);
    }

    public function testDoSendSetsDataCoding1ForAsciiBody(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['ErrorCode' => 0]));

        $this->createProvider()->send($this->createMessage('+971501234567', 'plain ascii text'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(1, $body['dataCoding']);
    }

    public function testDoSendSetsDataCoding8ForEmoji(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['ErrorCode' => 0]));

        $this->createProvider()->send($this->createMessage('+971501234567', 'Hello 🌍'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(8, $body['dataCoding']);
    }

    public function testDoSendSetsDataCoding8ForArabic(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode(['ErrorCode' => 0]));

        $this->createProvider()->send($this->createMessage('+971501234567', 'مرحبا'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(8, $body['dataCoding']);
    }

    public function testDoSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsBalanceFromResponse(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['Balance' => '493.20']));

        $this->assertSame('493.20', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenApiReturnsErrorCode(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['ErrorCode' => -5]));

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionSucceedsWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['Balance' => '493.20']));

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('493.20', $result->message);
        $this->assertSame('493.20', $result->details['balance']);
    }

    public function testTestConnectionFailsOnBadCredentials(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['ErrorCode' => -5]));

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertSame('Invalid Credentials', $result->message);
    }

    public function testTestConnectionFailsOnNetworkError(): void
    {
        $this->configure();
        // Default: no _test_wp_remote_get mock → returns WP_Error → DeliveryResult error path

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
