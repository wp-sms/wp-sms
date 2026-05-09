<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BulkSMSbdProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BulkSMSbdProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'wsms-api-key';
    private const SENDER_ID = '8809XXXXXX';
    private const RECIPIENT = '8801712345678';

    protected function createProvider(): AbstractProvider
    {
        return new BulkSMSbdProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulksmsbd' => [
                'shared' => array_merge(['api_key' => self::API_KEY], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_id' => self::SENDER_ID], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $body = 'Hello', string $recipient = self::RECIPIENT): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function capturePost(string $body, int $statusCode = 202): void
    {
        $GLOBALS['_test_wp_remote_post'] = function ($url, $args) use ($body, $statusCode) {
            $GLOBALS['_test_wp_remote_post_last_url'] = $url;
            $GLOBALS['_test_wp_remote_post_last_args'] = $args;
            return [
                'body'     => $body,
                'response' => ['code' => $statusCode],
            ];
        };
    }

    // --- Identity & schema ---

    public function testIdentity(): void
    {
        $p = $this->createProvider();
        $this->assertSame('bulksmsbd', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
        $this->assertInstanceOf(AbstractProvider::class, $p);
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BulkSMSbdProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['sender_id']['type']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
    }

    // --- Send ---

    public function testDoSendSuccess(): void
    {
        $this->configure();
        $this->capturePost('202');

        $result = $this->createProvider()->send($this->createMessage('Hi there'));

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('queued', $result->status);

        $this->assertSame('https://bulksmsbd.net/api/smsapi', $GLOBALS['_test_wp_remote_post_last_url']);

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(self::API_KEY, $body['api_key']);
        $this->assertSame(self::SENDER_ID, $body['senderid']);
        $this->assertSame(self::RECIPIENT, $body['number']);
        $this->assertSame('Hi there', $body['message']);
        $this->assertSame('text', $body['type']);
    }

    public function testDoSendStripsPlusFromRecipient(): void
    {
        $this->configure();
        $this->capturePost('202');

        $this->createProvider()->send($this->createMessage('Hi', '+8801712345678'));

        $this->assertSame('8801712345678', $GLOBALS['_test_wp_remote_post_last_args']['body']['number']);
    }

    public function testDoSendNormalizesLocalNumber(): void
    {
        $this->configure();
        $this->capturePost('202');

        $this->createProvider()->send($this->createMessage('Hi', '01712345678'));

        $this->assertSame('8801712345678', $GLOBALS['_test_wp_remote_post_last_args']['body']['number']);
    }

    public function testDoSendFailsWithoutApiKey(): void
    {
        $this->configure(sharedOverrides: ['api_key' => '']);
        $GLOBALS['_test_wp_remote_post'] = function () {
            throw new \LogicException('HTTP must not be called when API key is missing');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API key', $result->error);
    }

    public function testDoSendFailsWithoutSenderId(): void
    {
        $this->configure(smsOverrides: ['sender_id' => '']);
        $GLOBALS['_test_wp_remote_post'] = function () {
            throw new \LogicException('HTTP must not be called when sender ID is missing');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    public function testDoSendFailsOnNonSuccessResponseCode(): void
    {
        $this->configure();
        $this->capturePost('1007', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('1007', $result->meta['bulksmsbd_code']);
        $this->assertSame('1007', $result->meta['bulksmsbd_response']);
    }

    public function testDoSendFailsOnInvalidLocalNumber(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = function () {
            throw new \LogicException('HTTP must not be called for an invalid recipient');
        };

        $result = $this->createProvider()->send($this->createMessage('Hi', '5551234'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configure();
        $this->capturePost(json_encode(['balance' => 1234.5]), 200);

        $balance = $this->createProvider()->getCredit();

        $this->assertSame('1234.5', $balance);
        $this->assertSame('https://bulksmsbd.net/api/getBalanceApi', $GLOBALS['_test_wp_remote_post_last_url']);
        $this->assertSame(self::API_KEY, $GLOBALS['_test_wp_remote_post_last_args']['body']['api_key']);
    }

    public function testGetCreditParsesPlainNumericBody(): void
    {
        $this->configure();
        $this->capturePost('45.00', 200);

        $this->assertSame('45', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->capturePost('Unauthorized', 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $this->capturePost(json_encode(['balance' => 50.00]), 200);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success, $result->message);
        $this->assertSame('50', $result->details['balance']);
        $this->assertSame('https://bulksmsbd.net/api/getBalanceApi', $GLOBALS['_test_wp_remote_post_last_url']);
    }

    public function testTestConnectionAuthFailure(): void
    {
        $this->configure();
        $this->capturePost('Unauthorized', 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionMissingApiKey(): void
    {
        $this->configure(sharedOverrides: ['api_key' => '']);
        $GLOBALS['_test_wp_remote_post'] = function () {
            throw new \LogicException('HTTP must not be called without API key');
        };

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
    }
}
