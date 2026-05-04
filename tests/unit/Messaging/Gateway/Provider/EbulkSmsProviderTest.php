<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\EbulkSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class EbulkSmsProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'user@example.com';
    private const APIKEY   = 'apikey-abcdef';
    private const SMS_FROM = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new EbulkSmsProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $defaultChannels = [
            'sms'      => ['from' => self::SMS_FROM],
            'whatsapp' => ['subject' => ''],
        ];
        foreach ($channelOverrides as $channel => $overrides) {
            $defaultChannels[$channel] = array_merge($defaultChannels[$channel] ?? [], $overrides);
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'ebulksms' => [
                'shared' => array_merge([
                    'username' => self::USERNAME,
                    'apikey'   => self::APIKEY,
                ], $sharedOverrides),
                'channels' => $defaultChannels,
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+2348012345678', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockSendResponse(array $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($body),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockSendSuccess(int $cost = 1, int $totalsent = 1): void
    {
        $this->mockSendResponse([
            'response' => [
                'status'    => 'SUCCESS',
                'totalsent' => $totalsent,
                'cost'      => $cost,
            ],
        ]);
    }

    private function mockGetResponse(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('ebulksms', $p->getId());
        $this->assertSame(['sms', 'whatsapp'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(EbulkSmsProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertSame('secret', $schema['shared']['apikey']['type']);
        $this->assertTrue($schema['shared']['apikey']['required']);

        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertArrayHasKey('whatsapp', $schema['channels']);
        $this->assertFalse($schema['channels']['sms']['from']['required']);
        $this->assertFalse($schema['channels']['whatsapp']['subject']['required']);
    }

    // --- isConfiguredForChannel ---

    public function testIsConfiguredForChannelSmsOk(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testIsConfiguredForChannelMissingApikey(): void
    {
        $this->configure(['apikey' => '']);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));
    }

    // --- Send: SMS ---

    public function testSmsSendBuildsExpectedJsonPayload(): void
    {
        $this->configure();
        $this->mockSendSuccess();

        $this->createProvider()->send($this->createMessage('sms', '+2348012345678', 'Hi there'));

        $this->assertSame('https://api.ebulksms.com/sendsms.json', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::USERNAME, $body['SMS']['auth']['username']);
        $this->assertSame(self::APIKEY, $body['SMS']['auth']['apikey']);
        $this->assertSame('Hi there', $body['SMS']['message']['messagetext']);
        $this->assertSame(self::SMS_FROM, $body['SMS']['message']['sender']);
        $this->assertSame('0', $body['SMS']['message']['flash']);
        $this->assertSame('2348012345678', $body['SMS']['recipients']['gsm'][0]['msidn']);
        $this->assertNotEmpty($body['SMS']['recipients']['gsm'][0]['msgid']);
        $this->assertSame('0', $body['dndsender']);
    }

    public function testSmsSendSuccessReturnsSentWithProviderIdAndCost(): void
    {
        $this->configure();
        $this->mockSendSuccess(cost: 5, totalsent: 1);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNotNull($result->providerId);
        $this->assertSame(5.0, $result->cost);
    }

    public function testSmsSendInsufficientCreditReturnsFailed(): void
    {
        $this->configure();
        $this->mockSendResponse(['response' => ['status' => 'INSUFFICIENT_CREDIT']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credit', strtolower($result->error));
        $this->assertSame('INSUFFICIENT_CREDIT', $result->meta['ebulksms_status'] ?? null);
    }

    public function testSmsSendAuthFailureReturnsFailed(): void
    {
        $this->configure();
        $this->mockSendResponse(['response' => ['status' => 'AUTH_FAILURE']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credentials', strtolower($result->error));
    }

    public function testSmsSendInvalidRecipientReturnsFailed(): void
    {
        $this->configure();
        $this->mockSendResponse(['response' => ['status' => 'INVALID_RECIPIENT']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('recipient', strtolower($result->error));
    }

    public function testSmsSendFlashFlagPropagatesFromMeta(): void
    {
        $this->configure();
        $this->mockSendSuccess();

        $this->createProvider()->send($this->createMessage('sms', '+2348012345678', 'flash msg', ['flash' => true]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('1', $body['SMS']['message']['flash']);
    }

    // --- Send: WhatsApp ---

    public function testWhatsappSendDispatchesToWhatsappEndpoint(): void
    {
        $this->configure();
        $this->mockSendSuccess();

        $this->createProvider()->send($this->createMessage('whatsapp', '+2348012345678', 'Hello WA'));

        $this->assertSame('https://api.ebulksms.com/sendwhatsapp.json', $GLOBALS['_test_wp_remote_post_last_url']);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::USERNAME, $body['WA']['auth']['username']);
        $this->assertSame('Hello WA', $body['WA']['message']['messagetext']);
        $this->assertSame(['2348012345678'], $body['WA']['recipients']);
    }

    public function testWhatsappSendUsesSubjectFromMetaThenChannelConfig(): void
    {
        $this->configure([], ['whatsapp' => ['subject' => 'Default Title']]);
        $this->mockSendSuccess();

        $this->createProvider()->send($this->createMessage('whatsapp', '+2348012345678', 'msg', ['subject' => 'From Meta']));
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('From Meta', $body['WA']['message']['subject']);

        $this->createProvider()->send($this->createMessage('whatsapp', '+2348012345678', 'msg'));
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('Default Title', $body['WA']['message']['subject']);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsFormattedBalance(): void
    {
        $this->configure();
        $this->mockGetResponse('1234.50');

        $credit = $this->createProvider()->getCredit();

        $this->assertNotNull($credit);
        $this->assertStringContainsString('1234.50', $credit);
        $this->assertStringContainsString('units', $credit);
    }

    public function testGetCreditReturnsNullOnAuthError(): void
    {
        $this->configure();
        $this->mockGetResponse('Unauthorized', 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionOkOnSuccessfulBalance(): void
    {
        $this->configure();
        $this->mockGetResponse('500');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500', $result->message);
        $this->assertStringContainsString('Balance', $result->message);
    }

    public function testTestConnectionInvalidOn401(): void
    {
        $this->configure();
        $this->mockGetResponse('Unauthorized', 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }
}
