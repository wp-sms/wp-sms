<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\UnifonicProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class UnifonicProviderTest extends AbstractProviderTestCase
{
    private const EMAIL = 'agent@example.com';
    private const PASSWORD = 'unifonic-password-1234';
    private const APP_SID = 'app-sid-abcdef';
    private const SENDER = 'WSMS';

    private const SEND_URL = 'https://el.cloud.unifonic.com/rest/SMS/messages';

    protected function createProvider(): AbstractProvider
    {
        return new UnifonicProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'unifonic' => [
                'shared'   => array_merge([
                    'account_email'    => self::EMAIL,
                    'account_password' => self::PASSWORD,
                    'app_sid'          => self::APP_SID,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['sender_id' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+966555123456', string $body = 'Hello'): Message
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

    private function mockHttpPostRaw(string $rawBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $rawBody,
            'response' => ['code' => $statusCode],
        ];
    }

    private function expectedAuthHeader(): string
    {
        return 'Basic ' . base64_encode(self::EMAIL . ':' . self::PASSWORD);
    }

    // --- Identity & schema ---

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('unifonic', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(UnifonicProvider::TESTED);
    }

    public function testConfigSchemaHasRequiredFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('account_email', $schema['shared']);
        $this->assertArrayHasKey('account_password', $schema['shared']);
        $this->assertArrayHasKey('app_sid', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['account_password']['type']);
        $this->assertSame('secret', $schema['shared']['app_sid']['type']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertTrue((bool) $schema['channels']['sms']['sender_id']['required']);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredFalseWhenAppSidMissing(): void
    {
        $this->configure(sharedOverrides: ['app_sid' => '']);
        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredFalseWhenSenderMissing(): void
    {
        $this->configure(smsOverrides: ['sender_id' => '']);
        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testValidateConfigRejectsMissingFields(): void
    {
        $this->assertFalse($this->createProvider()->validateConfig(['shared' => []]));
    }

    // --- Send ---

    public function testSendReturnsQueuedWithMessageIdOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success' => 'true',
            'message' => 'Message has been sent successfully',
            'data'    => [
                'MessageID' => '123456789',
                'SenderID'  => self::SENDER,
                'Recipient' => '966555123456',
                'Status'    => 'Sent',
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('123456789', $result->providerId);
    }

    public function testSendPostsCorrectPayloadAndAuthHeader(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success' => 'true',
            'data'    => ['MessageID' => 'id-1'],
        ]);

        $this->createProvider()->send($this->createMessage('+966555000111', 'Hi there'));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedAuthHeader(), $args['headers']['Authorization']);
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);
        $this->assertSame('application/json', $args['headers']['Accept']);

        parse_str($args['body'], $body);
        $this->assertSame(self::APP_SID, $body['AppSid']);
        $this->assertSame(self::SENDER, $body['SenderID']);
        $this->assertSame('966555000111', $body['Recipient']); // leading + stripped
        $this->assertSame('Hi there', $body['Body']);
        $this->assertSame('JSON', $body['responseType']);
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => 'true', 'data' => ['MessageID' => 'id-2']]);

        $this->createProvider()->send($this->createMessage('+966555111222', 'Hi'));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $body);
        $this->assertSame('966555111222', $body['Recipient']);
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
        $this->mockHttpPost(['success' => 'false', 'message' => 'Authentication failed'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendSurfacesErrorMessageWhenSuccessFalse(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'success'   => 'false',
            'errorCode' => '480',
            'message'   => 'Recipient is not valid',
        ], 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Recipient is not valid', $result->error);
        $this->assertSame('480', $result->meta['unifonic_code']);
    }

    public function testSendFailsGracefullyOnNonJsonBody(): void
    {
        $this->configure();
        $this->mockHttpPostRaw('<html>Service unavailable</html>', 503);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('503', $result->error);
    }

    // --- Test connection ---

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => 'false', 'message' => 'Authentication failed'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionReturnsOkOnValidationError(): void
    {
        // 449/480/482 → auth was good but the request was rejected for a non-auth
        // reason. That's exactly what we want from a recipient-less probe.
        $this->configure();
        $this->mockHttpPost([
            'success'   => 'false',
            'errorCode' => '449',
            'message'   => 'Recipient is missing',
        ], 449);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('valid', $result->message);
    }

    public function testTestConnectionUsesAuthenticatedProbe(): void
    {
        $this->configure();
        $this->mockHttpPost(['success' => 'false', 'message' => 'no recipient'], 449);

        $this->createProvider()->testConnection();

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame($this->expectedAuthHeader(), $args['headers']['Authorization']);

        parse_str($args['body'], $body);
        $this->assertSame(self::APP_SID, $body['AppSid']);
        $this->assertArrayNotHasKey('Recipient', $body); // probe omits recipient
    }
}
