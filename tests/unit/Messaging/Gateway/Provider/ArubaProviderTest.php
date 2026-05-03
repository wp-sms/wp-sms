<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\ArubaProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class ArubaProviderTest extends AbstractProviderTestCase
{
    private const USERNAME    = 'aruba-user@example.com';
    private const PASSWORD    = 'aruba-pass-9999';
    private const USER_KEY    = 'USER123';
    private const ACCESS_TOK  = 'TOKEN456';

    /** @var array<int, array{url:string,args:array}> */
    private array $getLog = [];

    /** @var array<int, array{url:string,args:array}> */
    private array $postLog = [];

    protected function createProvider(): AbstractProvider
    {
        return new ArubaProvider();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_test_transients'] = [];
        $this->getLog = [];
        $this->postLog = [];
        unset(
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'aruba' => [
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

    private function createMessage(string $recipient = '+393331234567', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    /**
     * Install a callable mock for wp_remote_get; calls land in $this->getLog.
     *
     * @param callable(string,array): array $handler
     */
    private function mockGet(callable $handler): void
    {
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use ($handler) {
            $this->getLog[] = ['url' => $url, 'args' => $args];
            return $handler($url, $args);
        };
    }

    /**
     * Install a callable mock for wp_remote_post; calls land in $this->postLog.
     *
     * @param callable(string,array): array $handler
     */
    private function mockPost(callable $handler): void
    {
        $GLOBALS['_test_wp_remote_post'] = function (string $url, array $args) use ($handler) {
            $this->postLog[] = ['url' => $url, 'args' => $args];
            return $handler($url, $args);
        };
    }

    private static function tokenResponse(): array
    {
        return [
            'body'     => self::USER_KEY . ';' . self::ACCESS_TOK,
            'response' => ['code' => 200],
        ];
    }

    private static function jsonResponse(array $body, int $code = 200): array
    {
        return [
            'body'     => json_encode($body),
            'response' => ['code' => $code],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(ArubaProvider::TESTED);
    }

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('aruba', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertTrue($schema['shared']['password']['required']);
        $this->assertFalse((bool) ($schema['shared']['callback_token']['required'] ?? true));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertArrayHasKey('message_type', $schema['channels']['sms']);
        $this->assertSame('select', $schema['channels']['sms']['message_type']['type']);
        $this->assertSame('N', $schema['channels']['sms']['message_type']['default']);

        $values = array_column($schema['channels']['sms']['message_type']['options'], 'value');
        $this->assertSame(['N', 'L'], $values);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendFetchesTokenThenPostsSms(): void
    {
        $this->configure();
        $this->mockGet(fn() => self::tokenResponse());
        $this->mockPost(fn() => self::jsonResponse(['result' => 'OK', 'order_id' => 'ord-1'], 201));

        $result = $this->createProvider()->send($this->createMessage('+393331234567', 'Hi'));

        $this->assertTrue($result->success);

        $this->assertCount(1, $this->getLog);
        $this->assertSame('https://smspanel.aruba.it/API/v1.0/REST/token', $this->getLog[0]['url']);
        $expectedBasic = 'Basic ' . base64_encode(self::USERNAME . ':' . self::PASSWORD);
        $this->assertSame($expectedBasic, $this->getLog[0]['args']['headers']['Authorization']);

        $this->assertCount(1, $this->postLog);
        $this->assertSame('https://smspanel.aruba.it/API/v1.0/REST/sms', $this->postLog[0]['url']);
        $headers = $this->postLog[0]['args']['headers'];
        $this->assertSame(self::USER_KEY, $headers['user_key']);
        $this->assertSame(self::ACCESS_TOK, $headers['Access_token']);
        $this->assertSame('application/json', $headers['Content-Type']);

        $body = json_decode($this->postLog[0]['args']['body'], true);
        $this->assertSame('Hi', $body['message']);
        $this->assertSame(['+393331234567'], $body['recipient']);
        $this->assertTrue($body['returnCredits']);
        $this->assertTrue($body['returnRemaining']);
    }

    public function testSendDefaultsMessageTypeToHigh(): void
    {
        $this->configure(); // no message_type override
        $this->mockGet(fn() => self::tokenResponse());
        $this->mockPost(fn() => self::jsonResponse(['result' => 'OK', 'order_id' => 'x'], 201));

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($this->postLog[0]['args']['body'], true);
        $this->assertSame('N', $body['message_type']);
    }

    public function testSendIncludesSenderWhenConfigured(): void
    {
        $this->configure(smsOverrides: ['from' => 'WSMS']);
        $this->mockGet(fn() => self::tokenResponse());
        $this->mockPost(fn() => self::jsonResponse(['result' => 'OK', 'order_id' => 'x'], 201));

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($this->postLog[0]['args']['body'], true);
        $this->assertSame('WSMS', $body['sender']);
    }

    public function testSendOmitsSenderWhenNotConfigured(): void
    {
        $this->configure();
        $this->mockGet(fn() => self::tokenResponse());
        $this->mockPost(fn() => self::jsonResponse(['result' => 'OK', 'order_id' => 'x'], 201));

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($this->postLog[0]['args']['body'], true);
        $this->assertArrayNotHasKey('sender', $body);
        $this->assertArrayNotHasKey('encoding', $body);
    }

    public function testSendUsesUcs2EncodingForUnicode(): void
    {
        $this->configure();
        $this->mockGet(fn() => self::tokenResponse());
        $this->mockPost(fn() => self::jsonResponse(['result' => 'OK', 'order_id' => 'x'], 201));

        $this->createProvider()->send($this->createMessage('+393331234567', '안녕'));

        $body = json_decode($this->postLog[0]['args']['body'], true);
        $this->assertSame('ucs2', $body['encoding']);
        $this->assertSame('안녕', $body['message']);
    }

    public function testSendReturnsQueuedWithOrderId(): void
    {
        $this->configure();
        $this->mockGet(fn() => self::tokenResponse());
        $this->mockPost(fn() => self::jsonResponse(['result' => 'OK', 'order_id' => 'abc'], 201));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('abc', $result->providerId);
    }

    public function testSendRetriesOnceOnTokenExpiry(): void
    {
        $this->configure();

        $this->mockGet(fn() => self::tokenResponse());

        $postCalls = 0;
        $this->mockPost(function () use (&$postCalls) {
            $postCalls++;
            if ($postCalls === 1) {
                return self::jsonResponse(['result' => 'KO'], 401);
            }
            return self::jsonResponse(['result' => 'OK', 'order_id' => 'retry-ok'], 201);
        });

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('retry-ok', $result->providerId);
        $this->assertCount(2, $this->getLog, 'token endpoint should be re-hit after 401');
        $this->assertCount(2, $this->postLog, 'send should retry once');
    }

    public function testSendFailsOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockGet(fn() => self::jsonResponse(['error' => 'unauthorized'], 401));
        $this->mockPost(fn() => $this->fail('POST should not be called when token fetch fails'));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Aruba credentials', $result->error);
    }

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsOnApiError(): void
    {
        $this->configure();
        $this->mockGet(fn() => self::tokenResponse());
        $this->mockPost(fn() => self::jsonResponse(['result' => 'KO'], 400));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('KO', $result->error);
    }

    // --- Credit ---

    public function testGetCreditPicksMatchingMessageTypeBucket(): void
    {
        $this->configure(smsOverrides: ['message_type' => 'N']);

        $this->mockGet(function (string $url) {
            if (str_contains($url, '/token')) {
                return self::tokenResponse();
            }
            return self::jsonResponse([
                'money' => null,
                'sms'   => [
                    ['type' => 'N', 'quantity' => 500],
                    ['type' => 'L', 'quantity' => 200],
                ],
            ]);
        });

        $result = $this->createProvider()->getCredit();

        $this->assertSame('500 credits (type N)', $result);
        $this->assertCount(2, $this->getLog);
        $this->assertStringContainsString('getMoney=true', $this->getLog[1]['url']);
        $this->assertStringContainsString('typeAliases=true', $this->getLog[1]['url']);
    }

    public function testGetCreditFallsBackToFirstBucketWhenTypeMissing(): void
    {
        $this->configure(smsOverrides: ['message_type' => 'N']);

        $this->mockGet(function (string $url) {
            if (str_contains($url, '/token')) {
                return self::tokenResponse();
            }
            return self::jsonResponse([
                'sms' => [
                    ['type' => 'EE', 'quantity' => 10],
                ],
            ]);
        });

        $this->assertSame('10 credits (type EE)', $this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $this->mockGet(function (string $url) {
            if (str_contains($url, '/token')) {
                return self::tokenResponse();
            }
            return self::jsonResponse(['user' => ['username' => self::USERNAME]]);
        });

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Aruba', $result->message);
    }

    public function testTestConnectionInvalidCreds(): void
    {
        $this->configure();
        $this->mockGet(fn() => self::jsonResponse(['error' => 'bad'], 401));

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Aruba credentials', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackChecksToken(): void
    {
        $this->configure(['callback_token' => 'sekret']);

        $good = $this->buildRequest('GET', '/x', ['token' => 'sekret', 'order_id' => 'a']);
        $bad  = $this->buildRequest('GET', '/x', ['token' => 'wrong', 'order_id' => 'a']);
        $missing = $this->buildRequest('GET', '/x', ['order_id' => 'a']);

        $p = $this->createProvider();
        $this->assertTrue($p->validateStatusCallback($good));
        $this->assertFalse($p->validateStatusCallback($bad));
        $this->assertFalse($p->validateStatusCallback($missing));
    }

    public function testValidateStatusCallbackRejectsWhenTokenNotConfigured(): void
    {
        $this->configure(); // no callback_token
        $req = $this->buildRequest('GET', '/x', ['token' => 'anything']);
        $this->assertFalse($this->createProvider()->validateStatusCallback($req));
    }

    public function testParseStatusCallbackDlvrdMapsToDelivered(): void
    {
        $req = $this->buildRequest('GET', '/x', [
            'order_id' => 'ord-99',
            'status'   => 'DLVRD',
            'recipient' => '+393331234567',
        ]);

        $updates = $this->createProvider()->parseStatusCallback($req);

        $this->assertCount(1, $updates);
        $this->assertSame('ord-99', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertTrue($updates[0]->permanent);
        $this->assertNull($updates[0]->errorCode);
    }

    public function testParseStatusCallbackBlacklistedIsPermanentFailure(): void
    {
        $req = $this->buildRequest('GET', '/x', [
            'order_id' => 'ord-1',
            'status'   => 'BLACKLISTED',
        ]);

        $update = $this->createProvider()->parseStatusCallback($req)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('BLACKLISTED', $update->errorCode);
    }

    public function testParseStatusCallbackTimeoutIsTransientFailure(): void
    {
        $req = $this->buildRequest('GET', '/x', [
            'order_id' => 'ord-1',
            'status'   => 'TIMEOUT',
        ]);

        $update = $this->createProvider()->parseStatusCallback($req)[0];
        $this->assertSame('failed', $update->status);
        $this->assertFalse($update->permanent);
        $this->assertSame('TIMEOUT', $update->errorCode);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $req = $this->buildRequest('GET', '/x', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($req));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params): \WP_REST_Request
    {
        $request = new \WP_REST_Request($method, $route);
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        return $request;
    }
}
