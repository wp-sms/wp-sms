<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BulkSMSgatewayProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BulkSMSgatewayProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'wsms-user';
    private const PASSWORD = 'wsms-pass';
    private const SENDER_ID = 'WSMSIN';
    private const RECIPIENT = '919812345678';

    protected function createProvider(): AbstractProvider
    {
        return new BulkSMSgatewayProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulksmsgateway' => [
                'shared' => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_id' => self::SENDER_ID], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $body = 'Hello'): Message
    {
        return new Message('sms', self::RECIPIENT, $body);
    }

    private function captureGet(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($body, $statusCode) {
            $GLOBALS['_test_wp_remote_get_last_url'] = $url;
            $GLOBALS['_test_wp_remote_get_last_args'] = $args;
            $GLOBALS['_test_wp_remote_get_call_count'] = ($GLOBALS['_test_wp_remote_get_call_count'] ?? 0) + 1;
            return [
                'body'     => $body,
                'response' => ['code' => $statusCode],
            ];
        };
        $GLOBALS['_test_wp_remote_get_call_count'] = 0;
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('bulksmsgateway', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
        $this->assertSame('BulkSMSgateway', $p->getName());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BulkSMSgatewayProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);

        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $this->assertArrayHasKey('type', $schema['shared']);
        $this->assertSame('string', $schema['shared']['type']['type']);
        $this->assertEmpty($schema['shared']['type']['required'] ?? false);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['sender_id']['type']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
    }

    public function testIsConfiguredFalseWhenSharedMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulksmsgateway' => [
                'shared' => ['username' => '', 'password' => ''],
                'channels' => ['sms' => ['sender_id' => self::SENDER_ID]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredTrueWhenAllSet(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendBuildsCorrectQueryString(): void
    {
        $this->configure();
        $this->captureGet('202401241234567890');

        $this->createProvider()->send($this->createMessage('Hello'));

        $url = $GLOBALS['_test_wp_remote_get_last_url'];
        $this->assertStringStartsWith('https://www.bulksmsgateway.in/sendmessage.php?', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertSame(self::USERNAME, $params['user']);
        $this->assertSame(self::PASSWORD, $params['password']);
        $this->assertSame(self::RECIPIENT, $params['mobile']);
        $this->assertSame('Hello', $params['message']);
        $this->assertSame(self::SENDER_ID, $params['sender']);
        $this->assertSame('3', $params['type']);
    }

    public function testSendUsesCustomType(): void
    {
        $this->configure(sharedOverrides: ['type' => '4']);
        $this->captureGet('202401241234567890');

        $this->createProvider()->send($this->createMessage());

        parse_str(parse_url($GLOBALS['_test_wp_remote_get_last_url'], PHP_URL_QUERY), $params);
        $this->assertSame('4', $params['type']);
    }

    public function testSendSuccessOnNumericId(): void
    {
        $this->configure();
        $this->captureGet('202401241234567890');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('202401241234567890', $result->providerId);
    }

    public function testSendSuccessOnSuccessKeyword(): void
    {
        $this->configure();
        $this->captureGet('Message Submitted Successfully');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
    }

    public function testSendFailureOnTextError(): void
    {
        $this->configure();
        $this->captureGet('Invalid Username or Password');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Username or Password', $result->error);
        $this->assertSame('Invalid Username or Password', $result->meta['bulksmsgateway_response']);
    }

    public function testSendFailureOnHttp500(): void
    {
        $this->configure();
        $this->captureGet('Internal Server Error', 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    public function testSendFailsWhenSenderMissing(): void
    {
        $this->configure(smsOverrides: ['sender_id' => '']);
        $GLOBALS['_test_wp_remote_get'] = function () {
            throw new \LogicException('HTTP must not be called when sender ID is missing');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
    }

    public function testSendNetworkFailure(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'down');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
    }
}
