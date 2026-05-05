<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\ProsmsdkProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class ProsmsdkProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(): AbstractProvider
    {
        return new ProsmsdkProvider();
    }

    private function configureProvider(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'prosmsdk' => [
                'shared' => [
                    'api_key' => 'test_bearer_token',
                ],
                'channels' => [
                    'sms' => ['sender_name' => 'WSmsTest'],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+4520304050', string $body = 'Hello'): Message
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

    private function mockHttpGet(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
            'response' => ['code' => $statusCode],
        ];
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configureProvider();
        $provider = $this->createProvider();
        $this->assertTrue($provider->isConfigured());
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedWhenSenderNameMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'prosmsdk' => [
                'shared'   => ['api_key' => 'test_bearer_token'],
                'channels' => ['sms' => []],
            ],
        ];

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sender name', $result->error);
    }

    public function testSendPassesCorrectPayload(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'      => 'success',
            'messageCode' => 5000,
            'result'      => [
                'totalCreditSum' => 0.25,
                'messageSize'    => 1,
                'batchId'        => 'b-1',
                'report'         => ['accepted' => [['receiver' => 4520304050]], 'rejected' => []],
            ],
        ]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage('+4520304050', 'Hej'));

        $this->assertSame('https://api.sms.dk/v1/sms/send', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('Bearer test_bearer_token', $args['headers']['Authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame(4520304050, $body['receiver']);
        $this->assertSame('WSmsTest', $body['senderName']);
        $this->assertSame('Hej', $body['message']);
        $this->assertSame('UTF-8', $body['encoding']);
        $this->assertArrayNotHasKey('format', $body);
    }

    public function testSendReturnsQueuedOnSuccess(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'      => 'success',
            'messageCode' => 5000,
            'result'      => [
                'totalCreditSum' => 0.25,
                'messageSize'    => 1,
                'batchId'        => 'batch-abc',
                'report'         => ['accepted' => [['receiver' => 4520304050]], 'rejected' => []],
            ],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('batch-abc', $result->providerId);
        $this->assertSame('0.25', $result->meta['prosmsdk_credit_cost']);
    }

    public function testSendReturnsQueuedOn207Mixed(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status' => 'success',
            'result' => [
                'totalCreditSum' => 0.25,
                'batchId'        => 'batch-xyz',
                'report'         => ['accepted' => [['receiver' => 4520304050]], 'rejected' => []],
            ],
        ], 207);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('batch-xyz', $result->providerId);
    }

    public function testSendReturnsFailedWhenAllRejected(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'      => 'error',
            'messageCode' => 1059,
            'message'     => 'See rejected array',
            'result'      => [
                'totalCreditSum' => 0,
                'report'         => [
                    'accepted' => [],
                    'rejected' => [['receiver' => 4520304050, 'reason' => 'invalid number']],
                ],
            ],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('See rejected array', $result->error);
        $this->assertSame('1059', $result->meta['prosmsdk_code']);
    }

    public function testSendReturnsFailedOnApiError(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'      => 'error',
            'messageCode' => 1030,
            'message'     => 'Insufficient credits',
        ], 400);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credits', $result->error);
        $this->assertSame('1030', $result->meta['prosmsdk_code']);
        $this->assertSame('400', $result->meta['prosmsdk_http']);
    }

    public function testGetCreditHappyPath(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status'      => 'success',
            'messageCode' => 5005,
            'result'      => 700.2,
        ]);

        $provider = $this->createProvider();
        $this->assertSame('700.2', $provider->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $provider = $this->createProvider();
        $this->assertNull($provider->getCredit());
    }

    public function testTestConnectionHappyPath(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status'      => 'success',
            'messageCode' => 5005,
            'result'      => 42.5,
        ]);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42.5', $result->message);
        $this->assertSame('42.5', $result->details['credit']);
    }

    public function testTestConnectionReturnsErrorOnInvalidCredentials(): void
    {
        $this->configureProvider();
        $this->mockHttpGet(['message' => 'Unauthorized'], 401);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testGetConfigOptionsReturnsSenderNames(): void
    {
        $this->mockHttpGet([
            'status' => 'success',
            'result' => [
                ['senderName' => 'WSmsTest'],
                ['senderName' => 'BrandTwo'],
            ],
        ]);

        $config = [
            'shared'   => ['api_key' => 'draft_key'],
            'channels' => ['sms' => []],
        ];

        $provider = $this->createProvider();
        $options = $provider->getConfigOptions('sender_name', 'sms', $config);

        $this->assertCount(2, $options);
        $this->assertSame('WSmsTest', $options[0]['value']);
        $this->assertSame('WSmsTest', $options[0]['label']);
        $this->assertSame('BrandTwo', $options[1]['value']);
        $this->assertSame('BrandTwo', $options[1]['label']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $provider = $this->createProvider();
        $this->assertSame([], $provider->getConfigOptions('unknown_field', 'sms', []));
    }

    public function testConfigSchemaHasDynamicSenderName(): void
    {
        $provider = $this->createProvider();
        $schema = $provider->getConfigSchema();
        $this->assertTrue($schema['channels']['sms']['sender_name']['dynamic']);
    }

    public function testMetadataEmptyWithoutApiClient(): void
    {
        $provider = $this->createProvider();
        $this->assertEmpty($provider->getMetadata());
    }

    public function testFeaturesEmptyWithoutApiClient(): void
    {
        $provider = $this->createProvider();
        $this->assertEmpty($provider->getFeatures());
    }
}
