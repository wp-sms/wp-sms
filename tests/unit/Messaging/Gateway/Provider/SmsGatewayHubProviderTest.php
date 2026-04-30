<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsGatewayHubProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsGatewayHubProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'sgh-test-api-key';
    private const SENDER_ID = 'WSMSAB';
    private const ENTITY_ID = '1101000000000123456';
    private const TEMPLATE_ID = '1707170000000099999';

    /** @var array{0: string, 1: array}|null */
    private ?array $lastGet = null;

    protected function createProvider(): AbstractProvider
    {
        return new SmsGatewayHubProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
        $this->lastGet = null;
        parent::tearDown();
    }

    private function configure(array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsgatewayhub' => [
                'shared' => ['api_key' => self::API_KEY],
                'channels' => [
                    'sms' => array_merge([
                        'sender_id' => self::SENDER_ID,
                        'route'     => 'Trans',
                        'entity_id' => self::ENTITY_ID,
                    ], $channelOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '9999988888', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200): void
    {
        $payload = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
        $self = $this;
        $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($payload, $self) {
            $self->captureLastGet($url, $args);
            return $payload;
        };
    }

    public function captureLastGet(string $url, array $args): void
    {
        $this->lastGet = [$url, $args];
    }

    private function lastGetQuery(): array
    {
        $this->assertNotNull($this->lastGet, 'wp_remote_get was not called');
        $parsed = parse_url($this->lastGet[0]);
        $this->assertNotFalse($parsed);
        parse_str($parsed['query'] ?? '', $query);
        return $query;
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmsGatewayHubProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smsgatewayhub', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
        $this->assertSame('select', $schema['channels']['sms']['route']['type']);
        $this->assertSame('Trans', $schema['channels']['sms']['route']['default']);

        $values = array_column($schema['channels']['sms']['route']['options'], 'value');
        $this->assertSame(['Trans', 'Promo'], $values);

        $this->assertFalse($schema['channels']['sms']['entity_id']['required'] ?? true);
        $this->assertFalse($schema['channels']['sms']['template_id']['required'] ?? true);
    }

    // --- Send: SMS ---

    public function testSendBuildsCorrectQueryStringForAsciiBody(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '000', 'JobId' => 'job-123', 'MessageData' => [['Number' => '9999988888', 'MessageId' => 'msg-1']]]);

        $result = $this->createProvider()->send($this->createMessage('9999988888', 'Hi there'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('msg-1', $result->providerId);

        $this->assertStringStartsWith('https://www.smsgatewayhub.com/api/mt/SendSMS?', $this->lastGet[0]);

        $query = $this->lastGetQuery();
        $this->assertSame(self::API_KEY, $query['APIKey']);
        $this->assertSame(self::SENDER_ID, $query['senderid']);
        $this->assertSame('Trans', $query['channel']);
        $this->assertSame('0', $query['DCS']);
        $this->assertSame('0', $query['flashsms']);
        $this->assertSame('9999988888', $query['number']);
        $this->assertSame('Hi there', $query['text']);
        $this->assertSame('1', $query['route']);
        $this->assertSame(self::ENTITY_ID, $query['PEID']);
    }

    public function testSendUnicodeBodySetsDcs8(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '000', 'MessageData' => [['MessageId' => 'm']]]);

        $this->createProvider()->send($this->createMessage('9999988888', 'नमस्ते'));

        $this->assertSame('8', $this->lastGetQuery()['DCS']);
    }

    public function testSendSetsPromoChannelWhenConfigured(): void
    {
        $this->configure(['route' => 'Promo']);
        $this->mockHttpGet(['ErrorCode' => '000', 'MessageData' => [['MessageId' => 'm']]]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('Promo', $this->lastGetQuery()['channel']);
    }

    public function testSendStripsIndianCountryCodeVariants(): void
    {
        $this->configure();

        foreach (['+919999988888', '0091 9999988888', '91-9999988888', '9999988888'] as $input) {
            $this->mockHttpGet(['ErrorCode' => '000', 'MessageData' => [['MessageId' => 'm']]]);
            $this->createProvider()->send($this->createMessage($input));
            $this->assertSame('9999988888', $this->lastGetQuery()['number'], "wrong strip for {$input}");
        }
    }

    public function testSendFlashMetaSetsFlashSms(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '000', 'MessageData' => [['MessageId' => 'm']]]);

        $this->createProvider()->send($this->createMessage('9999988888', 'Hi', ['flash' => true]));

        $this->assertSame('1', $this->lastGetQuery()['flashsms']);
    }

    public function testSendFailsWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsWhenSenderIdMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsgatewayhub' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    public function testSendReturnsFailureWhenErrorCodeNotZero(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '015', 'ErrorMessage' => 'Invalid Sender Id']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid Sender Id', $result->error);
        $this->assertSame('015', $result->meta['smsgatewayhub_error_code']);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorMessage' => 'unauthorised'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    // --- Templates / DLT ---

    public function testSendIncludesPeidAndDltTemplateIdFromChannelConfig(): void
    {
        $this->configure(['template_id' => self::TEMPLATE_ID]);
        $this->mockHttpGet(['ErrorCode' => '000', 'MessageData' => [['MessageId' => 'm']]]);

        $this->createProvider()->send($this->createMessage());

        $query = $this->lastGetQuery();
        $this->assertSame(self::ENTITY_ID, $query['PEID']);
        $this->assertSame(self::TEMPLATE_ID, $query['DLTTemplateId']);
    }

    public function testSendOverridesDltTemplateIdViaTemplateModeMeta(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '000', 'MessageData' => [['MessageId' => 'm']]]);

        $this->createProvider()->send($this->createMessage('9999988888', 'fallback', [
            'template_mode'        => true,
            'provider_template_id' => '1707170000000088888',
            'template_body'        => 'Your code is {#var#}',
            'template_variables'   => ['1' => '482916'],
        ]));

        $query = $this->lastGetQuery();
        $this->assertSame('1707170000000088888', $query['DLTTemplateId']);
        $this->assertSame('Your code is 482916', $query['text']);
    }

    public function testSendUsesCatalogResolvedTemplate(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '000', 'MessageData' => [['MessageId' => 'm']]]);

        $catalog = $this->createMock(TemplateCatalogManager::class);
        $catalog->method('resolveMapping')->with('otp', 'smsgatewayhub')->willReturn(new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: self::TEMPLATE_ID,
            gatewayId: 'smsgatewayhub',
            language: 'en',
            variableMap: ['otp_code' => '1'],
            providerTemplateBody: 'Your OTP is {#var#}.',
        ));

        $provider = new SmsGatewayHubProvider();
        $provider->setCatalogManager($catalog);

        $provider->send($this->createMessage('9999988888', 'fallback', [
            'template_type'      => 'otp',
            'template_variables' => ['otp_code' => '482916'],
        ]));

        $query = $this->lastGetQuery();
        $this->assertSame(self::TEMPLATE_ID, $query['DLTTemplateId']);
        $this->assertSame('Your OTP is 482916.', $query['text']);
    }

    public function testRequiresTemplateForChannelReturnsFalse(): void
    {
        $this->assertFalse($this->createProvider()->requiresTemplateForChannel('sms'));
    }

    public function testVariableStyleIsPositional(): void
    {
        $this->assertSame(VariableStyle::Positional, $this->createProvider()->getVariableStyle());
    }

    public function testBuildTemplatePayloadReturnsTemplateIdAndRenderedText(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: self::TEMPLATE_ID,
            gatewayId: 'smsgatewayhub',
            language: 'en',
            variableMap: ['otp_code' => '1'],
            providerTemplateBody: 'Your code is {#var#}.',
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, ['1' => '482916']);

        $this->assertSame(self::TEMPLATE_ID, $payload['template_id']);
        $this->assertSame('Your code is 482916.', $payload['text']);
    }

    public function testBuildRegulatoryPayloadMapsToProviderKeys(): void
    {
        $payload = $this->createProvider()->buildRegulatoryPayload([
            'principal_entity_id' => self::ENTITY_ID,
            'content_template_id' => self::TEMPLATE_ID,
        ]);

        $this->assertSame(self::ENTITY_ID, $payload['PEID']);
        $this->assertSame(self::TEMPLATE_ID, $payload['DLTTemplateId']);
    }

    public function testBuildRegulatoryPayloadOmitsEmptyKeys(): void
    {
        $this->assertSame([], $this->createProvider()->buildRegulatoryPayload([]));
    }

    // --- Credit / test connection ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '000', 'Balance' => '1234.5']);

        $this->assertSame('1234.5', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenBalanceMissing(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '015', 'ErrorMessage' => 'Invalid API Key']);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '000', 'Balance' => '500']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500', $result->message);
        $this->assertSame('500', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorMessage' => 'unauthorised'], 401);

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

    public function testTestConnectionReturnsErrorWhenBalanceMissingFrom200(): void
    {
        $this->configure();
        $this->mockHttpGet(['ErrorCode' => '015', 'ErrorMessage' => 'Invalid API Key']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API Key', $result->message);
    }
}
