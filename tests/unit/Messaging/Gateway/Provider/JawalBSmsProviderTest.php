<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\JawalBSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class JawalBSmsProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'panel-user';
    private const PASSWORD = 'panel-pass';
    private const FROM = 'COMPANY';
    private const SEND_URL_PREFIX = 'https://www.jawalbsms.ws/api.php/sendsms';
    private const BALANCE_URL_PREFIX = 'https://www.jawalbsms.ws/api.php/chk_balance';

    protected function createProvider(): AbstractProvider
    {
        return new JawalBSmsProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_get']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'jawalbsms' => [
                'shared'   => array_merge(
                    ['username' => self::USERNAME, 'password' => self::PASSWORD],
                    $sharedOverrides,
                ),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+966500112233', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpGet(string $body, int $statusCode = 200, ?string &$capturedUrl = null): void
    {
        $payload = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];

        $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($payload, &$capturedUrl) {
            $capturedUrl = $url;
            return $payload;
        };
    }

    private function parseQuery(string $url): array
    {
        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $query);
        return $query;
    }

    // --- Identity ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(JawalBSmsProvider::TESTED);
    }

    public function testGetIdAndChannels(): void
    {
        $provider = $this->createProvider();
        $this->assertSame('jawalbsms', $provider->getId());
        $this->assertSame(['sms'], $provider->getSupportedChannels());
    }

    // --- Send ---

    public function testSendReturnsSentWhenBodyContainsSuccess(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('Success: 12345', 200, $url);

        $result = $this->createProvider()->send($this->createMessage('+966500112233', 'Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('Success: 12345', $result->providerId);

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::SEND_URL_PREFIX, $url);

        $query = $this->parseQuery($url);
        $this->assertSame(self::USERNAME, $query['user']);
        $this->assertSame(self::PASSWORD, $query['pass']);
        $this->assertSame('966500112233', $query['to']);
        $this->assertSame('Hi', $query['message']);
        $this->assertSame(self::FROM, $query['sender']);
    }

    public function testSendFailsOnMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $GLOBALS['_test_wp_remote_get'] = function () {
            throw new \RuntimeException('wp_remote_get must not be called when unconfigured');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendMapsKnownErrorCode(): void
    {
        $this->configure();
        $this->mockHttpGet('-110');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Account not exist (wrong username or password)', $result->error);
    }

    public function testSendPassesUnknownErrorCodeThrough(): void
    {
        $this->configure();
        $this->mockHttpGet('-999');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('-999', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsNumericBalance(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('42', 200, $url);

        $this->assertSame('42', $this->createProvider()->getCredit());

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::BALANCE_URL_PREFIX, $url);
        $query = $this->parseQuery($url);
        $this->assertSame(self::USERNAME, $query['user']);
        $this->assertSame(self::PASSWORD, $query['pass']);
    }

    public function testGetCreditReturnsNullOnNegativeCode(): void
    {
        $this->configure();
        $this->mockHttpGet('-110');

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionFailsOnAuthError(): void
    {
        $this->configure();
        $this->mockHttpGet('-110');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertSame('Account not exist (wrong username or password)', $result->message);
    }

    public function testTestConnectionSucceedsOnPositiveBalance(): void
    {
        $this->configure();
        $this->mockHttpGet('42');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42', $result->message);
    }
}
