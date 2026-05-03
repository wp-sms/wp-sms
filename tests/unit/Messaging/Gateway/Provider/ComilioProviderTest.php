<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\ComilioProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class ComilioProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'comilio-user@example.com';
    private const PASSWORD = 'comilio-secret';
    private const SENDER   = 'WSMS';

    protected function createProvider(): AbstractProvider
    {
        return new ComilioProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'comilio' => [
                'shared' => array_merge([
                    'username'     => self::USERNAME,
                    'password'     => self::PASSWORD,
                    'message_type' => 'SmartPro',
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+393480000000', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 201): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(ComilioProvider::TESTED);
    }

    public function testGetIdAndSupportedChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('comilio', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testGetConfigSchemaIncludesMessageTypeSelect(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('message_type', $schema['shared']);
        $this->assertSame('select', $schema['shared']['message_type']['type']);
        $this->assertTrue($schema['shared']['message_type']['required']);
        $this->assertSame('SmartPro', $schema['shared']['message_type']['default']);

        $values = array_column($schema['shared']['message_type']['options'], 'value');
        $this->assertSame(['Classic', 'Smart', 'SmartPro'], $values);

        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertFalse((bool) ($schema['channels']['sms']['from']['required'] ?? true));
    }

    public function testGetFeaturesAdvertisesUnicodeAndTestConnection(): void
    {
        $features = $this->createProvider()->getFeatures();
        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
        $this->assertFalse($features['delivery_receipt']);
        $this->assertFalse($features['incoming']);
    }

    // --- Send ---

    public function testDoSendReturnsSentOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost(['message_id' => 'abc123'], 201);

        $result = $this->createProvider()->send($this->createMessage('+393480000001', 'Ciao'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('abc123', $result->providerId);

        $this->assertSame(
            'https://api.comilio.it/rest/v1/message',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $expectedAuth = 'Basic ' . base64_encode(self::USERNAME . ':' . self::PASSWORD);
        $this->assertSame($expectedAuth, $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('SmartPro', $body['message_type']);
        $this->assertSame(['+393480000001'], $body['phone_numbers']);
        $this->assertSame('Ciao', $body['text']);
    }

    public function testDoSendIncludesSenderStringForSmartTier(): void
    {
        $this->configure(['message_type' => 'SmartPro']);
        $this->mockHttpPost(['message_id' => 'm1'], 201);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayHasKey('sender_string', $body);
        $this->assertSame(self::SENDER, $body['sender_string']);
    }

    public function testDoSendOmitsSenderStringForClassicTier(): void
    {
        $this->configure(['message_type' => 'Classic']);
        $this->mockHttpPost(['message_id' => 'm1'], 201);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('sender_string', $body);
        $this->assertSame('Classic', $body['message_type']);
    }

    public function testDoSendOmitsSenderStringWhenFromBlank(): void
    {
        $this->configure([], ['from' => '']);
        $this->mockHttpPost(['message_id' => 'm1'], 201);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('sender_string', $body);
    }

    public function testDoSendReturnsFailedOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testDoSendReturnsFailedOnApiError(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'invalid recipient'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('invalid recipient', $result->error);
    }

    public function testDoSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsConfiguredTierQuantity(): void
    {
        $this->configure(['message_type' => 'SmartPro']);
        $this->mockHttpGet([
            ['message_type' => 'Classic',       'quantity' => 100],
            ['message_type' => 'Smart',         'quantity' => 50],
            ['message_type' => 'SmartPro',      'quantity' => 25],
            ['message_type' => 'International', 'quantity' => 10],
        ]);

        $this->assertSame('25', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsClassicWhenConfigured(): void
    {
        $this->configure(['message_type' => 'Classic']);
        $this->mockHttpGet([
            ['message_type' => 'Classic',  'quantity' => 100],
            ['message_type' => 'Smart',    'quantity' => 50],
            ['message_type' => 'SmartPro', 'quantity' => 25],
        ]);

        $this->assertSame('100', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnHttpError(): void
    {
        $this->configure();
        $this->mockHttpGet([], 500);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure(['message_type' => 'SmartPro']);
        $this->mockHttpGet([
            ['message_type' => 'SmartPro', 'quantity' => 42],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42', $result->message);
        $this->assertSame('42', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpGet(['error' => 'unauthorized'], 401);

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
