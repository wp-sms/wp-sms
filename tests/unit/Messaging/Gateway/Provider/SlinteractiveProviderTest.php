<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SlinteractiveProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SlinteractiveProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'sli-user@example.com';
    private const PASSWORD = 'sli-pass-9999';
    private const SEND_URL = 'https://www.slinteractive.com.au/api/send_sms.php';

    /** @var array<int, array{url:string,args:array}> */
    private array $getLog = [];

    protected function createProvider(): AbstractProvider
    {
        return new SlinteractiveProvider();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->getLog = [];
        unset($GLOBALS['_test_wp_remote_get']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_get']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'slinteractive' => [
                'shared'   => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => $smsOverrides,
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '61433188903', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockGet(string $body, int $code = 200): void
    {
        $payload = [
            'body'     => $body,
            'response' => ['code' => $code],
        ];

        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use ($payload) {
            $this->getLog[] = ['url' => $url, 'args' => $args];
            return $payload;
        };
    }

    private function parseQuery(string $url): array
    {
        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $query);
        return $query;
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SlinteractiveProvider::TESTED);
    }

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('slinteractive', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertTrue($schema['shared']['password']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['sender_id']['type']);
        $this->assertFalse((bool) ($schema['channels']['sms']['sender_id']['required'] ?? true));
    }

    public function testIsConfiguredWithCredentials(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendSuccessReturnsSentWithNullProviderId(): void
    {
        $this->configure();
        $this->mockGet('Complete:1');

        $result = $this->createProvider()->send($this->createMessage('61433188903', 'Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNull($result->providerId);

        $this->assertCount(1, $this->getLog);
        $this->assertStringStartsWith(self::SEND_URL . '?', $this->getLog[0]['url']);

        $query = $this->parseQuery($this->getLog[0]['url']);
        $this->assertSame(self::USERNAME, $query['uname']);
        $this->assertSame(self::PASSWORD, $query['pword']);
        $this->assertSame('Hi', $query['msg']);
        $this->assertSame('61433188903', $query['to']);
        $this->assertArrayNotHasKey('sid', $query);
    }

    public function testSendIncludesSenderIdWhenConfigured(): void
    {
        $this->configure(smsOverrides: ['sender_id' => 'WSMS']);
        $this->mockGet('Complete:1');

        $this->createProvider()->send($this->createMessage());

        $query = $this->parseQuery($this->getLog[0]['url']);
        $this->assertSame('WSMS', $query['sid']);
    }

    public function testSendOmitsSenderIdWhenBlank(): void
    {
        $this->configure(smsOverrides: ['sender_id' => '']);
        $this->mockGet('Complete:1');

        $this->createProvider()->send($this->createMessage());

        $query = $this->parseQuery($this->getLog[0]['url']);
        $this->assertArrayNotHasKey('sid', $query);
    }

    public function testSendNormalisesRecipientToDigitsOnly(): void
    {
        $this->configure();
        $this->mockGet('Complete:1');

        $this->createProvider()->send($this->createMessage('+61 433 188 903'));

        $query = $this->parseQuery($this->getLog[0]['url']);
        $this->assertSame('61433188903', $query['to']);
    }

    public function testSendAcceptsCompleteWithMultipleRecipients(): void
    {
        $this->configure();
        $this->mockGet('Complete:2');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNull($result->providerId);
    }

    public function testSendFailsOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockGet('I_UNAME_PWORD');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid SL Interactive username/password', $result->error);
    }

    public function testSendFailsOnCreditExhaustionAndExposesRemainingCredit(): void
    {
        $this->configure();
        $this->mockGet('CREDIT:5');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credit', strtolower($result->error));
        $this->assertSame('5', $result->meta['sli_credit_remaining']);
    }

    public function testSendFailsOnInvalidRecipient(): void
    {
        $this->configure();
        $this->mockGet('PHONE_NO');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('invalid recipient', strtolower($result->error));
    }

    public function testSendFailsOnInvalidRecipientWithIndex(): void
    {
        $this->configure();
        $this->mockGet('PHONE_NO:3');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('invalid recipient', strtolower($result->error));
    }

    public function testSendFailsOnMessageLengthRejection(): void
    {
        $this->configure();
        $this->mockGet('M_LENGTH');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('160 characters', $result->error);
    }

    public function testSendFailsOnHttpError(): void
    {
        $this->configure();
        $this->mockGet('Internal Server Error', 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('HTTP 500', $result->error);
    }

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Test connection ---

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('username and password are required', $result->message);
    }

    public function testTestConnectionOkWhenServerReturnsNoMsg(): void
    {
        $this->configure();
        $this->mockGet('NO_MSG');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Connected to SL Interactive', $result->message);

        // The probe must omit `msg` so no SMS is sent and no credit is consumed.
        $query = $this->parseQuery($this->getLog[0]['url']);
        $this->assertSame(self::USERNAME, $query['uname']);
        $this->assertSame(self::PASSWORD, $query['pword']);
        $this->assertArrayNotHasKey('msg', $query);
    }

    public function testTestConnectionFailsOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockGet('I_UNAME_PWORD');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid SL Interactive credentials', $result->message);
    }

    public function testTestConnectionFailsOnHttpError(): void
    {
        $this->configure();
        $this->mockGet('', 503);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('HTTP 503', $result->message);
    }
}
