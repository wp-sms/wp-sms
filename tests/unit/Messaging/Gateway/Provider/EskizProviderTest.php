<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\EskizProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class EskizProviderTest extends AbstractProviderTestCase
{
    private const EMAIL = 'me@example.com';
    private const PASSWORD = 'sekret';
    private const FROM = '4546';
    private const RECIPIENT = '+998901234567';
    private const TOKEN = 'jwt.test.token';

    protected function createProvider(): AbstractProvider
    {
        return new EskizProvider();
    }

    private function configure(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'eskiz' => [
                'shared'   => [
                    'email'    => self::EMAIL,
                    'password' => self::PASSWORD,
                ],
                'channels' => [
                    'sms' => ['from' => self::FROM],
                ],
            ],
        ];
    }

    /**
     * Route POST mocks based on URL: /auth/login → tokenResponse, /message/sms/send → sendResponse.
     */
    private function mockTokenAndSend(array $tokenResponse, int $tokenStatus, array $sendResponse, int $sendStatus): void
    {
        $GLOBALS['_test_wp_remote_post'] = function (string $url) use ($tokenResponse, $tokenStatus, $sendResponse, $sendStatus) {
            if (str_contains($url, '/auth/login')) {
                return [
                    'body'     => json_encode($tokenResponse),
                    'response' => ['code' => $tokenStatus],
                ];
            }
            if (str_contains($url, '/message/sms/send')) {
                return [
                    'body'     => json_encode($sendResponse),
                    'response' => ['code' => $sendStatus],
                ];
            }
            return ['body' => '', 'response' => ['code' => 404]];
        };
    }

    private function createMessage(string $body = 'Hello'): Message
    {
        return new Message('sms', self::RECIPIENT, $body, null, []);
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(EskizProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('eskiz', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShapes(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('email', $schema['shared']);
        $this->assertSame('string', $schema['shared']['email']['type']);
        $this->assertTrue($schema['shared']['email']['required']);

        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $from = $schema['channels']['sms']['from'];
        $this->assertSame('string', $from['type']);
        $this->assertTrue($from['required']);
    }

    // --- Send ---

    public function testSendSucceedsAndReturnsMessageId(): void
    {
        $this->configure();
        $capturedSendArgs = null;
        $GLOBALS['_test_wp_remote_post'] = function (string $url, array $args) use (&$capturedSendArgs) {
            if (str_contains($url, '/auth/login')) {
                return [
                    'body'     => json_encode(['data' => ['token' => self::TOKEN]]),
                    'response' => ['code' => 200],
                ];
            }
            if (str_contains($url, '/message/sms/send')) {
                $capturedSendArgs = $args;
                return [
                    'body'     => json_encode(['id' => 'msg-001', 'status' => 'waiting']),
                    'response' => ['code' => 200],
                ];
            }
            return ['body' => '', 'response' => ['code' => 404]];
        };

        $result = $this->createProvider()->send($this->createMessage('Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('msg-001', $result->providerId);

        // The last URL recorded should be the send endpoint (the second POST).
        $this->assertSame(
            'https://notify.eskiz.uz/api/message/sms/send',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $this->assertNotNull($capturedSendArgs);
        $this->assertSame('Bearer ' . self::TOKEN, $capturedSendArgs['headers']['Authorization']);
        $this->assertSame(self::RECIPIENT, $capturedSendArgs['body']['mobile_phone']);
        $this->assertSame('Hi', $capturedSendArgs['body']['message']);
        $this->assertSame(self::FROM, $capturedSendArgs['body']['from']);
    }

    public function testSendFailsWithoutCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('email and password', $result->error);
    }

    public function testSendFailsOnAuthError(): void
    {
        $this->configure();
        $this->mockTokenAndSend(
            ['message' => 'Invalid credentials'], 401,
            [], 200,
        );

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('authentication failed', $result->error);
    }

    public function testSendFailsOnApiError(): void
    {
        $this->configure();
        $this->mockTokenAndSend(
            ['data' => ['token' => self::TOKEN]], 200,
            ['message' => 'Template not whitelisted'], 422,
        );

        $result = $this->createProvider()->send($this->createMessage('not a registered template'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Template not whitelisted', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = function () {
            return [
                'body'     => json_encode(['data' => ['token' => self::TOKEN]]),
                'response' => ['code' => 200],
            ];
        };
        $capturedGetArgs = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$capturedGetArgs) {
            $capturedGetArgs = $args;
            return [
                'body'     => json_encode(['data' => ['balance' => 1500]]),
                'response' => ['code' => 200],
            ];
        };

        $credit = $this->createProvider()->getCredit();

        $this->assertSame('1500', $credit);
        $this->assertNotNull($capturedGetArgs);
        $this->assertSame('Bearer ' . self::TOKEN, $capturedGetArgs['headers']['Authorization']);
    }

    // --- Test connection ---

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $this->mockTokenAndSend(
            ['data' => ['token' => self::TOKEN]], 200,
            [], 200,
        );

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Connected to Eskiz', $result->message);
    }

    public function testTestConnectionErrorOnBadCredentials(): void
    {
        $this->configure();
        $this->mockTokenAndSend(
            ['message' => 'invalid_credentials'], 401,
            [], 200,
        );

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('authentication failed', $result->message);
    }

    public function testTestConnectionErrorWithoutCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
