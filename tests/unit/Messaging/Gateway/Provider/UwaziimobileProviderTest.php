<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\UwaziimobileProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class UwaziimobileProviderTest extends AbstractProviderTestCase
{
    private const USERNAME    = 'uwazii-user@example.com';
    private const PASSWORD    = 'uwazii-pass-9999';
    private const SENDER_ID   = 'WSMS';
    private const AUTH_CODE   = 'AUTHCODE-123';
    private const ACCESS_TOK  = 'ACCESSTOKEN-456';
    private const RECIPIENT   = '+254712345678';
    private const RECIPIENT_DIGITS = '254712345678';

    /** @var array<int, array{url:string,args:array}> */
    private array $getLog = [];

    /** @var array<int, array{url:string,args:array}> */
    private array $postLog = [];

    protected function createProvider(): AbstractProvider
    {
        return new UwaziimobileProvider();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_test_transients'] = [];
        $this->getLog  = [];
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
            'uwaziimobile' => [
                'shared'   => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER_ID], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = self::RECIPIENT, string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
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

    private static function jsonResponse(array $body, int $code = 200): array
    {
        return [
            'body'     => json_encode($body),
            'response' => ['code' => $code],
        ];
    }

    private static function rawResponse(string $body, int $code = 200): array
    {
        return [
            'body'     => $body,
            'response' => ['code' => $code],
        ];
    }

    /**
     * Standard 3-step auth handler: returns the matching response based on URL.
     * Returns send response from the provided callable.
     *
     * @param callable(string,array,int $sendCallNumber): array $sendHandler
     */
    private function fullFlowPost(callable $sendHandler): callable
    {
        $sendCalls = 0;
        return function (string $url, array $args) use ($sendHandler, &$sendCalls) {
            if (str_ends_with($url, '/authorize')) {
                return self::jsonResponse(['status' => true, 'data' => ['authorization_code' => self::AUTH_CODE]]);
            }
            if (str_ends_with($url, '/accesstoken')) {
                return self::jsonResponse(['status' => true, 'data' => ['access_token' => self::ACCESS_TOK]]);
            }
            if (str_ends_with($url, '/send')) {
                $sendCalls++;
                return $sendHandler($url, $args, $sendCalls);
            }
            return self::jsonResponse([], 404);
        };
    }

    private static function successSendResponse(): array
    {
        return self::jsonResponse([
            'status' => true,
            'data'   => [
                self::RECIPIENT_DIGITS => [
                    ['id_state' => 'msg-uuid-1'],
                ],
            ],
        ]);
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(UwaziimobileProvider::TESTED);
    }

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('uwaziimobile', $p->getId());
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

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendReturnsDeliveryResultOnSuccess(): void
    {
        $this->configure();
        $this->mockPost($this->fullFlowPost(fn() => self::successSendResponse()));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('msg-uuid-1', $result->providerId);

        $authorize = $this->postLog[0];
        $this->assertSame('https://restapi.uwaziimobile.com/v1/authorize', $authorize['url']);
        $authorizeBody = json_decode($authorize['args']['body'], true);
        $this->assertSame(self::USERNAME, $authorizeBody['username']);
        $this->assertSame(self::PASSWORD, $authorizeBody['password']);

        $accesstoken = $this->postLog[1];
        $this->assertSame('https://restapi.uwaziimobile.com/v1/accesstoken', $accesstoken['url']);
        $tokenBody = json_decode($accesstoken['args']['body'], true);
        $this->assertSame(self::AUTH_CODE, $tokenBody['authorization_code']);

        $send = $this->postLog[2];
        $this->assertSame('https://restapi.uwaziimobile.com/v1/send', $send['url']);
        $this->assertSame(self::ACCESS_TOK, $send['args']['headers']['X-Access-Token']);
        $this->assertSame('application/json', $send['args']['headers']['Content-Type']);

        $sendBody = json_decode($send['args']['body'], true);
        $this->assertIsArray($sendBody);
        $this->assertCount(1, $sendBody);
        $this->assertSame([self::RECIPIENT_DIGITS], $sendBody[0]['number']);
        $this->assertSame(self::SENDER_ID, $sendBody[0]['senderID']);
        $this->assertSame('Hello', $sendBody[0]['text']);
        $this->assertSame('sms', $sendBody[0]['type']);
    }

    public function testSendReusesCachedToken(): void
    {
        $this->configure();
        $this->mockPost($this->fullFlowPost(fn() => self::successSendResponse()));

        $provider = $this->createProvider();
        $provider->send($this->createMessage());

        $countAfterFirst = count($this->postLog);
        $this->assertSame(3, $countAfterFirst, 'first send: authorize + accesstoken + send');

        $provider->send($this->createMessage());

        $sendCalls = array_filter($this->postLog, fn($call) => str_ends_with($call['url'], '/send'));
        $authCalls = array_filter($this->postLog, fn($call) => str_ends_with($call['url'], '/authorize'));
        $tokenCalls = array_filter($this->postLog, fn($call) => str_ends_with($call['url'], '/accesstoken'));

        $this->assertCount(2, $sendCalls, 'second send should hit /send again');
        $this->assertCount(1, $authCalls, 'cached token should skip /authorize on second send');
        $this->assertCount(1, $tokenCalls, 'cached token should skip /accesstoken on second send');
    }

    public function testSendReAuthenticatesOn401(): void
    {
        $this->configure();
        $this->mockPost($this->fullFlowPost(function ($url, $args, int $sendCalls) {
            if ($sendCalls === 1) {
                return self::jsonResponse(['status' => false, 'errors' => 'unauthorized'], 401);
            }
            return self::successSendResponse();
        }));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('msg-uuid-1', $result->providerId);

        $sendCalls = array_filter($this->postLog, fn($c) => str_ends_with($c['url'], '/send'));
        $authCalls = array_filter($this->postLog, fn($c) => str_ends_with($c['url'], '/authorize'));
        $tokenCalls = array_filter($this->postLog, fn($c) => str_ends_with($c['url'], '/accesstoken'));

        $this->assertCount(2, $sendCalls, 'send should retry once after 401');
        $this->assertCount(2, $authCalls, '/authorize should be re-hit after token invalidation');
        $this->assertCount(2, $tokenCalls, '/accesstoken should be re-hit after token invalidation');
    }

    public function testSendMarksKnownErrorsNonRetryable(): void
    {
        $this->configure();
        $this->mockPost($this->fullFlowPost(fn() => self::jsonResponse([
            'status'     => false,
            'error_code' => 400,
            'errors'     => 'no_client_price',
        ], 400)));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        $this->assertStringContainsString('no_client_price', $result->error);
    }

    public function testSendMarks5xxRetryable(): void
    {
        $this->configure();
        $this->mockPost($this->fullFlowPost(fn() => self::jsonResponse(['status' => false], 503)));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
        $this->assertStringContainsString('503', $result->error);
    }

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testRecipientNormalisationStripsPlusAndNonDigits(): void
    {
        $this->configure();
        $this->mockPost($this->fullFlowPost(fn() => self::successSendResponse()));

        $this->createProvider()->send($this->createMessage('+254 712-345-678', 'Hi'));

        $send = $this->postLog[2];
        $sendBody = json_decode($send['args']['body'], true);
        $this->assertSame([self::RECIPIENT_DIGITS], $sendBody[0]['number']);
    }

    // --- Test connection ---

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $this->mockPost($this->fullFlowPost(fn() => self::jsonResponse([])));
        $this->mockGet(fn() => self::jsonResponse(['status' => true, 'data' => ['username' => self::USERNAME]]));

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Uwazii', $result->message);

        $this->assertCount(1, $this->getLog);
        $this->assertSame('https://restapi.uwaziimobile.com/v1/me', $this->getLog[0]['url']);
        $this->assertSame(self::ACCESS_TOK, $this->getLog[0]['args']['headers']['X-Access-Token']);
    }

    public function testTestConnectionFailureOnBadCredentials(): void
    {
        $this->configure();
        // Auth dance fails: /authorize returns 401.
        $this->mockPost(function (string $url) {
            if (str_ends_with($url, '/authorize')) {
                return self::jsonResponse(['status' => false, 'errors' => 'invalid_credentials'], 401);
            }
            return self::jsonResponse([], 500);
        });

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
