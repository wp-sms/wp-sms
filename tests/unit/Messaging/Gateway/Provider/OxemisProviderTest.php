<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\OxemisProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class OxemisProviderTest extends AbstractProviderTestCase
{
    private const API_LOGIN = 'oxe-login-1234';
    private const API_PASSWORD = 'oxe-password-9999';
    private const SENDER = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new OxemisProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'oxemis' => [
                'shared'   => array_merge([
                    'api_login'    => self::API_LOGIN,
                    'api_password' => self::API_PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_id' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+33600111222', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function expectedAuth(): string
    {
        return 'Basic ' . base64_encode(self::API_LOGIN . ':' . self::API_PASSWORD);
    }

    // --- Identity & schema ---

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('oxemis', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(OxemisProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_login', $schema['shared']);
        $this->assertArrayHasKey('api_password', $schema['shared']);
        $this->assertSame('string', $schema['shared']['api_login']['type']);
        $this->assertSame('secret', $schema['shared']['api_password']['type']);
        $this->assertTrue($schema['shared']['api_login']['required']);
        $this->assertTrue($schema['shared']['api_password']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertFalse((bool) ($schema['channels']['sms']['sender_id']['required'] ?? true));
        $this->assertTrue((bool) ($schema['channels']['sms']['sender_id']['dynamic'] ?? false));
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendReturnsSentWithSendingId(): void
    {
        $this->configure();
        $this->mockHttpPost(['SendingId' => 'abc12345def']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('abc12345def', $result->providerId);
    }

    public function testSendPostsCorrectPayloadAndAuthHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['SendingId' => 's1']);

        $this->createProvider()->send($this->createMessage('+33600111222', 'Hi there'));

        $this->assertSame('https://api.oxisms.com/send', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedAuth(), $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);
        $this->assertSame('application/json', $args['headers']['Accept']);

        $body = json_decode($args['body'], true);
        $this->assertSame('Hi there', $body['Message']['Text']);
        $this->assertSame(self::SENDER, $body['Message']['Sender']);
        $this->assertSame([['PhoneNumber' => '+33600111222']], $body['Recipients']);
        $this->assertSame('notification', $body['Options']['Strategy']);
    }

    public function testSendOmitsSenderWhenNotConfigured(): void
    {
        $this->configure(smsOverrides: ['sender_id' => '']);
        $this->mockHttpPost(['SendingId' => 's2']);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('Sender', $body['Message']);
    }

    public function testSendForwardsUnicodeBodyAsIs(): void
    {
        $this->configure();
        $this->mockHttpPost(['SendingId' => 's-unicode']);

        $this->createProvider()->send($this->createMessage('+33600111222', 'سلام'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('سلام', $body['Message']['Text']);
    }

    public function testSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['Code' => 401, 'Message' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedOnApiError(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'Code'    => 1004,
            'Message' => 'Insufficient credit',
        ], 406);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credit', $result->error);
        $this->assertSame(1004, $result->meta['oxemis_code']);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalanceString(): void
    {
        $this->configure();
        $this->mockHttpGet(['CompanyName' => 'Acme', 'Credits' => 1234]);

        $this->assertSame('1234', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditUsesUserEndpointWithBasicAuth(): void
    {
        $this->configure();
        $captured = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode(['Credits' => 500]),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->getCredit();

        $this->assertSame('https://api.oxisms.com/user', $captured['url']);
        $this->assertSame($this->expectedAuth(), $captured['args']['headers']['Authorization']);
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithCredits(): void
    {
        $this->configure();
        $this->mockHttpGet(['Credits' => 250]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('250', $result->message);
        $this->assertSame('250', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['Code' => 401], 401);

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

    // --- Dynamic options (sender list) ---

    public function testGetConfigOptionsReturnsApprovedSendersOnly(): void
    {
        $this->mockHttpGet([
            'OK'      => ['Acme', 'Promo'],
            'PENDING' => ['NewSender'],
            'BLOCKED' => ['Banned'],
        ]);

        $config = [
            'shared'   => ['api_login' => self::API_LOGIN, 'api_password' => self::API_PASSWORD],
            'channels' => ['sms' => []],
        ];

        $options = $this->createProvider()->getConfigOptions('sender_id', 'sms', $config);

        $this->assertSame([
            ['value' => 'Acme',  'label' => 'Acme'],
            ['value' => 'Promo', 'label' => 'Promo'],
        ], $options);
    }

    public function testGetConfigOptionsReturnsEmptyWhenWrongField(): void
    {
        $config = [
            'shared'   => ['api_login' => self::API_LOGIN, 'api_password' => self::API_PASSWORD],
            'channels' => ['sms' => []],
        ];

        $this->assertSame([], $this->createProvider()->getConfigOptions('api_login', 'shared', $config));
        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_id', 'whatsapp', $config));
    }

    public function testGetConfigOptionsReturnsEmptyWhenUnconfigured(): void
    {
        $config = ['shared' => [], 'channels' => ['sms' => []]];

        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_id', 'sms', $config));
    }

    public function testGetConfigOptionsHandlesNoContent(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => '',
            'response' => ['code' => 204],
        ];

        $config = [
            'shared'   => ['api_login' => self::API_LOGIN, 'api_password' => self::API_PASSWORD],
            'channels' => ['sms' => []],
        ];

        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_id', 'sms', $config));
    }

    public function testGetConfigOptionsThrowsOn401(): void
    {
        $this->mockHttpGet(['Code' => 401], 401);

        $config = [
            'shared'   => ['api_login' => self::API_LOGIN, 'api_password' => self::API_PASSWORD],
            'channels' => ['sms' => []],
        ];

        $this->expectException(\RuntimeException::class);
        $this->createProvider()->getConfigOptions('sender_id', 'sms', $config);
    }
}
