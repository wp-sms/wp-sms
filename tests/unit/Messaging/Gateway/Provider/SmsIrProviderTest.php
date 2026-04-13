<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsIrProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsIrProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(): AbstractProvider
    {
        return new SmsIrProvider();
    }

    private function configureProvider(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsir' => [
                'shared' => [
                    'api_key' => 'test_api_key_123',
                ],
                'channels' => [
                    'sms' => ['line_number' => '30001234567890'],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '09121234567', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, meta: $meta);
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

    // --- Send tests (raw SMS) ---

    public function testSendReturnsSuccessWithMessageId(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'  => 1,
            'message' => 'موفق',
            'data'    => [
                'packId'     => 'abc-123',
                'messageIds' => [86522023],
                'cost'       => 2.0,
            ],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('86522023', $result->providerId);
        $this->assertSame(2.0, $result->cost);
    }

    public function testSendReturnsFailedOnApiError(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'  => 0,
            'message' => 'اعتبار کافی نیست',
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('اعتبار کافی نیست', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendPassesCorrectPayload(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status' => 1,
            'data'   => ['messageIds' => [123], 'cost' => 1.0],
        ]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage('09121234567', 'Test message'));

        $lastArgs = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('test_api_key_123', $lastArgs['headers']['X-API-KEY']);
        $this->assertSame('application/json', $lastArgs['headers']['Content-Type']);

        $body = json_decode($lastArgs['body'], true);
        $this->assertSame('30001234567890', $body['lineNumber']);
        $this->assertSame('Test message', $body['messageText']);
        $this->assertSame(['09121234567'], $body['mobiles']);
    }

    public function testSendUrlPointsToBulkEndpoint(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status' => 1,
            'data'   => ['messageIds' => [1]],
        ]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage());

        $lastUrl = $GLOBALS['_test_wp_remote_post_last_url'];
        $this->assertSame('https://api.sms.ir/v1/send/bulk', $lastUrl);
    }

    public function testSendReturnsFailedWhenLineNumberMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsir' => [
                'shared' => ['api_key' => 'test_api_key_123'],
                'channels' => ['sms' => []],
            ],
        ];

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('line number', $result->error);
    }

    // --- Send via verify endpoint (template mode) ---

    public function testSendViaVerifyEndpointInTemplateMode(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status' => 1,
            'data'   => ['messageId' => 89545112, 'cost' => 1.0],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage('09121234567', '', [
            'template_mode'        => true,
            'provider_template_id' => '100200',
            'template_variables'   => ['Code' => '12345'],
        ]));

        $this->assertTrue($result->success);
        $this->assertSame('89545112', $result->providerId);
        $this->assertSame(1.0, $result->cost);

        $lastUrl = $GLOBALS['_test_wp_remote_post_last_url'];
        $this->assertSame('https://api.sms.ir/v1/send/verify', $lastUrl);
    }

    public function testSendVerifyPassesCorrectPayload(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status' => 1,
            'data'   => ['messageId' => 1],
        ]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage('09121234567', '', [
            'template_mode'        => true,
            'provider_template_id' => '555',
            'template_variables'   => ['Code' => '9999', 'Name' => 'Ali'],
        ]));

        $lastArgs = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('test_api_key_123', $lastArgs['headers']['X-API-KEY']);

        $body = json_decode($lastArgs['body'], true);
        $this->assertSame('09121234567', $body['mobile']);
        $this->assertSame(555, $body['templateId']);
        $this->assertCount(2, $body['parameters']);
        $this->assertSame(['name' => 'Code', 'value' => '9999'], $body['parameters'][0]);
        $this->assertSame(['name' => 'Name', 'value' => 'Ali'], $body['parameters'][1]);
    }

    public function testSendVerifyReturnsFailedOnApiError(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'status'  => 0,
            'message' => 'قالب یافت نشد',
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage('09121234567', '', [
            'template_mode'        => true,
            'provider_template_id' => '999',
            'template_variables'   => ['Code' => '1234'],
        ]));

        $this->assertFalse($result->success);
        $this->assertSame('قالب یافت نشد', $result->error);
    }

    // --- Credit tests ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status' => 1,
            'data'   => 12345.50,
        ]);

        $provider = $this->createProvider();
        $credit = $provider->getCredit();

        $this->assertSame('12345.5', $credit);
    }

    public function testGetCreditReturnsNullOnApiFailure(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status'  => 0,
            'message' => 'Error',
        ]);

        $provider = $this->createProvider();
        $this->assertNull($provider->getCredit());
    }

    // --- Test connection tests ---

    public function testTestConnectionReturnsOkWithCredit(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'status' => 1,
            'data'   => 500.25,
        ]);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500.25', $result->message);
        $this->assertSame('500.25', $result->details['credit']);
    }

    public function testTestConnectionReturnsErrorOnInvalidKey(): void
    {
        $this->configureProvider();
        $this->mockHttpGet(['message' => 'Unauthorized'], 401);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API key', $result->message);
    }

    public function testTestConnectionReturnsErrorWhenKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key is required', $result->message);
    }

    // --- Dynamic options (line number fetching) ---

    public function testGetConfigOptionsReturnsLineNumbers(): void
    {
        $provider = $this->createProvider();

        $this->mockHttpGet([
            'status' => 1,
            'data'   => [10002155613464, 30004505000017],
        ]);

        $config = [
            'shared' => ['api_key' => 'test_key'],
            'channels' => ['sms' => []],
        ];

        $options = $provider->getConfigOptions('line_number', 'sms', $config);

        $this->assertCount(2, $options);
        $this->assertSame('10002155613464', $options[0]['value']);
        $this->assertSame('10002155613464', $options[0]['label']);
        $this->assertSame('30004505000017', $options[1]['value']);
        $this->assertSame('30004505000017', $options[1]['label']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $provider = $this->createProvider();

        $options = $provider->getConfigOptions('unknown_field', 'sms', []);

        $this->assertSame([], $options);
    }

    // --- SupportsTemplates ---

    public function testRequiresTemplateReturnsFalse(): void
    {
        $provider = $this->createProvider();
        $this->assertFalse($provider->requiresTemplateForChannel('sms'));
    }

    public function testVariableStyleIsNamed(): void
    {
        $provider = $this->createProvider();
        $this->assertSame(VariableStyle::Named, $provider->getVariableStyle());
    }

    public function testBuildTemplatePayloadFormatsParameters(): void
    {
        $provider = $this->createProvider();

        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: '100200',
            gatewayId: 'smsir',
            language: 'fa',
            variableMap: ['otp_code' => 'Code'],
        );

        $payload = $provider->buildTemplatePayload($mapping, ['Code' => '482916']);

        $this->assertSame('send/verify', $payload['endpoint']);
        $this->assertSame(100200, $payload['templateId']);
        $this->assertCount(1, $payload['parameters']);
        $this->assertSame(['name' => 'Code', 'value' => '482916'], $payload['parameters'][0]);
    }

    public function testConfigSchemaHasDynamicLineNumber(): void
    {
        $provider = $this->createProvider();
        $schema = $provider->getConfigSchema();

        $this->assertTrue($schema['channels']['sms']['line_number']['dynamic']);
    }

    // --- Metadata & features tests ---

    public function testMetadataEmptyWithoutApiClient(): void
    {
        $provider = $this->createProvider();
        $metadata = $provider->getMetadata();

        $this->assertEmpty($metadata);
    }

    public function testFeaturesEmptyWithoutApiClient(): void
    {
        $provider = $this->createProvider();

        $this->assertEmpty($provider->getFeatures());
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configureProvider();

        $provider = $this->createProvider();
        $this->assertTrue($provider->isConfigured());
    }
}
