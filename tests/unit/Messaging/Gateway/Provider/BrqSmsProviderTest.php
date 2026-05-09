<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BrqSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BrqSmsProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'brqsms-test-api-key';
    private const SENDER = 'WPSMS';
    private const RECIPIENT = '249912345678';

    protected function createProvider(): AbstractProvider
    {
        return new BrqSmsProvider();
    }

    private function configure(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'brqsms' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['sms' => ['sender_id' => self::SENDER]],
            ],
        ];
    }

    private function createMessage(string $body = 'Hello'): Message
    {
        return new Message('sms', self::RECIPIENT, $body, null, []);
    }

    private function mockHttpGet(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    /**
     * Capture the URL hit by httpGet so we can assert query parameters.
     * @param-out string|null $capturedUrl
     */
    private function mockHttpGetCapturing(string $body, int $statusCode, ?string &$capturedUrl): void
    {
        $capturedUrl = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url) use ($body, $statusCode, &$capturedUrl) {
            $capturedUrl = $url;
            return [
                'body'     => $body,
                'response' => ['code' => $statusCode],
            ];
        };
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('brqsms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BrqSmsProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $sender = $schema['channels']['sms']['sender_id'];
        $this->assertSame('string', $sender['type']);
        $this->assertTrue($sender['required']);
    }

    // --- Send ---

    public function testSendSuccessReturnsProviderIdFromPositiveInteger(): void
    {
        $this->configure();
        $this->mockHttpGet('12345');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('12345', $result->providerId);
    }

    public function testSendNegativeIntegerReturnsMappedError(): void
    {
        $this->configure();
        $this->mockHttpGet('-102');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication failed', $result->error);
        $this->assertSame('-102', $result->meta['brqsms_code']);
    }

    public function testSendUnicodeFlagIsOneForNonAsciiBody(): void
    {
        $this->configure();
        $capturedUrl = null;
        $this->mockHttpGetCapturing('1', 200, $capturedUrl);

        $this->createProvider()->send($this->createMessage('مرحبا'));

        $this->assertNotNull($capturedUrl);
        parse_str(parse_url($capturedUrl, PHP_URL_QUERY), $query);
        $this->assertSame('1', $query['unicode']);
    }

    public function testSendUnicodeFlagIsZeroForAsciiBody(): void
    {
        $this->configure();
        $capturedUrl = null;
        $this->mockHttpGetCapturing('1', 200, $capturedUrl);

        $this->createProvider()->send($this->createMessage('Hello world'));

        $this->assertNotNull($capturedUrl);
        parse_str(parse_url($capturedUrl, PHP_URL_QUERY), $query);
        $this->assertSame('0', $query['unicode']);
    }

    public function testSendNon200ReturnsBodyAsError(): void
    {
        $this->configure();
        $this->mockHttpGet('upstream', 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('upstream', $result->error);
    }

    public function testSendFailsWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'brqsms' => [
                'shared'   => [],
                'channels' => ['sms' => ['sender_id' => self::SENDER]],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key', $result->error);
    }

    public function testSendFailsWhenSenderMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'brqsms' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsBalanceFromJson(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['balance' => '42.50']));

        $this->assertSame('42.50', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnAuthError(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['message' => '-102']));

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnHttpError(): void
    {
        $this->configure();
        $this->mockHttpGet('boom', 500);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionSucceedsWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['balance' => '100.00']));

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('100.00', $result->message);
        $this->assertSame('100.00', $result->details['credit']);
    }

    public function testTestConnectionErrorsOnAuthFailure(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['message' => '-102']));

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Account not exist', $result->message);
    }

    public function testTestConnectionErrorsWithoutCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key', $result->message);
    }
}
