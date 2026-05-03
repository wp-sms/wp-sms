<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\DeewanProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class DeewanProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'deewan-user';
    private const API_KEY  = 'deewan-api-key-123';
    private const SENDER   = 'COMPANY';
    private const TOKEN    = 'access-token-xyz';

    private const SIGNIN_URL  = 'https://apis.deewan.sa/auth/v1/signin';
    private const SEND_URL    = 'https://apis.deewan.sa/sms/v1/messages';
    private const BALANCE_URL = 'https://apis.deewan.sa/sms/v1/account/balance';

    /** @var array<int, array{url:string,args:array}> */
    private array $postLog = [];

    /** @var array<int, array{url:string,args:array}> */
    private array $getLog = [];

    protected function createProvider(): AbstractProvider
    {
        return new DeewanProvider();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->postLog = [];
        $this->getLog = [];
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'deewan' => [
                'shared'   => array_merge(
                    ['username' => self::USERNAME, 'api_key' => self::API_KEY],
                    $sharedOverrides,
                ),
                'channels' => [
                    'sms' => array_merge(['sender_name' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '966500000000', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    /**
     * Install a wp_remote_post mock that dispatches by URL prefix. Each entry in
     * $responses is a [body, code] tuple; missing keys throw to surface mistakes.
     *
     * @param array<string, array{0:array|string, 1:int}> $responses URL prefix => [body, code]
     */
    private function mockPostByUrl(array $responses): void
    {
        $GLOBALS['_test_wp_remote_post'] = function (string $url, array $args) use ($responses) {
            $this->postLog[] = ['url' => $url, 'args' => $args];

            foreach ($responses as $prefix => $tuple) {
                if (str_starts_with($url, $prefix)) {
                    [$body, $code] = $tuple;
                    return [
                        'body'     => is_array($body) ? json_encode($body) : $body,
                        'response' => ['code' => $code],
                    ];
                }
            }
            throw new \RuntimeException("Unexpected POST: {$url}");
        };
    }

    /**
     * @param array<string, array{0:array|string, 1:int}> $responses URL prefix => [body, code]
     */
    private function mockGetByUrl(array $responses): void
    {
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use ($responses) {
            $this->getLog[] = ['url' => $url, 'args' => $args];

            foreach ($responses as $prefix => $tuple) {
                if (str_starts_with($url, $prefix)) {
                    [$body, $code] = $tuple;
                    return [
                        'body'     => is_array($body) ? json_encode($body) : $body,
                        'response' => ['code' => $code],
                    ];
                }
            }
            throw new \RuntimeException("Unexpected GET: {$url}");
        };
    }

    private static function signinOk(): array
    {
        return [['data' => ['access_token' => self::TOKEN]], 200];
    }

    private function lastPostBody(int $index): array
    {
        $args = $this->postLog[$index]['args'] ?? [];
        return json_decode($args['body'] ?? '{}', true) ?? [];
    }

    // --- Identity / schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(DeewanProvider::TESTED);
    }

    // --- Send ---

    public function testSendSucceedsReturnsRequestId(): void
    {
        $this->configure();
        $this->mockPostByUrl([
            self::SIGNIN_URL => self::signinOk(),
            self::SEND_URL   => [['requestStatus' => ['RequestID' => 'req-9001']], 200],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('req-9001', $result->providerId);

        $this->assertCount(2, $this->postLog);
        $this->assertSame(self::SIGNIN_URL, $this->postLog[0]['url']);
        $this->assertSame(self::SEND_URL, $this->postLog[1]['url']);
        $this->assertSame('Bearer ' . self::TOKEN, $this->postLog[1]['args']['headers']['Authorization']);
    }

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $GLOBALS['_test_wp_remote_post'] = function () {
            throw new \RuntimeException('wp_remote_post must not be called when unconfigured');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsWhenSigninReturnsError(): void
    {
        $this->configure();
        $this->mockPostByUrl([
            self::SIGNIN_URL => [['error' => ['description' => 'Invalid API key']], 401],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid API key', $result->error);
        $this->assertCount(1, $this->postLog);
    }

    public function testSendFailsWhenSendReturnsError(): void
    {
        $this->configure();
        $this->mockPostByUrl([
            self::SIGNIN_URL => self::signinOk(),
            self::SEND_URL   => [['error' => ['description' => 'Insufficient balance']], 400],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient balance', $result->error);
    }

    public function testSendFailsWhenSendReturnsErrorsTuple(): void
    {
        $this->configure();
        $this->mockPostByUrl([
            self::SIGNIN_URL => self::signinOk(),
            self::SEND_URL   => [['errors' => [['E_INVALID_RECIPIENT', 'Recipient is not valid']]], 400],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Recipient is not valid', $result->error);
    }

    public function testSendDetectsArabicAsUnicode(): void
    {
        $this->configure();
        $this->mockPostByUrl([
            self::SIGNIN_URL => self::signinOk(),
            self::SEND_URL   => [['requestStatus' => ['RequestID' => 'r1']], 200],
        ]);

        $this->createProvider()->send($this->createMessage('966500000000', 'مرحبا بالعالم'));

        $body = $this->lastPostBody(1);
        $this->assertSame('unicode', $body['messageType']);
        $this->assertSame('مرحبا بالعالم', $body['messageText']);
        $this->assertSame(self::SENDER, $body['senderName']);
        $this->assertSame('966500000000', $body['recipients']);
    }

    public function testSendDetectsLatinAsText(): void
    {
        $this->configure();
        $this->mockPostByUrl([
            self::SIGNIN_URL => self::signinOk(),
            self::SEND_URL   => [['requestStatus' => ['RequestID' => 'r1']], 200],
        ]);

        $this->createProvider()->send($this->createMessage('966500000000', 'Hello world'));

        $this->assertSame('text', $this->lastPostBody(1)['messageType']);
    }

    // --- Credit ---

    public function testGetCreditReturnsParsedValue(): void
    {
        $this->configure();
        $this->mockPostByUrl([self::SIGNIN_URL => self::signinOk()]);
        $this->mockGetByUrl([
            self::BALANCE_URL => [['data' => ['Account' => ['Credit' => '100.50']]], 200],
        ]);

        $this->assertSame('100.50', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnFailure(): void
    {
        $this->configure();
        $this->mockPostByUrl([
            self::SIGNIN_URL => [['error' => ['description' => 'auth failed']], 401],
        ]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionSucceedsAndSurfacesCredit(): void
    {
        $this->configure();
        $this->mockPostByUrl([self::SIGNIN_URL => self::signinOk()]);
        $this->mockGetByUrl([
            self::BALANCE_URL => [['data' => ['Account' => ['Credit' => '42']]], 200],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('42', $result->details['credit']);
        $this->assertStringContainsString('42', $result->message);
    }

    public function testTestConnectionFailsOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockPostByUrl([
            self::SIGNIN_URL => [['error' => ['description' => 'Invalid credentials']], 401],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        // 401 is non-2xx — validateTestResponse returns a generic HTTP message.
        // Either way, the user gets a clear error rather than a silent success.
        $this->assertNotEmpty($result->message);
    }

    public function testTestConnectionRequiresUsernameAndApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
