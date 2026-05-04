<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\FarazSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class FarazSmsProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(): AbstractProvider
    {
        return new FarazSmsProvider();
    }

    private function configureProvider(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'farazsms' => [
                'shared' => [
                    'api_key' => 'test_api_key_123',
                ],
                'channels' => [
                    'sms' => ['sender' => '50001234567890'],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '09121234567', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, meta: $meta);
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

    // --- Send tests (raw SMS) ---

    public function testSendReturnsSuccessWithMessageId(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'  => 'success',
            'message' => 'Ok',
            'data'    => ['message_id' => 'abc-123'],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('abc-123', $result->providerId);
    }

    public function testSendReturnsFailedOnApiError(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'  => 'error',
            'message' => 'Insufficient balance',
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('Insufficient balance', $result->error);
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
            'status' => 'success',
            'data'   => ['message_id' => '1'],
        ]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage('09121234567', 'Test message'));

        $this->assertSame(
            'https://api.iranpayamak.com/ws/v1/sms/simple',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $lastArgs = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('test_api_key_123', $lastArgs['headers']['Api-Key']);
        $this->assertSame('application/json', $lastArgs['headers']['Content-Type']);

        $body = json_decode($lastArgs['body'], true);
        $this->assertSame('Test message', $body['text']);
        $this->assertSame('50001234567890', $body['line_number']);
        $this->assertSame(['09121234567'], $body['recipients']);
        $this->assertSame('english', $body['number_format']);
    }

    public function testTemplateModeSendUsesPatternEndpoint(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status' => 'success',
            'data'   => ['message_id' => '99887'],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage('09121234567', '', [
            'template_mode'        => true,
            'provider_template_id' => '5555',
            'template_variables'   => ['name' => 'Ali', 'code' => '4321'],
        ]));

        $this->assertTrue($result->success);
        $this->assertSame('99887', $result->providerId);

        $this->assertSame(
            'https://api.iranpayamak.com/ws/v1/sms/pattern',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $lastArgs = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('test_api_key_123', $lastArgs['headers']['Api-Key']);

        $body = json_decode($lastArgs['body'], true);
        $this->assertSame('5555', $body['code']);
        $this->assertSame(['name' => 'Ali', 'code' => '4321'], $body['attributes']);
        $this->assertSame('09121234567', $body['recipient']);
        $this->assertSame('50001234567890', $body['line_number']);
        $this->assertSame('english', $body['number_format']);
    }

    // --- Credit tests ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status' => 'success',
            'data'   => ['balance_amount' => 12345.5],
        ]);

        $provider = $this->createProvider();
        $this->assertSame('12345.5', $provider->getCredit());
    }

    public function testGetCreditReturnsNullWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $this->assertNull($provider->getCredit());
    }

    // --- Test connection tests ---

    public function testTestConnectionSuccess(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status' => 'success',
            'data'   => ['balance_amount' => '500'],
        ]);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500', $result->message);
        $this->assertSame('500', $result->details['credit']);
    }

    public function testTestConnectionFailureOnError(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status'  => 'error',
            'message' => 'Account suspended',
        ]);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Account suspended', $result->message);
    }

    public function testTestConnectionFailsOn401(): void
    {
        $this->configureProvider();
        $this->mockHttpGet(['message' => 'Unauthorized'], 401);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API key', $result->message);
    }

    // --- Tested-flag pin ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(FarazSmsProvider::TESTED);
    }
}
