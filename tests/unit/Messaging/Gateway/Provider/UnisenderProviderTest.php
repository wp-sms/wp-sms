<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\UnisenderProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class UnisenderProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'unisender-api-key-1234567890';
    private const SENDER  = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new UnisenderProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $defaultChannels = [
            'sms' => ['from' => self::SENDER],
        ];
        foreach ($channelOverrides as $channel => $overrides) {
            $defaultChannels[$channel] = array_merge($defaultChannels[$channel] ?? [], $overrides);
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'unisender' => [
                'shared'   => array_merge(['api_key' => self::API_KEY], $sharedOverrides),
                'channels' => $defaultChannels,
            ],
        ];
    }

    private function createMessage(string $recipient = '+79091234567', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body, null, []);
    }

    private function mockPost(array $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($body),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(UnisenderProvider::TESTED);
    }

    public function testGetIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('unisender', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
    }

    // --- Send ---

    public function testDoSendPostsFormEncodedBodyToSendSms(): void
    {
        $this->configure();
        $this->mockPost(['result' => ['currency' => 'RUB', 'price' => 1.5, 'sms_id' => 'sms-001']]);

        $this->createProvider()->send($this->createMessage('+79091234567', 'Hello'));

        $this->assertSame('https://api.unisender.com/en/api/sendSms', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $form);
        $this->assertSame(self::API_KEY, $form['api_key']);
        $this->assertSame('79091234567', $form['phone']);
        $this->assertSame(self::SENDER, $form['sender']);
        $this->assertSame('Hello', $form['text']);
        $this->assertSame('json', $form['format']);
    }

    public function testDoSendStripsNonDigitsFromPhone(): void
    {
        $this->configure();
        $this->mockPost(['result' => ['sms_id' => 'sms-002']]);

        $this->createProvider()->send($this->createMessage('+1 (555) 555-1234', 'Hi'));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('15555551234', $form['phone']);
    }

    public function testDoSendReturnsSentWithSmsIdOnSuccess(): void
    {
        $this->configure();
        $this->mockPost(['result' => ['currency' => 'RUB', 'price' => 1.5, 'sms_id' => 'sms-007']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('sms-007', $result->providerId);
        $this->assertSame(1.5, $result->cost);
    }

    public function testDoSendReturnsFailedAndStashesErrorCodeOnApiError(): void
    {
        $this->configure();
        $this->mockPost(['error' => 'Destination phone is invalid', 'code' => 'dest_invalid']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Destination phone is invalid', $result->error);
        $this->assertSame('dest_invalid', $result->meta['unisender_error_code']);
    }

    public function testIsOptOutErrorTrueForUnsubscribedGlobally(): void
    {
        $result = DeliveryResult::failed('blocked', ['unisender_error_code' => 'unsubscribed_globally']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForOtherErrors(): void
    {
        $result = DeliveryResult::failed('boom', ['unisender_error_code' => 'dest_invalid']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));

        $noMeta = DeliveryResult::failed('boom');
        $this->assertFalse($this->createProvider()->isOptOutError($noMeta));
    }

    public function testDoSendFailsWithoutApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API key not configured', $result->error);
    }

    public function testDoSendFailsWithoutSender(): void
    {
        $this->configure([], ['sms' => ['from' => '']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sender name not configured', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsBalanceWithCurrency(): void
    {
        $this->configure();
        $this->mockPost(['result' => ['login' => 'me', 'email' => 'me@example.com', 'balance' => '100.50', 'currency' => 'RUB']]);

        $this->assertSame('100.50 RUB', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnApiError(): void
    {
        $this->configure();
        $this->mockPost(['error' => 'invalid api key', 'code' => 'invalid_api_key']);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionOkReturnsBalance(): void
    {
        $this->configure();
        $this->mockPost(['result' => ['balance' => '42.00', 'currency' => 'RUB']]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42.00 RUB', $result->message);
        $this->assertSame('42.00', $result->details['balance']);
        $this->assertSame('RUB', $result->details['currency']);
    }

    public function testTestConnectionMapsInvalidApiKeyError(): void
    {
        $this->configure();
        $this->mockPost(['error' => 'invalid api key', 'code' => 'invalid_api_key']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Unisender API key', $result->message);
    }
}
