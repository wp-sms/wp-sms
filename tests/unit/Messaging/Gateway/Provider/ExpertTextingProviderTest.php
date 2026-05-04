<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\ExpertTextingProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class ExpertTextingProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'starcity';
    private const API_KEY = 'sswmp8r7l63y';
    private const API_SECRET = '5fq8vn07iyoqu3j';
    private const FROM = 'WSMS';

    private const SEND_URL_PATH = '/ExptRestApi/sms/json/Message/Send';
    private const BALANCE_URL_PATH = '/ExptRestApi/sms/json/Account/Balance';

    protected function createProvider(): AbstractProvider
    {
        return new ExpertTextingProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'experttexting' => [
                'shared'   => array_merge([
                    'username'   => self::USERNAME,
                    'api_key'    => self::API_KEY,
                    'api_secret' => self::API_SECRET,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+34600111222', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    /**
     * Mock wp_remote_get with a callable so we can capture URL/args.
     * The caller passes `$captured` by reference; the closure populates it on invocation.
     */
    private function captureHttpGet(array &$captured, array $responseBody, int $statusCode = 200): void
    {
        $captured = ['url' => null, 'args' => null];
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$captured, $responseBody, $statusCode) {
            $captured['url'] = $url;
            $captured['args'] = $args;
            return [
                'body'     => json_encode($responseBody),
                'response' => ['code' => $statusCode],
            ];
        };
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private static function parseQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        return $q;
    }

    // --- Identity & schema ---

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('experttexting', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(ExpertTextingProvider::TESTED);
    }

    public function testConfigSchemaHasRequiredFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        foreach (['username', 'api_key', 'api_secret'] as $key) {
            $this->assertArrayHasKey($key, $schema['shared'], "missing shared.$key");
            $this->assertTrue((bool) ($schema['shared'][$key]['required'] ?? false), "$key not required");
        }

        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertSame('secret', $schema['shared']['api_secret']['type']);
        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    /**
     * @dataProvider missingCredentialProvider
     */
    public function testIsConfiguredFalseWhenAnyCredentialMissing(string $location, string $field): void
    {
        if ($location === 'shared') {
            $this->configure(sharedOverrides: [$field => '']);
        } else {
            $this->configure(smsOverrides: [$field => '']);
        }
        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public static function missingCredentialProvider(): array
    {
        return [
            'username missing'   => ['shared', 'username'],
            'api_key missing'    => ['shared', 'api_key'],
            'api_secret missing' => ['shared', 'api_secret'],
            'from missing'       => ['channels', 'from'],
        ];
    }

    public function testValidateConfigRejectsMissingShared(): void
    {
        $this->assertFalse($this->createProvider()->validateConfig(['shared' => []]));
        $this->assertFalse($this->createProvider()->validateConfig([
            'shared' => ['username' => 'u', 'api_key' => 'k'], // api_secret missing
        ]));
    }

    // --- Send ---

    public function testSendReturnsSentOnStatusZero(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'Status'   => 0,
            'Response' => ['MessageID' => 'abc-123'],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('abc-123', $result->providerId);
    }

    public function testSendBuildsCorrectQueryString(): void
    {
        $this->configure();
        $captured = [];
        $this->captureHttpGet($captured, ['Status' => 0, 'Response' => ['MessageID' => 'id-1']]);

        $this->createProvider()->send($this->createMessage('+34600111222', 'Hi there'));

        $this->assertNotNull($captured['url']);
        $this->assertStringContainsString(self::SEND_URL_PATH, $captured['url']);

        $q = self::parseQuery($captured['url']);
        $this->assertSame(self::USERNAME, $q['username']);
        $this->assertSame(self::API_KEY, $q['api_key']);
        $this->assertSame(self::API_SECRET, $q['api_secret']);
        $this->assertSame(self::FROM, $q['from']);
        $this->assertSame('34600111222', $q['to']);
        $this->assertSame('Hi there', $q['text']);
        $this->assertSame('text', $q['type']);
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $captured = [];
        $this->captureHttpGet($captured, ['Status' => 0, 'Response' => ['MessageID' => 'id-2']]);

        $this->createProvider()->send($this->createMessage('+15551234567', 'Hi'));

        $q = self::parseQuery($captured['url']);
        $this->assertSame('15551234567', $q['to']);
    }

    public function testSendSetsTypeUnicodeForNonAsciiBody(): void
    {
        $this->configure();
        $captured = [];
        $this->captureHttpGet($captured, ['Status' => 0, 'Response' => ['MessageID' => 'id-uc']]);

        $this->createProvider()->send($this->createMessage('+34600111222', 'سلام'));

        $q = self::parseQuery($captured['url']);
        $this->assertSame('unicode', $q['type']);
    }

    public function testSendReturnsFailedWhenAnyCredentialMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedOnNonZeroStatusWithErrorMessage(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'Status'       => 8,
            'ErrorMessage' => 'Insufficient credit',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credit', $result->error);
        $this->assertSame(8, $result->meta['experttexting_status']);
    }

    public function testSendReturnsAuthErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['Status' => 99, 'ErrorMessage' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalanceString(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'Status'   => 0,
            'Response' => ['Balance' => 247247],
        ]);

        $this->assertSame('247247', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnNonZeroStatus(): void
    {
        $this->configure();
        $this->mockHttpGet(['Status' => 9, 'ErrorMessage' => 'Bad credentials']);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditUsesBalanceEndpointWithAllThreeCredentials(): void
    {
        $this->configure();
        $captured = [];
        $this->captureHttpGet($captured, [
            'Status'   => 0,
            'Response' => ['Balance' => 500],
        ]);

        $this->createProvider()->getCredit();

        $this->assertNotNull($captured['url']);
        $this->assertStringContainsString(self::BALANCE_URL_PATH, $captured['url']);

        $q = self::parseQuery($captured['url']);
        $this->assertSame(self::USERNAME, $q['username']);
        $this->assertSame(self::API_KEY, $q['api_key']);
        $this->assertSame(self::API_SECRET, $q['api_secret']);
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'Status'   => 0,
            'Response' => ['Balance' => 15],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('15', $result->message);
        $this->assertSame(15, $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['Status' => 99, 'ErrorMessage' => 'Unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresAllCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
