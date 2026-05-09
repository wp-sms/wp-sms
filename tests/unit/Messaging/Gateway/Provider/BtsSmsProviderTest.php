<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BtsSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BtsSmsProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'btssms-test-api-key';
    private const SENDER = 'WPSMS';
    private const RECIPIENT = '8801712345678';

    protected function createProvider(): AbstractProvider
    {
        return new BtsSmsProvider();
    }

    private function configure(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'btssms' => [
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
        $this->assertSame('btssms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BtsSmsProvider::TESTED);
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

    public function testSendSuccessReturnsSentOnCode202(): void
    {
        $this->configure();
        $this->mockHttpGet('202');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
    }

    public function testSendSuccessReturnsSentOnCode1001(): void
    {
        $this->configure();
        $this->mockHttpGet('1001');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
    }

    public function testSendCode1010ReturnsAuthError(): void
    {
        $this->configure();
        $this->mockHttpGet('1010');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API Key', $result->error);
        $this->assertSame('1010', $result->meta['btssms_code']);
    }

    public function testSendCode1007ReturnsBalanceError(): void
    {
        $this->configure();
        $this->mockHttpGet('1007');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient balance', $result->error);
        $this->assertSame('1007', $result->meta['btssms_code']);
    }

    public function testSendUnicodeFlagForBengaliBody(): void
    {
        $this->configure();
        $capturedUrl = null;
        $this->mockHttpGetCapturing('202', 200, $capturedUrl);

        $this->createProvider()->send($this->createMessage('হ্যালো'));

        $this->assertNotNull($capturedUrl);
        parse_str(parse_url($capturedUrl, PHP_URL_QUERY), $query);
        $this->assertSame('unicode', $query['type']);
    }

    public function testSendUnicodeFlagAsciiBody(): void
    {
        $this->configure();
        $capturedUrl = null;
        $this->mockHttpGetCapturing('202', 200, $capturedUrl);

        $this->createProvider()->send($this->createMessage('Hello world'));

        $this->assertNotNull($capturedUrl);
        parse_str(parse_url($capturedUrl, PHP_URL_QUERY), $query);
        $this->assertSame('text', $query['type']);
    }

    public function testSendQueryParametersIncludeApiKeySenderNumberMessage(): void
    {
        $this->configure();
        $capturedUrl = null;
        $this->mockHttpGetCapturing('202', 200, $capturedUrl);

        $this->createProvider()->send($this->createMessage('Hello'));

        $this->assertNotNull($capturedUrl);
        parse_str(parse_url($capturedUrl, PHP_URL_QUERY), $query);
        $this->assertSame(self::API_KEY, $query['api_key']);
        $this->assertSame(self::SENDER, $query['senderid']);
        $this->assertSame(self::RECIPIENT, $query['number']);
        $this->assertSame('Hello', $query['message']);
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
            'btssms' => [
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
            'btssms' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsNullByDesign(): void
    {
        $this->configure();

        // No public balance endpoint exists; design pins this to null
        // regardless of any HTTP fixture.
        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionSucceedsOnNonAuthError(): void
    {
        $this->configure();
        $this->mockHttpGet('1003');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
    }

    public function testTestConnectionErrorsOn1010(): void
    {
        $this->configure();
        $this->mockHttpGet('1010');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API Key', $result->message);
    }

    public function testTestConnectionErrorsWithoutCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key', $result->message);
    }
}
