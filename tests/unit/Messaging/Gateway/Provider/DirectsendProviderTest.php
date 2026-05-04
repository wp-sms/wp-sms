<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\DirectsendProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class DirectsendProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'wsms-account';
    private const KEY = 'directsend-api-key-xxxxx';
    private const FROM = '0212345678';
    private const SEND_URL = 'https://directsend.co.kr/index.php/api_v2/sms_change_word';

    protected function createProvider(): AbstractProvider
    {
        return new DirectsendProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'directsend' => [
                'shared' => array_merge([
                    'username' => self::USERNAME,
                    'key'      => self::KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+821012345678', string $body = '안녕하세요'): Message
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

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(DirectsendProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('directsend', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue((bool) ($schema['shared']['username']['required'] ?? false));

        $this->assertArrayHasKey('key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['key']['type']);
        $this->assertTrue((bool) ($schema['shared']['key']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    public function testIsConfiguredForSmsRequiresAllSharedFields(): void
    {
        $this->configure(['key' => '']);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $this->configure(['username' => '']);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $this->configure([], ['from' => '']);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));

        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send ---

    public function testSendPostsToCorrectUrlWithJsonBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 0, 'message' => 'OK']);

        $this->createProvider()->send($this->createMessage('+821012345678', 'Hi'));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json; charset=utf-8', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::USERNAME, $body['username']);
        $this->assertSame(self::KEY, $body['key']);
        $this->assertSame(self::FROM, $body['sender']);
        $this->assertSame('Hi', $body['message']);
        $this->assertIsArray($body['receiver']);
        $this->assertCount(1, $body['receiver']);
        $this->assertSame(['mobile' => '01012345678'], $body['receiver'][0]);
    }

    public function testSendStripsKoreanCountryCodeAndPrefixesZero(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 0, 'message' => 'OK']);

        $this->createProvider()->send($this->createMessage('+821012345678'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('01012345678', $body['receiver'][0]['mobile']);
    }

    public function testSendStripsLeadingPlus82WhenAlreadyMissing(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 0, 'message' => 'OK']);

        $this->createProvider()->send($this->createMessage('1012345678'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('01012345678', $body['receiver'][0]['mobile']);
    }

    public function testSendQueuedOnStatusZero(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 0, 'message' => 'OK']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertNull($result->providerId);
    }

    public function testSendQueuedOnStatusOne(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 1, 'message' => 'OK']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
    }

    public function testSendFailedOnNonZeroOneStatus(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 4, 'message' => 'sender not registered']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('sender not registered', $result->error);
    }

    public function testSendFailedOnHttpError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => '<html>Server error</html>',
            'response' => ['code' => 500],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Inherited defaults ---

    public function testGetCreditReturnsNull(): void
    {
        $this->configure();
        $this->assertNull($this->createProvider()->getCredit());
    }
}
