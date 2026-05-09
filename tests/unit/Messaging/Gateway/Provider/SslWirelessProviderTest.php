<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SslWirelessProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SslWirelessProviderTest extends AbstractProviderTestCase
{
    private const API_TOKEN = 'isms-test-token-xyz';
    private const SID = 'TEST_SID';
    private const RECIPIENT = '8801712345678';

    protected function createProvider(): AbstractProvider
    {
        return new SslWirelessProvider();
    }

    private function configure(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'sslwireless' => [
                'shared'   => [
                    'api_token' => self::API_TOKEN,
                ],
                'channels' => [
                    'sms' => ['sid' => self::SID],
                ],
            ],
        ];
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function createMessage(string $body = 'Hello'): Message
    {
        return new Message('sms', self::RECIPIENT, $body, null, []);
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SslWirelessProvider::TESTED);
    }

    public function testSendSucceedsOnStatusCode200(): void
    {
        $this->configure();
        $this->mockHttpPost(['status_code' => 200, 'error_message' => 'Success']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNotEmpty($result->providerId);
        $this->assertStringStartsWith('wsms_', $result->providerId);
        $this->assertSame($result->providerId, $result->meta['csms_id']);
    }

    public function testSendFailsOnNon200StatusCode(): void
    {
        $this->configure();
        $this->mockHttpPost(['status_code' => 4012, 'error_message' => 'Invalid SID'], 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid SID', $result->error);
    }

    public function testSendFailsWhenApiTokenMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'sslwireless' => [
                'shared'   => [],
                'channels' => ['sms' => ['sid' => self::SID]],
            ],
        ];
        $GLOBALS['_test_wp_remote_post'] = function () {
            throw new \RuntimeException('HTTP must not be called when token is missing');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API token', $result->error);
    }

    public function testSendFailsWhenSidMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'sslwireless' => [
                'shared'   => ['api_token' => self::API_TOKEN],
                'channels' => ['sms' => []],
            ],
        ];
        $GLOBALS['_test_wp_remote_post'] = function () {
            throw new \RuntimeException('HTTP must not be called when SID is missing');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    public function testRequestPostsToV3Endpoint(): void
    {
        $this->configure();
        $this->mockHttpPost(['status_code' => 200, 'error_message' => 'Success']);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://smsplus.sslwireless.com/api/v3/send-sms',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testRequestBodyContainsExpectedJsonFields(): void
    {
        $this->configure();
        $this->mockHttpPost(['status_code' => 200, 'error_message' => 'Success']);

        $this->createProvider()->send($this->createMessage('Hi there'));

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('api_token', $body);
        $this->assertArrayHasKey('sid', $body);
        $this->assertArrayHasKey('msisdn', $body);
        $this->assertArrayHasKey('sms', $body);
        $this->assertArrayHasKey('csms_id', $body);
        $this->assertSame(self::API_TOKEN, $body['api_token']);
        $this->assertSame(self::SID, $body['sid']);
        $this->assertSame(self::RECIPIENT, $body['msisdn']);
        $this->assertSame('Hi there', $body['sms']);
        $this->assertStringStartsWith('wsms_', $body['csms_id']);
    }

    public function testSendHandlesNetworkFailure(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'Connection refused');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Connection refused', $result->error);
    }
}
