<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BulkSmsHyderabadProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BulkSmsHyderabadProviderTest extends AbstractProviderTestCase
{
    private const USERID = 'wsms-user';
    private const PASSWORD = 'wsms-pass';
    private const SENDER = 'WSMSIN';
    private const RECIPIENT = '919812345678';

    protected function createProvider(): AbstractProvider
    {
        return new BulkSmsHyderabadProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulksmshyderabad' => [
                'shared' => array_merge([
                    'userid'   => self::USERID,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $body = 'Hello world'): Message
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

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BulkSmsHyderabadProvider::TESTED);
    }

    public function testGetIdReturnsExpectedSlug(): void
    {
        $this->assertSame('bulksmshyderabad', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetConfigSchemaHasRequiredFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('userid', $schema['shared']);
        $this->assertSame('string', $schema['shared']['userid']['type']);
        $this->assertTrue($schema['shared']['userid']['required']);

        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $this->assertArrayHasKey('sender', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['sender']['type']);
        $this->assertTrue($schema['channels']['sms']['sender']['required']);
    }

    public function testIsConfiguredReturnsFalseWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulksmshyderabad' => [
                'shared' => ['userid' => '', 'password' => ''],
                'channels' => ['sms' => ['sender' => self::SENDER]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_wp_remote_get'] = function () {
            throw new \LogicException('HTTP must not be called when credentials are missing');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
    }

    public function testSendIssuesGetWithCorrectQueryString(): void
    {
        $this->configure();
        $this->captureGet('SUBMITTED');

        $this->createProvider()->send(new Message('sms', '+1234567890', 'Hello world'));

        $url = $GLOBALS['_test_wp_remote_get_last_url'];
        $this->assertStringStartsWith('http://tra.bulksmshyderabad.co.in/websms/sendsms.aspx?', $url);

        // Assert raw query string contains the URL-encoded values v7 used.
        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertStringContainsString('userid=' . self::USERID, $query);
        $this->assertStringContainsString('password=' . self::PASSWORD, $query);
        $this->assertStringContainsString('sender=' . self::SENDER, $query);
        $this->assertStringContainsString('mobileno=%2B1234567890', $query);
        $this->assertStringContainsString('msg=Hello+world', $query);
    }

    public function testSendReturnsSentOn200WithBodyAsProviderId(): void
    {
        $this->configure();
        $this->captureGet('OK 1234567890');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('OK 1234567890', $result->providerId);
    }

    public function testSendReturnsSentWithNullProviderIdOnEmptyBody(): void
    {
        $this->configure();
        $this->captureGet('   ');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertNull($result->providerId);
    }

    public function testSendReturnsFailedOnNon2xx(): void
    {
        $this->configure();
        $this->captureGet('Internal Server Error', 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
        $this->assertStringContainsString('Internal Server Error', $result->error);
    }

    public function testSendReturnsFailedOnNetworkError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'host unreachable');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('host unreachable', $result->error);
    }

    // --- Test connection ---

    public function testTestConnectionFailsWithoutCreds(): void
    {
        $GLOBALS['_test_wp_remote_get'] = function () {
            throw new \LogicException('HTTP must not be called when credentials are missing');
        };

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('User ID', $result->message);
    }

    public function testTestConnectionReturnsOkOn200(): void
    {
        $this->configure();
        $this->captureGet('Invalid Mobile Number');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Invalid Mobile Number', $result->message);
    }

    public function testTestConnectionReturnsErrorOnNon2xx(): void
    {
        $this->configure();
        $this->captureGet('boom', 503);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('503', $result->message);
    }

    public function testTestConnectionReturnsErrorOnNetworkFailure(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'down');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach', $result->message);
    }

    // --- Credit ---

    public function testGetCreditReturnsNull(): void
    {
        $this->configure();
        $this->assertNull($this->createProvider()->getCredit());
    }
}
