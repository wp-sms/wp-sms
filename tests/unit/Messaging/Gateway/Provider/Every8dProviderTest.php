<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\Every8dProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class Every8dProviderTest extends AbstractProviderTestCase
{
    private const UID = 'tester';
    private const PWD = 'secret';

    protected function createProvider(): AbstractProvider
    {
        return new Every8dProvider();
    }

    private function configure(array $sharedOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'every8d' => [
                'shared' => array_merge([
                    'username' => self::UID,
                    'password' => self::PWD,
                ], $sharedOverrides),
            ],
        ];
    }

    private function mockHttpPost(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    private function createMessage(string $recipient = '+886912345678', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(Every8dProvider::TESTED);
    }

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('every8d', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaHasCredentials(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertTrue($schema['shared']['password']['required']);
        $this->assertArrayNotHasKey('channels', $schema);
    }

    // --- Send ---

    public function testSendReturnsSentOnPositiveCredit(): void
    {
        $this->configure();
        $this->mockHttpPost('100,1,1,0,batch-abc');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('batch-abc', $result->providerId);
    }

    public function testSendFailsOnNegativeCredit(): void
    {
        $this->configure();
        $this->mockHttpPost('-1,Authentication failure');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication failure', $result->error);
    }

    public function testSendFailsOnHttpError(): void
    {
        $this->configure();
        $this->mockHttpPost('', 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    public function testSendPostsExpectedBodyToSendUrl(): void
    {
        $this->configure();
        $this->mockHttpPost('100,1,1,0,batch-1');

        $this->createProvider()->send($this->createMessage('+886912345678', 'Hi'));

        $this->assertSame('https://oms.e8d.tw/API21/HTTP/sendSMS.ashx', $GLOBALS['_test_wp_remote_post_last_url']);
        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(self::UID, $body['UID']);
        $this->assertSame(self::PWD, $body['PWD']);
        $this->assertSame('Hi', $body['MSG']);
        $this->assertSame('+886912345678', $body['DEST']);
    }

    // --- Credit ---

    public function testGetCreditReturnsCreditValue(): void
    {
        $this->configure();
        $this->mockHttpPost('250,OK');

        $this->assertSame('250', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnNegativeCredit(): void
    {
        $this->configure();
        $this->mockHttpPost('-1,Authentication failure');

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionOkOnPositiveCredit(): void
    {
        $this->configure();
        $this->mockHttpPost('500,OK');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500', $result->message);
        $this->assertSame('500', $result->details['credit']);
    }

    public function testTestConnectionErrorOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost('-1,Authentication failure');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication failure', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
