<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\TextplodeProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class TextplodeProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'tp-test-key-1234';
    private const FROM = 'WSMS';

    private const SEND_URL = 'https://api.textplode.com/v3/messages/send';
    private const CREDITS_URL = 'https://api.textplode.com/v3/account/get/credits';

    protected function createProvider(): AbstractProvider
    {
        return new TextplodeProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'textplode' => [
                'shared'   => array_merge([
                    'api_key' => self::API_KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::FROM], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+447700900123', string $body = 'Hello'): Message
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

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('textplode', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(TextplodeProvider::TESTED);
    }

    public function testConfigSchemaHasApiKeyAndFrom(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue((bool) ($schema['shared']['api_key']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredFalseWhenFromMissing(): void
    {
        $this->configure(smsOverrides: ['from' => '']);
        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testValidateConfigRejectsMissingApiKey(): void
    {
        $this->assertFalse($this->createProvider()->validateConfig(['shared' => []]));
    }

    // --- Send ---

    public function testSendReturnsSentWithProviderIdFromMessageIds(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => [],
            'data'   => [[
                'campaign_id' => 'C-001',
                'message_ids' => ['M-abc-123'],
            ]],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('M-abc-123', $result->providerId);
    }

    public function testSendPostsCorrectPayloadAndJsonHeaders(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => [],
            'data'   => [['campaign_id' => 'C', 'message_ids' => ['M']]],
        ]);

        $this->createProvider()->send($this->createMessage('+447700900123', 'Hi there'));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);
        $this->assertSame('application/json', $args['headers']['Accept']);
        $this->assertArrayNotHasKey('Authorization', $args['headers']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::API_KEY, $body['api_key']);
        $this->assertSame(self::FROM, $body['from']);
        $this->assertSame('Hi there', $body['message']);
        $this->assertSame('447700900123', $body['recipients'][0]['phone_number']); // leading + stripped
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => [],
            'data'   => [['campaign_id' => 'C', 'message_ids' => ['M']]],
        ]);

        $this->createProvider()->send($this->createMessage('+15551234567', 'Hi'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('15551234567', $body['recipients'][0]['phone_number']);
    }

    public function testSendDoesNotStripWhenRecipientHasNoPlus(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => [],
            'data'   => [['campaign_id' => 'C', 'message_ids' => ['M']]],
        ]);

        $this->createProvider()->send($this->createMessage('447700900123', 'Hi'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('447700900123', $body['recipients'][0]['phone_number']);
    }

    public function testSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsAuthErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => ['errorMessage' => 'Forbidden', 'errorCode' => '401'],
            'data'   => [],
        ], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendSurfacesApiErrorEnvelopeOn200(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => [
                'errorMessage' => 'Insufficient credits',
                'errorCode'    => 'E101',
            ],
            'data' => [],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credits', $result->error);
        $this->assertSame('E101', $result->meta['textplode_code']);
    }

    public function testSendFailsOnNon2xxWithoutErrorEnvelope(): void
    {
        $this->configure();
        $this->mockHttpPost([], 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsCreditsString(): void
    {
        $this->configure();
        $this->mockHttpPost(['credits' => 247]);

        $this->assertSame('247', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOn401(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => ['errorMessage' => 'Forbidden', 'errorCode' => '401'],
        ], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditUsesCreditsEndpointWithApiKeyInBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['credits' => 500]);

        $this->createProvider()->getCredit();

        $this->assertSame(self::CREDITS_URL, $GLOBALS['_test_wp_remote_post_last_url']);
        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::API_KEY, $body['api_key']);
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpPost(['credits' => 15]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('15', $result->message);
        $this->assertSame(15, $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => ['errorMessage' => 'Forbidden', 'errorCode' => '401'],
        ], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionSurfacesErrorEnvelopeOn200(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'errors' => ['errorMessage' => 'Account suspended', 'errorCode' => 'E500'],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Account suspended', $result->message);
    }
}
