<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\AoboxProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class AoboxProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'aobox-tester';
    private const PASSWORD = 'aobox-secret';

    protected function createProvider(): AbstractProvider
    {
        return new AoboxProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'aobox' => [
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

    private function createMessage(string $recipient = '393351234567', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(AoboxProvider::TESTED);
    }

    public function testGetIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('aobox', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $sms = $schema['channels']['sms'];
        $this->assertArrayHasKey('from', $sms);
        $this->assertFalse((bool) ($sms['from']['required'] ?? true));
        $this->assertArrayHasKey('route', $sms);
        $this->assertFalse((bool) ($sms['route']['required'] ?? true));
        $this->assertSame('3', $sms['route']['default']);
    }

    // --- Send ---

    public function testDoSendPostsFormEncodedBodyWithVersion3(): void
    {
        $this->configure();
        $this->mockHttpPost("status=0;OK\ncost=1");

        $this->createProvider()->send($this->createMessage('393351234567', 'Ciao'));

        $this->assertSame(
            'https://aobox.it/app/gateway.php',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('3', $body['version']);
        $this->assertSame(self::USERNAME, $body['username']);
        $this->assertSame(self::PASSWORD, $body['password']);
        $this->assertSame('3', $body['route']);
        $this->assertSame('393351234567', $body['rcpt']);
        $this->assertSame('Ciao', $body['text']);
        // Default sender when none configured
        $this->assertSame('Aobox', $body['sender']);
    }

    public function testDoSendUsesConfiguredFromAndRoute(): void
    {
        $this->configure(smsOverrides: ['from' => 'MyBrand', 'route' => '7']);
        $this->mockHttpPost("status=0;OK\ncost=1");

        $this->createProvider()->send($this->createMessage());

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('MyBrand', $body['sender']);
        $this->assertSame('7', $body['route']);
    }

    public function testDoSendReturnsSentOnStatus0(): void
    {
        $this->configure();
        $this->mockHttpPost("status=0;OK\ncost=1");

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNotEmpty($result->providerId);
    }

    public function testDoSendReturnsFailedOnNonZeroStatus(): void
    {
        $this->configure();
        $this->mockHttpPost("status=4;Authentication error\ncost=");

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication error', $result->error);
        $this->assertSame('4', (string) ($result->meta['aobox_status'] ?? ''));
    }

    public function testDoSendReturnsFailedWhenBodyContainsError(): void
    {
        $this->configure();
        $this->mockHttpPost('error: invalid recipient');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('error', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsBalanceOnNumericResponse(): void
    {
        $this->configure();
        $this->mockHttpPost('42.5');

        $balance = $this->createProvider()->getCredit();

        $this->assertSame('42.5', $balance);
        $this->assertSame(
            'https://aobox.it/app/getcred3.php',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(self::USERNAME, $body['username']);
        $this->assertSame(self::PASSWORD, $body['password']);
    }

    public function testGetCreditReturnsNullOnUnauthorizedResponse(): void
    {
        $this->configure();
        $this->mockHttpPost('UNAUTHORIZED');

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionMapsUnauthorizedToInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost('UNAUTHORIZED');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }
}
