<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BulkSmsMaProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BulkSmsMaProviderTest extends AbstractProviderTestCase
{
    private const TOKEN       = 'tok-bulksmsma-12345';
    private const SHORTCODE   = 'MYBRAND';
    private const SEND_URL    = 'https://bulksms.ma/developer/sms/send';
    private const BALANCE_URL = 'https://bulksms.ma/developer/account/solde';

    protected function createProvider(): AbstractProvider
    {
        return new BulkSmsMaProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_post']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulksmsma' => [
                'shared'   => array_merge(['token' => self::TOKEN], $sharedOverrides),
                'channels' => [
                    'sms' => $smsOverrides,
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '0612345678', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200, ?callable $capture = null): void
    {
        $payload = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];

        if ($capture) {
            $GLOBALS['_test_wp_remote_post'] = function ($url, $args) use ($payload, $capture) {
                $capture($url, $args);
                return $payload;
            };
        } else {
            $GLOBALS['_test_wp_remote_post'] = $payload;
        }
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BulkSmsMaProvider::TESTED);
    }

    // --- Send ---

    public function testSendPostsExpectedBody(): void
    {
        $this->configure([], ['shortcode' => self::SHORTCODE]);
        $captured = ['url' => null, 'args' => null];
        $this->mockHttpPost(
            ['success' => 1],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url']  = $url;
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage('0612345678', 'Salam'));

        $this->assertSame(self::SEND_URL, $captured['url']);
        $body = $captured['args']['body'];
        $this->assertSame(self::TOKEN, $body['token']);
        $this->assertSame('0612345678', $body['tel']);
        $this->assertSame('Salam', $body['message']);
        $this->assertSame(self::SHORTCODE, $body['shortcode']);
    }

    public function testSendOmitsShortcodeWhenNotConfigured(): void
    {
        $this->configure();
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['success' => 1],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage());

        $this->assertArrayNotHasKey('shortcode', $captured['args']['body']);
    }

    public function testSendReturnsSentOnSuccessJson(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => 1]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNull($result->providerId);
    }

    public function testSendReturnsFailedOnErrorJson(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'Invalid token']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid token', $result->error);
    }

    public function testSendFailsWhenTokenMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('token', strtolower($result->error));
    }

    // --- getCredit ---

    public function testGetCreditReturnsSoldeWhenConfigured(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => 1, 'solde' => 2000]);

        $this->assertSame('2000', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfiguredExplicit(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionOkOnValidBalance(): void
    {
        $this->configure();
        $captured = ['url' => null];
        $this->mockHttpPost(
            ['success' => 1, 'solde' => 1500],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url'] = $url;
            },
        );

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('1500', $result->message);
        $this->assertSame('1500', $result->details['credit']);
        $this->assertSame(self::BALANCE_URL, $captured['url']);
    }

    public function testTestConnectionErrorOnApiError(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'Invalid token']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid token', $result->message);
    }

    public function testTestConnectionErrorOnNon2xx(): void
    {
        $this->configure();
        $this->mockHttpPost([], 500);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->message);
    }
}
