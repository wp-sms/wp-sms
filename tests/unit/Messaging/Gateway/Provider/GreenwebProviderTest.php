<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\GreenwebProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class GreenwebProviderTest extends AbstractProviderTestCase
{
    private const API_TOKEN = 'gw_test_token_abcdef';

    protected function createProvider(): AbstractProvider
    {
        return new GreenwebProvider();
    }

    private function configure(array $sharedOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'greenweb' => [
                'shared'   => array_merge(['api_token' => self::API_TOKEN], $sharedOverrides),
                'channels' => ['sms' => []],
            ],
        ];
    }

    private function createMessage(string $recipient = '+8801711000000', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => is_string($responseBody) ? $responseBody : json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => is_string($responseBody) ? $responseBody : json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testIdIsGreenweb(): void
    {
        $this->assertSame('greenweb', $this->createProvider()->getId());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(GreenwebProvider::TESTED);
    }

    public function testSupportedChannelsIsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testConfigSchemaRequiresApiToken(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue($schema['shared']['api_token']['required']);
        // No 'from' / sender field — Greenweb binds sender to the account.
        $this->assertSame([], $schema['channels']['sms']);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send: happy path ---

    public function testSendSuccessParsesSentStatus(): void
    {
        $this->configure();
        $this->mockHttpPost([
            ['to' => '8801711000000', 'message' => 'Hello', 'status' => 'SENT', 'statusmsg' => 'Successfully Sent'],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNull($result->providerId);
    }

    public function testSendIncludesTokenInBody(): void
    {
        $this->configure();
        $this->mockHttpPost([
            ['to' => '8801711000000', 'status' => 'SENT', 'statusmsg' => 'OK'],
        ]);

        $this->createProvider()->send($this->createMessage());

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertIsArray($body, 'Body should be an array so WP serialises as form-urlencoded');
        $this->assertSame(self::API_TOKEN, $body['token']);
        $this->assertSame('+8801711000000', $body['to']);
        $this->assertSame('Hello', $body['message']);
    }

    public function testSendUsesJsonEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost([
            ['to' => '8801711000000', 'status' => 'SENT', 'statusmsg' => 'OK'],
        ]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://api.greenweb.com.bd/api.php?json',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    // --- Send: errors ---

    public function testSendReturnsFailedWhenTokenMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailureParsesFailedStatus(): void
    {
        $this->configure();
        $this->mockHttpPost([
            ['to' => '8801711000000', 'status' => 'FAILED', 'statusmsg' => 'Invalid Number'],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid Number', $result->error);
    }

    public function testSendHandles401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendHandlesNon2xx(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'Server error'], 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('HTTP 500', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalanceString(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => '49.50']);

        $this->assertSame('49.50', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsBalanceFromArrayShape(): void
    {
        $this->configure();
        $this->mockHttpGet([['balance' => '12.34']]);

        $this->assertSame('12.34', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnHttpError(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'oops'], 500);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $this->mockHttpGet(['balance' => '100.00']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('100.00', $result->message);
        $this->assertSame('100.00', $result->details['balance']);
    }

    public function testTestConnectionInvalidToken(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionMissingToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
