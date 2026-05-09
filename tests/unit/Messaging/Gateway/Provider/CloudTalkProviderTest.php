<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\CloudTalkProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class CloudTalkProviderTest extends AbstractProviderTestCase
{
    private const ACCESS_KEY_ID     = 'cloudtalk-access-key-id';
    private const ACCESS_KEY_SECRET = 'cloudtalk-access-key-secret';
    private const SENDER            = '+14155550100';
    private const RECIPIENT         = '+15559876543';

    protected function createProvider(): AbstractProvider
    {
        return new CloudTalkProvider();
    }

    private function configure(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'cloudtalk' => [
                'shared' => [
                    'access_key_id'     => self::ACCESS_KEY_ID,
                    'access_key_secret' => self::ACCESS_KEY_SECRET,
                ],
                'channels' => [
                    'sms' => ['from_number' => self::SENDER],
                ],
            ],
        ];
    }

    private function createMessage(string $body = 'Hello CloudTalk'): Message
    {
        return new Message('sms', self::RECIPIENT, $body);
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

    private function expectedBasicAuth(): string
    {
        return 'Basic ' . base64_encode(self::ACCESS_KEY_ID . ':' . self::ACCESS_KEY_SECRET);
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(CloudTalkProvider::TESTED);
    }

    public function testGetIdReturnsCloudtalk(): void
    {
        $this->assertSame('cloudtalk', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetConfigSchemaIncludesRequiredFields(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('access_key_id', $schema['shared']);
        $this->assertTrue($schema['shared']['access_key_id']['required']);
        $this->assertSame('string', $schema['shared']['access_key_id']['type']);

        $this->assertArrayHasKey('access_key_secret', $schema['shared']);
        $this->assertTrue($schema['shared']['access_key_secret']['required']);
        $this->assertSame('secret', $schema['shared']['access_key_secret']['type']);

        $this->assertArrayHasKey('from_number', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from_number']['required']);
    }

    public function testIsConfiguredReturnsTrueWithFullConfig(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testDoSendQueuesMessageOnSuccessfulApiResponse(): void
    {
        $this->configure();
        $this->mockHttpPost(['responseData' => ['id' => 12345]]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('12345', $result->providerId);
    }

    public function testDoSendFallsBackToGeneratedUuidWhenNoIdInResponse(): void
    {
        $this->configure();
        $this->mockHttpPost(['status' => 'ok']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertNotNull($result->providerId);
        $this->assertNotEmpty($result->providerId);
    }

    public function testDoSendReturnsFailedOnHttpError(): void
    {
        $this->configure();
        $this->mockHttpPost(['message' => 'Unauthorized'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Unauthorized', $result->error);
        $this->assertSame(401, $result->meta['status']);
        $this->assertSame('cloudtalk_api_error', $result->meta['cloudtalk_error']);
    }

    public function testDoSendReturnsFailedOnNetworkError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'Connection refused');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Connection refused', $result->error);
        $this->assertSame('cloudtalk_network_error', $result->meta['cloudtalk_error']);
    }

    public function testDoSendUsesBasicAuthHeader(): void
    {
        $this->configure();
        $captured = ['args' => null];
        $GLOBALS['_test_wp_remote_post'] = function (string $url, array $args) use (&$captured) {
            $captured['args'] = $args;
            return [
                'body'     => json_encode(['responseData' => ['id' => 'm-1']]),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->send($this->createMessage());

        $this->assertSame($this->expectedBasicAuth(), $captured['args']['headers']['Authorization']);
    }

    public function testDoSendPostsToCorrectEndpoint(): void
    {
        $this->configure();
        $captured = ['url' => null];
        $GLOBALS['_test_wp_remote_post'] = function (string $url, array $args) use (&$captured) {
            $captured['url'] = $url;
            return [
                'body'     => json_encode(['responseData' => ['id' => 'm-1']]),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('https://my.cloudtalk.io/api/sms/send.json', $captured['url']);
    }

    public function testDoSendIncludesRecipientSenderAndMessage(): void
    {
        $this->configure();
        $captured = ['args' => null];
        $GLOBALS['_test_wp_remote_post'] = function (string $url, array $args) use (&$captured) {
            $captured['args'] = $args;
            return [
                'body'     => json_encode(['responseData' => ['id' => 'm-1']]),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->send($this->createMessage('Hi from test'));

        $body = $captured['args']['body'];
        $this->assertIsArray($body);
        $this->assertSame(self::RECIPIENT, $body['recipient']);
        $this->assertSame(self::SENDER, $body['sender']);
        $this->assertSame('Hi from test', $body['message']);
    }

    public function testGetCreditReturnsNull(): void
    {
        $this->configure();
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkOn200(): void
    {
        $this->configure();
        $this->mockHttpGet([['id' => 1, 'name' => 'Agent A']]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('valid', $result->message);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['message' => 'Unauthorized'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
