<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\PayamresanProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class PayamresanProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(): AbstractProvider
    {
        return new PayamresanProvider();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset(
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get_last_url'],
            $GLOBALS['_test_wp_remote_get_last_args'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
    }

    private function configureProvider(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'payamresan' => [
                'shared'   => ['api_key' => 'test_api_key_123'],
                'channels' => ['sms' => ['from_number' => '+9810001234']],
            ],
        ];
    }

    private function createMessage(string $recipient = '09121234567', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, meta: $meta);
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($responseBody, $statusCode) {
            $GLOBALS['_test_wp_remote_get_last_url']  = $url;
            $GLOBALS['_test_wp_remote_get_last_args'] = $args;
            return [
                'body'     => json_encode($responseBody),
                'response' => ['code' => $statusCode],
            ];
        };
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Free-text send (GET /Send) ---

    public function testSendReturnsSuccessWithMessageId(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'Success' => true,
            'Result'  => [['Id' => 86522023]],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage('09121234567', 'Hello'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('86522023', $result->providerId);
    }

    public function testSendUsesGetSendEndpointWithQueryParams(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'Success' => true,
            'Result'  => [['Id' => 1]],
        ]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage('09121234567', 'Test message'));

        $url = $GLOBALS['_test_wp_remote_get_last_url'];
        $this->assertStringStartsWith('https://api.sms-webservice.com/api/V3/Send', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('test_api_key_123', $query['ApiKey']);
        $this->assertSame('+9810001234', $query['Sender']);
        $this->assertSame('09121234567', $query['Recipients']);
        $this->assertSame('Test message', $query['MessageBodies']);
    }

    public function testSendReturnsFailedOnApiError(): void
    {
        $this->configureProvider();
        $this->mockHttpGet([
            'Success'   => false,
            'Error'     => 'اعتبار کافی نیست',
            'ErrorCode' => 4,
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('اعتبار کافی نیست', $result->error);
        $this->assertStringContainsString('ErrorCode 4', $result->error);
    }

    public function testSendReturnsFailedWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API key not configured', $result->error);
    }

    public function testSendReturnsFailedWhenSenderMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'payamresan' => [
                'shared'   => ['api_key' => 'test_api_key_123'],
                'channels' => ['sms' => []],
            ],
        ];

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sender', $result->error);
    }

    // --- Template send (POST /SendTokenSingle) ---

    public function testTemplateModeUsesSendTokenSingle(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Success' => true,
            'Result'  => ['Id' => 99887766],
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage('09121234567', '', [
            'template_mode'        => true,
            'provider_template_id' => 'TPL123',
            'template_variables'   => ['1' => '4829', '2' => 'Ali'],
        ]));

        $this->assertTrue($result->success);
        $this->assertSame('99887766', $result->providerId);
        $this->assertSame(
            'https://api.sms-webservice.com/api/V3/SendTokenSingle',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('test_api_key_123', $body['ApiKey']);
        $this->assertSame('+9810001234', $body['Sender']);
        $this->assertSame('09121234567', $body['Recipient']);
        $this->assertSame('TPL123', $body['TemplateKey']);
        $this->assertSame('4829', $body['p1']);
        $this->assertSame('Ali', $body['p2']);
    }

    public function testTemplateModeFailureSurfacesApiError(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Success'   => false,
            'Error'     => 'قالب یافت نشد',
            'ErrorCode' => 12,
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage('09121234567', '', [
            'template_mode'        => true,
            'provider_template_id' => 'NOPE',
            'template_variables'   => ['1' => '1234'],
        ]));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('قالب یافت نشد', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Success' => true,
            'Result'  => ['Credit' => 12345.5, 'AvailableSenders' => []],
        ]);

        $provider = $this->createProvider();
        $this->assertSame('12345.5', $provider->getCredit());
        $this->assertSame(
            'https://api.sms-webservice.com/api/V3/AccountInfo',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testGetCreditReturnsNullOnApiFailure(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Success' => false,
            'Error'   => 'Auth failed',
        ]);

        $provider = $this->createProvider();
        $this->assertNull($provider->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionReturnsOkWithCredit(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Success' => true,
            'Result'  => ['Credit' => 500.25, 'AvailableSenders' => ['+9810001234']],
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
        $this->mockHttpPost(['Error' => 'Unauthorized'], 401);

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

    public function testTestConnectionSurfacesProviderError(): void
    {
        $this->configureProvider();
        $this->mockHttpPost(['Success' => false, 'Error' => 'Account suspended']);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Account suspended', $result->message);
    }

    // --- fetchTemplates ---

    public function testFetchTemplatesParsesTokenList(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Success' => true,
            'Result'  => [
                ['Key' => 'OTP1', 'Name' => 'OTP', 'TextTemplate' => 'Code: {1}', 'Status' => 1],
                ['Key' => 'WEL2', 'Name' => 'Welcome', 'TextTemplate' => 'Hi {1}, welcome to {2}!', 'Status' => 2],
                ['Key' => 'BAD3', 'Name' => 'Rejected', 'TextTemplate' => 'No {1}', 'Status' => 3],
            ],
        ]);

        $provider = $this->createProvider();
        $templates = $provider->fetchTemplates();

        $this->assertCount(3, $templates);
        $this->assertSame('OTP1', $templates[0]->id);
        $this->assertSame('OTP', $templates[0]->name);
        $this->assertSame('fa', $templates[0]->language);
        $this->assertSame('Code: {1}', $templates[0]->bodyText);
        $this->assertSame(1, $templates[0]->variableCount);
        $this->assertSame(TemplateStatus::Approved, $templates[0]->status);
        $this->assertSame(2, $templates[1]->variableCount);
        $this->assertSame(TemplateStatus::Pending, $templates[1]->status);
        $this->assertSame(TemplateStatus::Rejected, $templates[2]->status);
    }

    public function testFetchTemplatesReturnsEmptyOnApiFailure(): void
    {
        $this->configureProvider();
        $this->mockHttpPost(['Success' => false, 'Error' => 'Boom']);

        $provider = $this->createProvider();
        $this->assertSame([], $provider->fetchTemplates());
    }

    public function testFetchTemplatesReturnsEmptyWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $this->assertSame([], $provider->fetchTemplates());
    }

    // --- getConfigOptions (dynamic from_number) ---

    public function testGetConfigOptionsReturnsAvailableSenders(): void
    {
        $this->mockHttpPost([
            'Success' => true,
            'Result'  => [
                'Credit'           => 100,
                'AvailableSenders' => ['+9810001234', '+9810005678'],
            ],
        ]);

        $config = [
            'shared'   => ['api_key' => 'test_key'],
            'channels' => ['sms' => []],
        ];

        $provider = $this->createProvider();
        $options = $provider->getConfigOptions('from_number', 'sms', $config);

        $this->assertCount(2, $options);
        $this->assertSame('+9810001234', $options[0]['value']);
        $this->assertSame('+9810001234', $options[0]['label']);
        $this->assertSame('+9810005678', $options[1]['value']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $provider = $this->createProvider();
        $this->assertSame([], $provider->getConfigOptions('unknown', 'sms', []));
    }

    public function testGetConfigOptionsThrowsWhenApiKeyMissing(): void
    {
        $provider = $this->createProvider();

        $this->expectException(\RuntimeException::class);
        $provider->getConfigOptions('from_number', 'sms', ['shared' => [], 'channels' => []]);
    }

    // --- SupportsTemplates contract ---

    public function testRequiresTemplateReturnsFalse(): void
    {
        $provider = $this->createProvider();
        $this->assertFalse($provider->requiresTemplateForChannel('sms'));
    }

    public function testVariableStyleIsPositional(): void
    {
        $provider = $this->createProvider();
        $this->assertSame(VariableStyle::Positional, $provider->getVariableStyle());
    }

    public function testBuildTemplatePayloadFormatsPositionalParameters(): void
    {
        $provider = $this->createProvider();

        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'TPL777',
            gatewayId: 'payamresan',
            language: 'fa',
            variableMap: ['otp_code' => '1'],
        );

        $payload = $provider->buildTemplatePayload($mapping, ['1' => '482916']);

        $this->assertSame('SendTokenSingle', $payload['endpoint']);
        $this->assertSame('TPL777', $payload['TemplateKey']);
        $this->assertSame('482916', $payload['p1']);
    }

    public function testBuildTemplatePayloadIgnoresPositionsBeyondTen(): void
    {
        $provider = $this->createProvider();

        $mapping = new TemplateMapping(
            templateType: 'multi',
            providerTemplateId: 'TPL999',
            gatewayId: 'payamresan',
            language: 'fa',
            variableMap: [],
        );

        $vars = [];
        for ($i = 1; $i <= 12; $i++) {
            $vars[(string) $i] = "v{$i}";
        }

        $payload = $provider->buildTemplatePayload($mapping, $vars);

        $this->assertArrayHasKey('p10', $payload);
        $this->assertArrayNotHasKey('p11', $payload);
        $this->assertArrayNotHasKey('p12', $payload);
    }

    // --- Schema / metadata ---

    public function testConfigSchemaHasDynamicFromNumber(): void
    {
        $provider = $this->createProvider();
        $schema = $provider->getConfigSchema();

        $this->assertTrue($schema['channels']['sms']['from_number']['dynamic']);
        $this->assertTrue($schema['channels']['sms']['from_number']['required']);
        $this->assertTrue($schema['shared']['api_key']['required']);
    }

    public function testFeaturesAdvertiseUnicodeAndTestConnectionOnly(): void
    {
        $provider = $this->createProvider();
        $features = $provider->getFeatures();

        $this->assertTrue($features['unicode']);
        $this->assertTrue($features['test_connection']);
        $this->assertFalse($features['delivery_receipt']);
        $this->assertFalse($features['incoming']);
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configureProvider();
        $provider = $this->createProvider();
        $this->assertTrue($provider->isConfigured());
    }
}
