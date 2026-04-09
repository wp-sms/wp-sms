<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsIrProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsIrProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(): AbstractProvider
    {
        return new SmsIrProvider();
    }

    private function configureProvider(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsir' => [
                'shared' => [
                    'api_key' => 'test_api_key_123',
                ],
                'channels' => [
                    'sms' => ['line_number' => '30001234567890'],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '09121234567', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
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

    // --- Send tests ---

    public function testSendReturnsSuccessWithMessageId(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'  => 1,
            'message' => 'موفق',
            'data'    => [
                'packId'     => 'abc-123',
                'messageIds' => [86522023],
                'cost'       => 2.0,
            ],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('86522023', $result->providerId);
        $this->assertSame(2.0, $result->cost);
    }

    public function testSendReturnsFailedOnApiError(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'  => 0,
            'message' => 'اعتبار کافی نیست',
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('اعتبار کافی نیست', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendPassesCorrectPayload(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status' => 1,
            'data'   => ['messageIds' => [123], 'cost' => 1.0],
        ]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage('09121234567', 'Test message'));

        $lastArgs = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('test_api_key_123', $lastArgs['headers']['X-API-KEY']);
        $this->assertSame('application/json', $lastArgs['headers']['Content-Type']);

        $body = json_decode($lastArgs['body'], true);
        $this->assertSame('30001234567890', $body['lineNumber']);
        $this->assertSame('Test message', $body['messageText']);
        $this->assertSame(['09121234567'], $body['mobiles']);
    }

    public function testSendUrlPointsToBulkEndpoint(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status' => 1,
            'data'   => ['messageIds' => [1]],
        ]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage());

        $lastUrl = $GLOBALS['_test_wp_remote_post_last_url'];
        $this->assertSame('https://api.sms.ir/v1/send/bulk', $lastUrl);
    }

    // --- Credit tests ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status' => 1,
            'data'   => 12345.50,
        ]);

        $provider = $this->createProvider();
        $credit = $provider->getCredit();

        $this->assertSame('12345.5', $credit);
    }

    public function testGetCreditReturnsNullOnApiFailure(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status'  => 0,
            'message' => 'Error',
        ]);

        $provider = $this->createProvider();
        $this->assertNull($provider->getCredit());
    }

    // --- Test connection tests ---

    public function testTestConnectionReturnsOkWithCredit(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status' => 1,
            'data'   => 500.25,
        ]);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500.25', $result->message);
        $this->assertSame('500.25', $result->details['credit']);
    }

    public function testTestConnectionReturnsErrorOnInvalidKey(): void
    {
        $this->configureProvider();
        $this->mockHttpGet(['message' => 'Unauthorized'], 401);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API key', $result->message);
    }

    public function testTestConnectionReturnsErrorWhenKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key is required', $result->message);
    }

    // --- Metadata & features tests ---

    public function testMetadataHasExpectedKeys(): void
    {
        $provider = $this->createProvider();
        $metadata = $provider->getMetadata();

        $this->assertArrayHasKey('description', $metadata);
        $this->assertArrayHasKey('website', $metadata);
        $this->assertArrayHasKey('regions', $metadata);
        $this->assertContains('IR', $metadata['regions']);
    }

    public function testFeaturesIncludesTestConnection(): void
    {
        $provider = $this->createProvider();
        $features = $provider->getFeatures();

        $this->assertTrue($features['test_connection']);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configureProvider();

        $provider = $this->createProvider();
        $this->assertTrue($provider->isConfigured());
    }
}
