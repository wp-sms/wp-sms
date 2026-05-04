<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\EaziSMSproProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class EaziSMSproProviderTest extends AbstractProviderTestCase
{
    private const API_KEY   = 'eazi-test-api-key';
    private const SENDER_ID = 'WSmsBrand';

    protected function createProvider(): AbstractProvider
    {
        return new EaziSMSproProvider();
    }

    protected function setUp(): void
    {
        parent::setUp();
        unset(
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_get_last_url'],
            $GLOBALS['_test_wp_remote_get_last_args'],
        );
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'eazismspro' => [
                'shared' => array_merge([
                    'api_key' => self::API_KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_id' => self::SENDER_ID], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+233501234567', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function captureGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use ($responseBody, $statusCode) {
            $GLOBALS['_test_wp_remote_get_last_url']  = $url;
            $GLOBALS['_test_wp_remote_get_last_args'] = $args;
            return [
                'body'     => json_encode($responseBody),
                'response' => ['code' => $statusCode],
            ];
        };
    }

    // --- Identity & schema ---

    public function testTestedFlagIsTrueAfterManualEndToEndVerification(): void
    {
        $this->assertTrue(EaziSMSproProvider::TESTED);
    }

    public function testGetIdAndSupportedChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('eazismspro', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    // --- Send ---

    public function testSmsSendSuccessReturnsSent(): void
    {
        $this->configure();
        $this->captureGet(['code' => '100', 'message' => 'queued']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
    }

    public function testSmsSendInsufficientBalanceFails(): void
    {
        $this->configure();
        $this->captureGet(['code' => '103', 'message' => 'Insufficient balance']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient', $result->error);
    }

    public function testSmsSendInvalidApiKeyFails(): void
    {
        $this->configure();
        // Live API uses HTTP 422 + 3-digit code 102 for auth failures.
        $this->captureGet(['code' => '102', 'message' => 'Authentication Failed'], 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication Failed', $result->error);
    }

    public function testSmsSendAcceptsLegacyFourDigitSuccessCode(): void
    {
        $this->configure();
        $this->captureGet(['code' => '1000']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
    }

    public function testSmsSendBuildsCorrectQueryString(): void
    {
        $this->configure();
        $this->captureGet(['code' => '100']);

        $this->createProvider()->send($this->createMessage('+233501234567', 'Hi & welcome'));

        $url = $GLOBALS['_test_wp_remote_get_last_url'];
        $this->assertStringStartsWith('https://dashboard.eazismspro.com/sms/api?', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('send-sms', $query['action']);
        $this->assertSame(self::API_KEY, $query['api_key']);
        $this->assertSame(self::SENDER_ID, $query['from']);
        $this->assertSame('Hi & welcome', $query['sms']);
        $this->assertSame('+233501234567', $query['to']);
        $this->assertSame('json', $query['response']);
    }

    public function testSmsSendBulkRecipientsJoinsWithComma(): void
    {
        $this->configure();
        $this->captureGet(['code' => '100']);

        $this->createProvider()->send($this->createMessage('+233501234567,+233502222222'));

        parse_str(parse_url($GLOBALS['_test_wp_remote_get_last_url'], PHP_URL_QUERY), $query);
        $this->assertSame('+233501234567,+233502222222', $query['to']);
    }

    public function testSmsSendNetworkErrorFails(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'cURL error');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('cURL error', $result->error);
    }

    public function testSmsSendFailsWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsBalanceString(): void
    {
        $this->configure();
        $this->captureGet(['code' => '100', 'balance' => '42.50']);

        $this->assertSame('42.50', $this->createProvider()->getCredit());

        parse_str(parse_url($GLOBALS['_test_wp_remote_get_last_url'], PHP_URL_QUERY), $query);
        $this->assertSame('check-balance', $query['action']);
        $this->assertSame(self::API_KEY, $query['api_key']);
        $this->assertSame('json', $query['response']);
    }

    // --- testConnection ---

    public function testTestConnectionInvalidKeySurfacesApiMessageOn422(): void
    {
        $this->configure();
        // Live API: HTTP 422 + {"code":"102","message":"Authentication Failed"}.
        $this->captureGet(['code' => '102', 'message' => 'Authentication Failed'], 422);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication Failed', $result->message);
    }

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $this->captureGet(['code' => '100', 'balance' => '100.00']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('100.00', $result->message);
        $this->assertSame('100.00', $result->details['balance']);
    }

    public function testTestConnectionMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
