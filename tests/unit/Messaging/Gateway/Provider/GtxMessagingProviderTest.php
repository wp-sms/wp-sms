<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\GtxMessagingProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class GtxMessagingProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'aaaaaaaa-bbbb-cccc-dddd-1234567890ab';
    private const FROM = 'YourBrand';

    protected function createProvider(): AbstractProvider
    {
        return new GtxMessagingProvider();
    }

    private function configure(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gtxmessaging' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['sms' => ['from' => self::FROM]],
            ],
        ];
    }

    private function createMessage(string $recipient = '+15559876543', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body, null, []);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(GtxMessagingProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('gtxmessaging', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    // --- Send ---

    public function testSendQueuesMessageOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'message-count'  => 1,
            'message-status' => 'OK',
            'message-id'     => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('a1b2c3d4-e5f6-7890-abcd-ef1234567890', $result->providerId);
    }

    public function testSendBuildsCorrectUrlAndBody(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'message-count'  => 1,
            'message-status' => 'OK',
            'message-id'     => 'msg-1',
        ]);

        $this->createProvider()->send($this->createMessage('+15559876543', 'Hi there'));

        $this->assertSame(
            'https://rest.gtx-messaging.net/smsc/sendsms/' . rawurlencode(self::API_KEY) . '/json',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::FROM, $body['from']);
        $this->assertSame('+15559876543', $body['to']);
        $this->assertSame('Hi there', $body['text']);
    }

    public function testSendFailsWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['message-status' => 'ERROR'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
        $this->assertStringContainsString('GTX', $result->error);
    }

    public function testSendReturnsFailedOn400WithFieldErrors(): void
    {
        $this->configure();
        $this->mockHttpPost(['from' => ["can't be blank"]], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString("can't be blank", $result->error);
        $this->assertStringContainsString('from', $result->error);
    }

    public function testSendReturnsFailedOnNetworkError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'cURL error 28: timeout');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('timeout', $result->error);
    }

    // --- Test connection ---

    public function testTestConnectionPassesOn400(): void
    {
        $this->configure();
        $this->mockHttpPost(['from' => ["can't be blank"], 'to' => ["invalid"]], 400);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('accepted', $result->message);
    }

    public function testTestConnectionFailsOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['message-status' => 'ERROR'], 401);

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

    // --- Credit ---

    public function testGetCreditReturnsNull(): void
    {
        $this->configure();
        $this->assertNull($this->createProvider()->getCredit());
    }
}
