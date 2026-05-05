<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SafaSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SafaSmsProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'panel-user';
    private const PASSWORD = 'panel-pass';
    private const SENDER = 'COMPANY';
    private const SEND_URL_PREFIX = 'https://www.safa-sms.com/api/sendsms.php';
    private const BALANCE_URL_PREFIX = 'https://www.safa-sms.com/api/getbalance.php';
    private const SENDERS_URL_PREFIX = 'https://www.safa-sms.com/specialapi/GetAllSenders.php';

    protected function createProvider(): AbstractProvider
    {
        return new SafaSmsProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_get']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'safasms' => [
                'shared'   => array_merge(
                    ['username' => self::USERNAME, 'password' => self::PASSWORD],
                    $sharedOverrides,
                ),
                'channels' => [
                    'sms' => array_merge(['sender' => self::SENDER], $smsOverrides),
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

    private function mockHttpGetError(): void
    {
        $GLOBALS['_test_wp_remote_get'] = function () {
            return new \WP_Error('http_request_failed', 'cURL error 7: Connection refused');
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
        $this->assertFalse(SafaSmsProvider::TESTED);
    }

    public function testGetIdAndChannels(): void
    {
        $provider = $this->createProvider();
        $this->assertSame('safasms', $provider->getId());
        $this->assertSame(['sms'], $provider->getSupportedChannels());
    }

    public function testGetConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertTrue($schema['shared']['password']['required']);
        $this->assertSame('secret', $schema['shared']['password']['type']);

        $this->assertArrayHasKey('sender', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender']['required']);
        $this->assertTrue($schema['channels']['sms']['sender']['dynamic']);
    }

    public function testIsConfiguredFalseWhenMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'safasms' => [
                'shared'   => ['username' => self::USERNAME, 'password' => ''],
                'channels' => ['sms' => ['sender' => self::SENDER]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $GLOBALS['_test_wp_remote_get'] = function () {
            throw new \RuntimeException('wp_remote_get must not be called when unconfigured');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendComposesQueryString(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('847291', 200, $url);

        $this->createProvider()->send($this->createMessage('+966500112233', 'Hi'));

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::SEND_URL_PREFIX, $url);

        $query = $this->parseQuery($url);
        $this->assertSame(self::USERNAME, $query['username']);
        $this->assertSame(self::PASSWORD, $query['password']);
        $this->assertSame('Hi', $query['message']);
        $this->assertSame('966500112233', $query['numbers']);
        $this->assertSame(self::SENDER, $query['sender']);
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('1', 200, $url);

        $this->createProvider()->send($this->createMessage('+966500112233'));

        $query = $this->parseQuery((string) $url);
        $this->assertSame('966500112233', $query['numbers']);
    }

    public function testSendReturnsSentWithProviderIdOnNumericBody(): void
    {
        $this->configure();
        $this->mockHttpGet('847291');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('847291', $result->providerId);
    }

    public function testSendFailsOnErrorBody(): void
    {
        $this->configure();
        $this->mockHttpGet('Error: Invalid credentials');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Error: Invalid credentials', $result->error);
    }

    public function testSendFailsOnEmptyBody(): void
    {
        $this->configure();
        $this->mockHttpGet('');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('empty', strtolower($result->error));
    }

    public function testSendFailsOnNon2xxStatus(): void
    {
        $this->configure();
        $this->mockHttpGet('Internal Server Error', 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Internal Server Error', $result->error);
    }

    public function testSendFailsOnNetworkError(): void
    {
        $this->configure();
        $this->mockHttpGetError();

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Connection refused', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalanceWhenNumeric(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet('250', 200, $url);

        $this->assertSame('250', $this->createProvider()->getCredit());

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::BALANCE_URL_PREFIX, $url);
        $query = $this->parseQuery($url);
        $this->assertSame(self::USERNAME, $query['username']);
        $this->assertSame(self::PASSWORD, $query['password']);
    }

    public function testGetCreditReturnsNullOnNonNumericBody(): void
    {
        $this->configure();
        $this->mockHttpGet('Error: invalid credentials');

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionOkOnNumericBalance(): void
    {
        $this->configure();
        $this->mockHttpGet('250');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('250', $result->message);
    }

    public function testTestConnectionErrorOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpGet('Error: Invalid username or password');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertSame('Error: Invalid username or password', $result->message);
    }

    // --- Dynamic options (sender list) ---

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $config = [
            'shared'   => ['username' => self::USERNAME, 'password' => self::PASSWORD],
            'channels' => ['sms' => []],
        ];

        $this->assertSame([], $this->createProvider()->getConfigOptions('username', 'shared', $config));
        $this->assertSame([], $this->createProvider()->getConfigOptions('sender', 'whatsapp', $config));
    }

    public function testGetConfigOptionsThrowsWhenCredentialsMissing(): void
    {
        $config = [
            'shared'   => ['username' => '', 'password' => ''],
            'channels' => ['sms' => []],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Enter API Username and API Password first');

        $this->createProvider()->getConfigOptions('sender', 'sms', $config);
    }

    public function testGetConfigOptionsParsesXmlSenderList(): void
    {
        $config = [
            'shared'   => ['username' => self::USERNAME, 'password' => self::PASSWORD],
            'channels' => ['sms' => []],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?><Senders>'
             . '<Sender>FOO</Sender>'
             . '<Sender>BAR</Sender>'
             . '<Sender>BAZ</Sender>'
             . '</Senders>';

        $url = null;
        $this->mockHttpGet($xml, 200, $url);

        $options = $this->createProvider()->getConfigOptions('sender', 'sms', $config);

        $this->assertSame([
            ['value' => 'FOO', 'label' => 'FOO'],
            ['value' => 'BAR', 'label' => 'BAR'],
            ['value' => 'BAZ', 'label' => 'BAZ'],
        ], $options);

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::SENDERS_URL_PREFIX, $url);
        $query = $this->parseQuery($url);
        $this->assertSame(self::USERNAME, $query['username']);
        $this->assertSame(self::PASSWORD, $query['password']);
        $this->assertSame('xml', $query['return']);
    }

    public function testGetConfigOptionsThrowsOnMalformedXml(): void
    {
        $config = [
            'shared'   => ['username' => self::USERNAME, 'password' => self::PASSWORD],
            'channels' => ['sms' => []],
        ];

        $this->mockHttpGet('<<<not xml>>>');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('malformed sender list');

        $this->createProvider()->getConfigOptions('sender', 'sms', $config);
    }
}
