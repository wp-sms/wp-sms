<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BulkSmsNigeriaProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BulkSmsNigeriaProviderTest extends AbstractProviderTestCase
{
    private const API_TOKEN = 'bsn_test_token_abcdef';

    protected function createProvider(): AbstractProvider
    {
        return new BulkSmsNigeriaProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulksmsnigeria' => [
                'shared'   => array_merge(['api_token' => self::API_TOKEN], $sharedOverrides),
                'channels' => ['sms' => $smsOverrides],
            ],
        ];
    }

    private function createMessage(string $recipient = '+2348031234567', string $body = 'Hello'): Message
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

    // --- Identity & schema ---

    public function testIdIsBulksmsnigeria(): void
    {
        $this->assertSame('bulksmsnigeria', $this->createProvider()->getId());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BulkSmsNigeriaProvider::TESTED);
    }

    public function testSupportedChannelsIsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testConfigSchemaHasApiTokenAndSenderId(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue($schema['shared']['api_token']['required']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertFalse((bool) ($schema['channels']['sms']['from']['required'] ?? true));
    }

    public function testConfigSchemaHasRouteSelectWithFourOptions(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $route  = $schema['channels']['sms']['route'];

        $this->assertSame('select', $route['type']);
        $this->assertSame('direct-refund', $route['default']);
        $this->assertCount(4, $route['options']);

        $values = array_column($route['options'], 'value');
        $this->assertSame(
            ['direct-refund', 'direct-corporate', 'otp', 'dual-backup'],
            $values,
        );

        // Each option must be an associative array with value+label (per CLAUDE.md select-field rule).
        foreach ($route['options'] as $opt) {
            $this->assertArrayHasKey('value', $opt);
            $this->assertArrayHasKey('label', $opt);
        }
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send: happy path ---

    public function testSendReturnsSuccessWithMessageIdAndCost(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success' => true,
            'data'    => [
                'message_id' => 'bsn_msg_12345',
                'cost'       => 2.5,
                'currency'   => 'NGN',
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('bsn_msg_12345', $result->providerId);
        $this->assertSame(2.5, $result->cost);
    }

    public function testSendAcceptsAlternateStatusSuccessShape(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'status'  => 'success',
            'data'    => [
                'recipients' => [
                    ['number' => '2348031234567', 'message_id' => 'bsn_alt_99'],
                ],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('bsn_alt_99', $result->providerId);
    }

    public function testSendStripsPlusFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true, 'data' => ['message_id' => 'x']]);

        $this->createProvider()->send($this->createMessage('+2348031234567'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('2348031234567', $body['to']);
    }

    public function testSendIncludesGatewayRouteWhenConfigured(): void
    {
        $this->configure(smsOverrides: ['route' => 'direct-corporate']);
        $this->mockHttpPost(['success' => true, 'data' => ['message_id' => 'x']]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('direct-corporate', $body['gateway']);
    }

    public function testSendOmitsRouteWhenDefault(): void
    {
        // Default route (direct-refund) should NOT be sent — keeps the request body
        // minimal and lets the provider apply its own server-side default.
        $this->configure(smsOverrides: ['route' => 'direct-refund']);
        $this->mockHttpPost(['success' => true, 'data' => ['message_id' => 'x']]);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('gateway', $body);
    }

    public function testSendUsesBearerAuthHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true, 'data' => ['message_id' => 'x']]);

        $this->createProvider()->send($this->createMessage());

        $headers = $GLOBALS['_test_wp_remote_post_last_args']['headers'];
        $this->assertSame('Bearer ' . self::API_TOKEN, $headers['Authorization']);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    public function testSendUsesV2Endpoint(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true, 'data' => ['message_id' => 'x']]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://www.bulksmsnigeria.com/api/v2/sms',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    // --- Send: errors ---

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
        $this->mockHttpPost(['error' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedOnInsufficientCredits(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success'    => false,
            'message'    => 'Insufficient credits',
            'error_code' => 'insufficient_balance',
        ], 402);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credits', $result->error);
        $this->assertSame('insufficient_balance', $result->meta['error_code']);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalanceWithCurrency(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'success' => true,
            'data'    => ['balance' => 497.5, 'currency' => 'NGN'],
        ]);

        $this->assertSame('NGN 497.5', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'success' => true,
            'data'    => ['balance' => 1000, 'currency' => 'NGN'],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('NGN 1000', $result->message);
        $this->assertSame('NGN 1000', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionReturnsErrorWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
