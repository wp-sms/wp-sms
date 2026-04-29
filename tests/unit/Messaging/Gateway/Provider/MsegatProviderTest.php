<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\MsegatProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class MsegatProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'mycompany';
    private const API_KEY = 'msegat-test-api-key';
    private const SENDER = 'Brand-AD';
    private const RECIPIENT = '966500000000';

    protected function createProvider(): AbstractProvider
    {
        return new MsegatProvider();
    }

    private function configure(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'msegat' => [
                'shared' => [
                    'username' => self::USERNAME,
                    'api_key'  => self::API_KEY,
                ],
                'channels' => [
                    'sms' => ['sender_name' => self::SENDER],
                ],
            ],
        ];
    }

    private function createMessage(string $body = 'Hello'): Message
    {
        return new Message('sms', self::RECIPIENT, $body, null, []);
    }

    private function mockHttpPost(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpPostJson(array $payload, int $statusCode = 200): void
    {
        $this->mockHttpPost(json_encode($payload), $statusCode);
    }

    private function mockHttpGetJson(array $payload, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($payload),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('msegat', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(MsegatProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);

        $sender = $schema['channels']['sms']['sender_name'];
        $this->assertSame('select', $sender['type']);
        $this->assertTrue($sender['dynamic']);
        $this->assertTrue($sender['required']);
    }

    // --- Send ---

    public function testSendSuccessReturnsProviderId(): void
    {
        $this->configure();
        $this->mockHttpPostJson(['code' => 1, 'message' => 'success', 'id' => 'msg-12345']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('msg-12345', $result->providerId);
    }

    public function testSendSuccessAcceptsM0000(): void
    {
        $this->configure();
        $this->mockHttpPostJson(['code' => 'M0000', 'message' => 'success']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
    }

    public function testSendPostsJsonBodyToCorrectUrl(): void
    {
        $this->configure();
        $this->mockHttpPostJson(['code' => 1, 'id' => 'x']);

        $this->createProvider()->send($this->createMessage('Verification Code: 1234'));

        $this->assertSame(
            'https://www.msegat.com/gw/sendsms.php',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(self::USERNAME, $body['userName']);
        $this->assertSame(self::API_KEY, $body['apiKey']);
        $this->assertSame(self::SENDER, $body['userSender']);
        $this->assertSame(self::RECIPIENT, $body['numbers']);
        $this->assertSame('Verification Code: 1234', $body['msg']);
        $this->assertSame('UTF8', $body['msgEncoding']);
    }

    /**
     * @dataProvider errorCodeProvider
     */
    public function testSendMapsErrorCodes(string $code, string $expectedFragment): void
    {
        $this->configure();
        $this->mockHttpPostJson(['code' => $code, 'message' => 'raw']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString($expectedFragment, $result->error);
        $this->assertSame($code, $result->meta['msegat_code']);
    }

    public static function errorCodeProvider(): array
    {
        return [
            'invalid login (M0002)' => ['M0002', 'Invalid login'],
            'low balance (1060)'    => ['1060', 'balance'],
            'free OTP (1064)'       => ['1064', 'Free OTP'],
            'bad sender (1110)'     => ['1110', 'Sender name'],
            'bad number (1120)'     => ['1120', 'Mobile number'],
        ];
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedWhenSenderMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'msegat' => [
                'shared'   => ['username' => self::USERNAME, 'api_key' => self::API_KEY],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sender name', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsBalanceString(): void
    {
        $this->configure();
        $this->mockHttpPost('1234');

        $this->assertSame('1234', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost('M0002');

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenSharedConfigMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionOkContainsCredit(): void
    {
        $this->configure();
        $this->mockHttpPost('5000');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5000', $result->message);
        $this->assertSame('5000', $result->details['credit']);
    }

    public function testTestConnectionErrorOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost('1020');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid login', $result->message);
    }

    public function testTestConnectionErrorWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- SupportsDynamicOptions ---

    public function testGetConfigOptionsReturnsActivatedSendersOnly(): void
    {
        $this->mockHttpGetJson([
            'code'    => 1,
            'senders' => [
                ['SenderID' => 'Brand-AD',  'Status' => 'Activated'],
                ['SenderID' => 'Pending1',  'Status' => 'Pending'],
                ['SenderID' => 'Refused1',  'Status' => 'Refused'],
                ['SenderID' => 'OtherSdr',  'Status' => 'Activated'],
            ],
        ]);

        $config = [
            'shared'   => ['username' => self::USERNAME, 'api_key' => self::API_KEY],
            'channels' => [],
        ];

        $options = $this->createProvider()->getConfigOptions('sender_name', 'sms', $config);

        $this->assertCount(2, $options);
        $this->assertSame('Brand-AD', $options[0]['value']);
        $this->assertSame('Brand-AD', $options[0]['label']);
        $this->assertSame('OtherSdr', $options[1]['value']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('unknown', 'sms', []));
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownSection(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('sender_name', 'whatsapp', []));
    }

    public function testGetConfigOptionsThrowsWhenCredentialsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->createProvider()->getConfigOptions('sender_name', 'sms', ['shared' => [], 'channels' => []]);
    }

    public function testGetConfigOptionsThrowsOnInvalidCredentials(): void
    {
        $this->mockHttpGetJson(['error' => 'unauth'], 401);

        $config = [
            'shared'   => ['username' => self::USERNAME, 'api_key' => self::API_KEY],
            'channels' => [],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid credentials');
        $this->createProvider()->getConfigOptions('sender_name', 'sms', $config);
    }
}
