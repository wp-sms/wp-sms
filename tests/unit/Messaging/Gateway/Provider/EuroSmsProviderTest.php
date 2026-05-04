<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\EuroSmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class EuroSmsProviderTest extends AbstractProviderTestCase
{
    private const IID      = '1-ABCDEF';
    private const KEY      = 'eurosms-integration-key-xxxxx';
    private const SENDER   = 'WSMS';
    private const SEND_URL = 'https://as.eurosms.com/api/v3/send/one';
    private const TEST_URL = 'https://as.eurosms.com/api/v3/test/one';

    protected function createProvider(): AbstractProvider
    {
        return new EuroSmsProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'eurosms' => [
                'shared' => array_merge([
                    'integration_id'  => self::IID,
                    'integration_key' => self::KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+421903622237', string $body = 'Hello'): Message
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
        $this->assertFalse(EuroSmsProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('eurosms', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('integration_id', $schema['shared']);
        $this->assertSame('string', $schema['shared']['integration_id']['type']);
        $this->assertTrue((bool) ($schema['shared']['integration_id']['required'] ?? false));

        $this->assertArrayHasKey('integration_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['integration_key']['type']);
        $this->assertTrue((bool) ($schema['shared']['integration_key']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));
    }

    // --- Send ---

    public function testSendSuccessReturnsSentWithProviderId(): void
    {
        $this->configure();
        $this->mockHttpPost(['err_code' => 'ENQUEUED', 'uuid' => ['uuid-1', 'uuid-2'], 'err_desc' => 'OK']);

        $result = $this->createProvider()->send($this->createMessage('+421903622237', 'Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('uuid-1', $result->providerId);

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json; charset=utf-8', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::IID, $body['iid']);
        $this->assertSame(self::SENDER, $body['sndr']);
        $this->assertSame('421903622237', $body['rcpt']);
        $this->assertSame('Hi', $body['txt']);
        $this->assertNotEmpty($body['sgn']);
        $this->assertSame(
            hash_hmac('sha1', self::SENDER . '421903622237' . 'Hi', self::KEY),
            $body['sgn'],
        );
    }

    public function testSendComputesUnicodeFlagsForDiacritics(): void
    {
        $this->configure();
        $this->mockHttpPost(['err_code' => 'ENQUEUED', 'uuid' => ['uuid-1']]);

        $this->createProvider()->send($this->createMessage('+421903622237', 'Ahoj ľščťžý'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(0x04, $body['flgs'] & 0x04, 'Unicode flag (0x04) should be set for diacritics');
    }

    public function testSendComputesLongAsciiFlag(): void
    {
        $this->configure();
        $this->mockHttpPost(['err_code' => 'ENQUEUED', 'uuid' => ['uuid-1']]);

        $longBody = str_repeat('A', 200);
        $this->createProvider()->send($this->createMessage('+421903622237', $longBody));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(0x02, $body['flgs'] & 0x02, 'Long flag (0x02) should be set for ASCII >160 chars');
        $this->assertSame(0, $body['flgs'] & 0x04, 'Unicode flag (0x04) should NOT be set for ASCII');
    }

    public function testSendComputesLongUnicodeFlagOver70Chars(): void
    {
        $this->configure();
        $this->mockHttpPost(['err_code' => 'ENQUEUED', 'uuid' => ['uuid-1']]);

        $longUnicode = str_repeat('ľ', 80);
        $this->createProvider()->send($this->createMessage('+421903622237', $longUnicode));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(0x06, $body['flgs'] & 0x06, 'Both unicode (0x04) and long (0x02) flags should be set');
    }

    public function testSendShortAsciiHasNoFlags(): void
    {
        $this->configure();
        $this->mockHttpPost(['err_code' => 'ENQUEUED', 'uuid' => ['uuid-1']]);

        $this->createProvider()->send($this->createMessage('+421903622237', 'Short message'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(0, $body['flgs']);
    }

    public function testSendStripsNonDigitsFromRecipient(): void
    {
        $this->configure();
        $this->mockHttpPost(['err_code' => 'ENQUEUED', 'uuid' => ['uuid-1']]);

        $this->createProvider()->send($this->createMessage('+421 903 622 237'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('421903622237', $body['rcpt']);
    }

    public function testSendFailureSurfacesErrList(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'err_code' => 'FAILED',
            'err_list' => [
                ['err_code' => 'NO_BALANCE', 'err_desc' => 'Account balance is insufficient'],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('NO_BALANCE', $result->error);
        $this->assertStringContainsString('insufficient', $result->error);
    }

    public function testSendFailureWithMultipleErrors(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'err_code' => 'FAILED',
            'err_list' => [
                ['err_code' => 'WRONG_SIGNATURE', 'err_desc' => 'Bad sig'],
                ['err_code' => 'INVALID_RCPT', 'err_desc' => 'Bad number'],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('WRONG_SIGNATURE', $result->error);
        $this->assertStringContainsString('INVALID_RCPT', $result->error);
    }

    public function testSendNetworkErrorReturnsFailedResult(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'cURL error 6: Could not resolve host');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('cURL', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailedOnHttpError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => '<html>Server error</html>',
            'response' => ['code' => 500],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    // --- Test connection ---

    public function testTestConnectionMissingCredentialsReturnsError(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Integration', $result->message);
    }

    public function testTestConnectionInvalidCredentialsReturnsError(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'err_code' => 'FAILED',
            'err_list' => [
                ['err_code' => 'WRONG_SIGNATURE', 'err_desc' => 'Bad signature'],
            ],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertSame(self::TEST_URL, $GLOBALS['_test_wp_remote_post_last_url']);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionInvalidCredentialsOn401(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => '',
            'response' => ['code' => 401],
        ];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionOkOnEnqueued(): void
    {
        $this->configure();
        $this->mockHttpPost(['err_code' => 'ENQUEUED', 'uuid' => ['test-uuid'], 'err_desc' => 'OK']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame(self::TEST_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame(self::IID, $body['iid']);
        $this->assertNotEmpty($body['sgn']);
        $this->assertNotEmpty($body['sndr']);
    }

    public function testTestConnectionUsesFallbackSenderWhenMissing(): void
    {
        $this->configure([], ['from' => '']);
        $this->mockHttpPost(['err_code' => 'ENQUEUED', 'uuid' => ['test-uuid']]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertNotEmpty($body['sndr']);
    }

    public function testTestConnectionNetworkErrorReturnsError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'connection timed out');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach', $result->message);
    }

    // --- Inherited defaults ---

    public function testGetCreditReturnsNull(): void
    {
        $this->configure();
        $this->assertNull($this->createProvider()->getCredit());
    }
}
