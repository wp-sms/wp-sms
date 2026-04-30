<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmspointProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmspointProviderTest extends AbstractProviderTestCase
{
    private const API_TOKEN = '56b560a2-57cd-4071-8e6e-6eacb1979107';
    private const FROM = 'WSMS';
    private const SEND_URL = 'https://app.smspoint.de/public/api/v1/sms/send';

    protected function createProvider(): AbstractProvider
    {
        return new SmspointProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smspoint' => [
                'shared'   => array_merge(['api_token' => self::API_TOKEN], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+491701234567', string $body = 'Hello'): Message
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

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmspointProvider::TESTED);
    }

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smspoint', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue((bool) ($schema['shared']['api_token']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    // --- Send ---

    public function testSendReturnsSentOnSuccessTrue(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNull($result->providerId);
    }

    public function testSendReturnsFailedOnSuccessFalseWithErrorMessage(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => false, 'errorMessage' => "Token hasn't been provided"]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame("Token hasn't been provided", $result->error);
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true]);

        $this->createProvider()->send($this->createMessage('+491701234567'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('491701234567', $body['phone']);
    }

    public function testSendDoesNotMutateRecipientWithoutPlus(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true]);

        $this->createProvider()->send($this->createMessage('491701234567'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('491701234567', $body['phone']);
    }

    public function testSendSendsXAuthTokenAndJsonHeaders(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_TOKEN, $args['headers']['X-Auth-Token']);
        $this->assertSame('application/json;charset=UTF-8', $args['headers']['Content-Type']);
        $this->assertSame('application/json', $args['headers']['Accept']);
    }

    public function testSendBodyShape(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => true]);

        $this->createProvider()->send($this->createMessage('+491701234567', 'Welcome to SMS Point'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(['senderName', 'body', 'phone'], array_keys($body));
        $this->assertSame(self::FROM, $body['senderName']);
        $this->assertSame('Welcome to SMS Point', $body['body']);
        $this->assertSame('491701234567', $body['phone']);
    }

    public function testSendReturnsFailedOnNetworkError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'cURL timeout');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('cURL timeout', $result->error);
    }

    public function testSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFallsBackToHttpCodeWhenResponseHasNoErrorMessage(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => false], 502);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('502', $result->error);
    }

    // --- Inherited defaults (the registry's features.{test_connection,delivery_receipt}=false relies on these) ---

    public function testGetCreditReturnsNullByDefault(): void
    {
        $this->configure();
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsErrorByDefault(): void
    {
        $this->configure();
        $result = $this->createProvider()->testConnection();
        $this->assertFalse($result->success);
    }
}
