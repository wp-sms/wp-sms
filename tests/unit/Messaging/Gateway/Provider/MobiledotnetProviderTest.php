<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\MobiledotnetProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class MobiledotnetProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'wsms-user';
    private const PASSWORD = 'wsms-pass';
    private const SENDER_ID = 'WSMS';
    private const RECIPIENT = '966555000111';

    protected function createProvider(): AbstractProvider
    {
        return new MobiledotnetProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mobiledotnet' => [
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
        $this->assertSame('mobiledotnet', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
        $this->assertSame('MadarSMS (Mobiledotnet)', $p->getName());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(MobiledotnetProvider::TESTED);
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

        $this->assertArrayHasKey('route_id', $schema['shared']);
        $this->assertSame('string', $schema['shared']['route_id']['type']);
        $this->assertEmpty($schema['shared']['route_id']['required'] ?? false);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['sender_id']['type']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
    }

    public function testIsConfiguredFalseWhenSharedMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mobiledotnet' => [
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
        $this->captureGet('1');

        $this->createProvider()->send($this->createMessage('Hello'));

        $url = $GLOBALS['_test_wp_remote_get_last_url'];
        $this->assertStringStartsWith('https://mobile.net.sa/sms/gw/?', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertSame(self::USERNAME, $params['userName']);
        $this->assertSame(self::PASSWORD, $params['userPassword']);
        $this->assertSame(self::RECIPIENT, $params['numbers']);
        $this->assertSame(self::SENDER_ID, $params['userSender']);
        $this->assertSame('Hello', $params['msg']);
        $this->assertSame('standard', $params['By']);
    }

    public function testSendSuccessOnBody1(): void
    {
        $this->configure();
        $this->captureGet('1');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
    }

    public function testSendFailureMapsKnownErrorCode(): void
    {
        $this->configure();
        $this->captureGet('1020');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid login', $result->error);
        $this->assertSame('1020', $result->meta['mobiledotnet_code']);
    }

    public function testSendFailureUnknownCodeFallsBack(): void
    {
        $this->configure();
        $this->captureGet('9999');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('code 9999', $result->error);
        $this->assertSame('9999', $result->meta['mobiledotnet_code']);
    }

    public function testSendFailsWhenSenderMissing(): void
    {
        $this->configure(smsOverrides: ['sender_id' => '']);
        $GLOBALS['_test_wp_remote_get'] = function () {
            throw new \LogicException('HTTP must not be called when sender ID is missing');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sender ID not configured', $result->error);
    }

    public function testSendUsesCustomRouteId(): void
    {
        $this->configure(sharedOverrides: ['route_id' => 'premium']);
        $this->captureGet('1');

        $this->createProvider()->send($this->createMessage());

        parse_str(parse_url($GLOBALS['_test_wp_remote_get_last_url'], PHP_URL_QUERY), $params);
        $this->assertSame('premium', $params['By']);
    }

    // --- Credit ---

    public function testGetCreditReturnsNumericBody(): void
    {
        $this->configure();
        $this->captureGet('150');

        $this->assertSame('150', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnErrorCode(): void
    {
        $this->configure();
        $this->captureGet('1020');

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test Connection ---

    public function testTestConnectionOkOnNumericBody(): void
    {
        $this->configure();
        $this->captureGet('150');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('150', $result->message);
        $this->assertSame('150', $result->details['credit']);
    }

    public function testTestConnectionMapsErrorCodeToUserMessage(): void
    {
        $this->configure();
        $this->captureGet('1020');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid login', $result->message);
    }

    public function testTestConnectionDetectsTransportFailure(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'down');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach', $result->message);
    }
}
