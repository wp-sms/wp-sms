<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\GatewaySaProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class GatewaySaProviderTest extends AbstractProviderTestCase
{
    private const API_ID = 'gw-test-id';
    private const API_PASSWORD = 'gw-test-pass';
    private const SENDER_ID = 'WSMS';
    private const RECIPIENT = '966555000111';

    protected function createProvider(): AbstractProvider
    {
        return new GatewaySaProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gateway' => [
                'shared' => array_merge([
                    'api_id'       => self::API_ID,
                    'api_password' => self::API_PASSWORD,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_id' => self::SENDER_ID], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', self::RECIPIENT, $body, null, $meta);
    }

    private function captureGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($responseBody, $statusCode) {
            $GLOBALS['_test_wp_remote_get_last_url'] = $url;
            $GLOBALS['_test_wp_remote_get_last_args'] = $args;
            return [
                'body'     => json_encode($responseBody),
                'response' => ['code' => $statusCode],
            ];
        };
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('gateway', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(GatewaySaProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_id', $schema['shared']);
        $this->assertSame('string', $schema['shared']['api_id']['type']);
        $this->assertTrue($schema['shared']['api_id']['required']);

        $this->assertArrayHasKey('api_password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_password']['type']);
        $this->assertTrue($schema['shared']['api_password']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
    }

    public function testIsConfiguredFalseWhenSharedMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gateway' => [
                'shared' => ['api_id' => '', 'api_password' => ''],
                'channels' => ['sms' => ['sender_id' => self::SENDER_ID]],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredFalseWhenChannelMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'gateway' => [
                'shared' => ['api_id' => self::API_ID, 'api_password' => self::API_PASSWORD],
                'channels' => ['sms' => ['sender_id' => '']],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendSuccessReturnsMessageId(): void
    {
        $this->configure();
        $this->captureGet(['MessageID' => 'gw-msg-42']);

        $result = $this->createProvider()->send($this->createMessage('Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('gw-msg-42', $result->providerId);
    }

    public function testSendFailureWhenStatusF(): void
    {
        $this->configure();
        $this->captureGet(['status' => 'F', 'remarks' => 'Insufficient credit']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient credit', $result->error);
    }

    public function testSendFailsWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendBuildsCorrectQueryParamsForAscii(): void
    {
        $this->configure();
        $this->captureGet(['MessageID' => 'm1']);

        $this->createProvider()->send($this->createMessage('Hello world'));

        $url = $GLOBALS['_test_wp_remote_get_last_url'];
        $this->assertStringStartsWith('http://rest.gateway.sa/api/SendSMSMulti?', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertSame(self::API_ID, $params['api_id']);
        $this->assertSame(self::API_PASSWORD, $params['api_password']);
        $this->assertSame('T', $params['sms_type']);
        $this->assertSame('T', $params['encoding']);
        $this->assertSame(self::SENDER_ID, $params['sender_id']);
        $this->assertSame(self::RECIPIENT, $params['phonenumber']);
        $this->assertSame('Hello world', $params['textmessage']);
        $this->assertArrayNotHasKey('template_id', $params);
    }

    public function testSendUnicodeEncoding(): void
    {
        $this->configure();
        $this->captureGet(['MessageID' => 'm1']);

        $this->createProvider()->send($this->createMessage('مرحبا'));

        parse_str(parse_url($GLOBALS['_test_wp_remote_get_last_url'], PHP_URL_QUERY), $params);
        $this->assertSame('U', $params['encoding']);
        $this->assertSame('مرحبا', $params['textmessage']);
    }

    public function testSendForwardsProviderTemplateId(): void
    {
        $this->configure();
        $this->captureGet(['MessageID' => 'm1']);

        $this->createProvider()->send($this->createMessage('Hi', [
            'provider_template_id' => '909',
        ]));

        parse_str(parse_url($GLOBALS['_test_wp_remote_get_last_url'], PHP_URL_QUERY), $params);
        $this->assertSame('909', $params['template_id']);
    }

    public function testSendReturnsFailedOn500(): void
    {
        $this->configure();
        $this->captureGet(['error' => 'server'], 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('HTTP 500', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configure();
        $this->captureGet(['BalanceAmount' => '1500']);

        $this->assertSame('1500', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnFailure(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'down');

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test Connection ---

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $this->captureGet(['BalanceAmount' => '750']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('750', $result->message);
        $this->assertSame('750', $result->details['balance']);
    }

    public function testTestConnectionInvalidCredentials(): void
    {
        $this->configure();
        $this->captureGet(['status' => 'F', 'remarks' => 'Authentication failed']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Authentication failed', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionNetworkError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'down');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach', $result->message);
    }
}
