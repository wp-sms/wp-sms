<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\Fast2SmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class Fast2SmsProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'fast2sms-test-api-key';
    private const SENDER_ID = 'WSMSAB';
    private const ENTITY_ID = '1101000000000123456';
    private const PHONE_NUMBER_ID = '15551234567';
    private const CALLBACK_TOKEN = 'webhook-secret-xyz';

    protected function createProvider(): AbstractProvider
    {
        return new Fast2SmsProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
        $GLOBALS['_test_transients'] = [];
        parent::tearDown();
    }

    private function configure(array $extraShared = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'fast2sms' => [
                'shared' => array_merge([
                    'api_key' => self::API_KEY,
                ], $extraShared),
                'channels' => array_merge([
                    'sms' => [
                        'sender_id' => self::SENDER_ID,
                        'entity_id' => self::ENTITY_ID,
                    ],
                    'whatsapp' => [
                        'phone_number_id' => self::PHONE_NUMBER_ID,
                    ],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '9999988888', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200, ?callable $capture = null): void
    {
        $payload = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];

        if ($capture) {
            $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($payload, $capture) {
                $capture($url, $args);
                return $payload;
            };
        } else {
            $GLOBALS['_test_wp_remote_get'] = $payload;
        }
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(Fast2SmsProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('fast2sms', $p->getId());
        $this->assertSame(['sms', 'whatsapp'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('callback_token', $schema['shared']);
        $this->assertFalse($schema['shared']['callback_token']['required'] ?? true);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertArrayHasKey('entity_id', $schema['channels']['sms']);
        $this->assertArrayHasKey('phone_number_id', $schema['channels']['whatsapp']);
        $this->assertTrue($schema['channels']['whatsapp']['phone_number_id']['required']);
    }

    public function testIsConfiguredForChannelOnlyWhatsAppWhenSmsHasNoRequired(): void
    {
        // SMS has no required fields, so any configured shared.api_key is enough for SMS.
        // WhatsApp requires phone_number_id.
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'fast2sms' => [
                'shared' => ['api_key' => self::API_KEY],
                'channels' => ['whatsapp' => []],
            ],
        ];

        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('sms'));
        $this->assertFalse($p->isConfiguredForChannel('whatsapp'));
    }

    // --- SMS: Quick route ---

    public function testQuickSmsSendPostsToBulkV2WithAuthHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['return' => true, 'request_id' => 'req-quick-1', 'message' => ['Sent successfully']]);

        $result = $this->createProvider()->send($this->createMessage('sms', '9999988888', 'Hi there'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('req-quick-1', $result->providerId);

        $this->assertSame('https://www.fast2sms.com/dev/bulkV2', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['authorization']);

        $this->assertSame('q', $args['body']['route']);
        $this->assertSame('9999988888', $args['body']['numbers']);
        $this->assertSame('Hi there', $args['body']['message']);
        $this->assertArrayNotHasKey('template_id', $args['body']);
        $this->assertArrayNotHasKey('sender_id', $args['body']);
    }

    public function testQuickSmsStripsIndianCountryCodeVariants(): void
    {
        $this->configure();

        foreach (['+919999988888', '0091 9999988888', '91-9999988888', '9999988888'] as $input) {
            $this->mockHttpPost(['return' => true, 'request_id' => 'req-x']);
            $this->createProvider()->send($this->createMessage('sms', $input, 'Hi'));
            $this->assertSame('9999988888', $GLOBALS['_test_wp_remote_post_last_args']['body']['numbers'], "wrong strip for {$input}");
        }
    }

    public function testFlashMetaSetsFlashFlag(): void
    {
        $this->configure();
        $this->mockHttpPost(['return' => true, 'request_id' => 'req-f']);

        $this->createProvider()->send($this->createMessage('sms', '9999988888', 'Hi', ['flash' => true]));

        $this->assertSame('1', $GLOBALS['_test_wp_remote_post_last_args']['body']['flash']);
    }

    public function testSmsSendReturnsFailedWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSmsSendReturnsFailedOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['message' => ['Invalid API Key']], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSmsSendBubblesUpProviderErrorMessage(): void
    {
        $this->configure();
        $this->mockHttpPost(['return' => false, 'message' => ['Insufficient wallet balance']], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient wallet balance', $result->error);
    }

    // --- SMS: DLT route ---

    public function testDltSmsSendViaTemplateMode(): void
    {
        $this->configure();
        $this->mockHttpPost(['return' => true, 'request_id' => 'req-dlt-1']);

        $this->createProvider()->send($this->createMessage('sms', '+919999988888', '', [
            'template_mode'        => true,
            'provider_template_id' => '1707171234567890123',
            'template_variables'   => ['1' => 'Alice', '2' => '482916'],
        ]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('dlt', $body['route']);
        $this->assertSame('1707171234567890123', $body['message']);
        $this->assertSame('Alice|482916', $body['variables_values']);
        $this->assertSame('9999988888', $body['numbers']);
        $this->assertSame(self::SENDER_ID, $body['sender_id']);
        $this->assertSame(self::ENTITY_ID, $body['entity_id']);
    }

    public function testDltSmsSendViaTemplateCatalog(): void
    {
        $this->configure();
        $this->mockHttpPost(['return' => true, 'request_id' => 'req-dlt-cat']);

        $catalog = $this->createMock(TemplateCatalogManager::class);
        $catalog->method('resolveMapping')->with('otp', 'fast2sms')->willReturn(new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: '1707170000000099999',
            gatewayId: 'fast2sms',
            language: 'en',
            variableMap: ['otp_code' => '1'],
        ));

        $provider = new Fast2SmsProvider();
        $provider->setCatalogManager($catalog);

        $provider->send($this->createMessage('sms', '9999988888', 'fallback body', [
            'template_type'      => 'otp',
            'template_variables' => ['otp_code' => '482916'],
        ]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('dlt', $body['route']);
        $this->assertSame('1707170000000099999', $body['message']);
        $this->assertSame('482916', $body['variables_values']);
        $this->assertSame(self::ENTITY_ID, $body['entity_id']);
    }

    // --- WhatsApp ---

    public function testWhatsAppTextSendPostsToSessionEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost(['return' => true, 'request_id' => 'wa-text-1']);

        $result = $this->createProvider()->send($this->createMessage('whatsapp', '919999988888', 'Hi WA'));

        $this->assertTrue($result->success);
        $this->assertSame('wa-text-1', $result->providerId);

        $this->assertSame('https://www.fast2sms.com/dev/whatsapp-session', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['authorization']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('919999988888', $body['to']);
        $this->assertSame(self::PHONE_NUMBER_ID, $body['phone_number_id']);
        $this->assertSame('text', $body['type']);
        $this->assertSame(['body' => 'Hi WA'], $body['text']);
    }

    public function testWhatsAppTemplateSendUsesGetWhatsappEndpoint(): void
    {
        $this->configure();
        $captured = ['url' => null, 'args' => null];
        $this->mockHttpGet(
            ['return' => true, 'request_id' => 'wa-tpl-1'],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url']  = $url;
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage('whatsapp', '919999988888', '', [
            'template_mode'        => true,
            'provider_template_id' => 'tpl_otp',
            'template_variables'   => ['1' => 'Alice', '2' => '482916'],
        ]));

        $this->assertNotNull($captured['url']);
        $parsed = parse_url($captured['url']);
        $this->assertSame('/dev/whatsapp', $parsed['path']);
        parse_str($parsed['query'], $query);
        $this->assertSame(self::API_KEY, $query['authorization']);
        $this->assertSame('tpl_otp', $query['message_id']);
        $this->assertSame(self::PHONE_NUMBER_ID, $query['phone_number_id']);
        $this->assertSame('919999988888', $query['numbers']);
        $this->assertSame('Alice|482916', $query['variables_values']);
    }

    public function testWhatsAppMediaSendUsesSessionImagePayload(): void
    {
        $this->configure();
        $this->mockHttpPost(['return' => true, 'request_id' => 'wa-img-1']);

        $this->createProvider()->send($this->createMessage('whatsapp', '919999988888', 'Caption', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('image', $body['type']);
        $this->assertSame('https://example.com/photo.jpg', $body['image']['link']);
        $this->assertSame('Caption', $body['image']['caption']);
    }

    public function testWhatsAppSendFailsWithoutPhoneNumberId(): void
    {
        $this->configure(channelOverrides: ['whatsapp' => []]);

        $result = $this->createProvider()->send($this->createMessage('whatsapp', '919999988888', 'Hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Phone Number ID', $result->error);
    }

    // --- Credit / Test Connection ---

    public function testGetCreditReturnsWalletString(): void
    {
        $this->configure();
        $this->mockHttpGet(['return' => true, 'wallet' => '493.20']);

        $this->assertSame('493.20', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenWalletAbsent(): void
    {
        $this->configure();
        $this->mockHttpGet(['return' => false, 'message' => ['Bad key']], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $this->mockHttpGet(['return' => true, 'wallet' => '493.20']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('493.20', $result->message);
        $this->assertSame('493.20', $result->details['balance']);
    }

    public function testTestConnectionRejectsInvalidKey(): void
    {
        $this->configure();
        $this->mockHttpGet(['message' => ['Invalid key']], 401);

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

    // --- SupportsTemplates ---

    public function testRequiresTemplateForChannelReturnsFalse(): void
    {
        $p = $this->createProvider();
        $this->assertFalse($p->requiresTemplateForChannel('sms'));
        $this->assertFalse($p->requiresTemplateForChannel('whatsapp'));
    }

    public function testVariableStyleIsPositional(): void
    {
        $this->assertSame(VariableStyle::Positional, $this->createProvider()->getVariableStyle());
    }

    public function testBuildTemplatePayloadJoinsVariablesWithPipeInPositionalOrder(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'welcome',
            providerTemplateId: 'tpl_welcome',
            gatewayId: 'fast2sms',
            language: 'en',
            variableMap: [],
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, [
            '2' => 'Bob', '1' => 'Alice', '3' => 'Acme',
        ]);

        $this->assertSame('tpl_welcome', $payload['template_id']);
        $this->assertSame('Alice|Bob|Acme', $payload['variables_values']);
    }

    // --- SupportsTemplateFetch ---

    public function testFetchTemplatesParsesDltManagerResponse(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'return' => true,
            'data'   => [
                [
                    'template_id'      => '1707170000000011111',
                    'template_name'    => 'OTP English',
                    'template_message' => 'Your OTP is {#var#}.',
                    'language'         => 'english',
                    'status'           => 'approved',
                    'sender_id'        => self::SENDER_ID,
                ],
                [
                    'template_id'      => '1707170000000022222',
                    'template_name'    => 'Welcome',
                    'template_message' => 'Hi {#var#}, welcome to {#var#}.',
                    'status'           => 'pending',
                ],
            ],
        ]);

        $templates = $this->createProvider()->fetchTemplates();

        $this->assertCount(2, $templates);

        $this->assertSame('1707170000000011111', $templates[0]->id);
        $this->assertSame('OTP English', $templates[0]->name);
        $this->assertSame(1, $templates[0]->variableCount);
        $this->assertSame(TemplateStatus::Approved, $templates[0]->status);

        $this->assertSame(2, $templates[1]->variableCount);
        $this->assertSame(TemplateStatus::Pending, $templates[1]->status);
    }

    public function testFetchTemplatesReturnsEmptyOnFailure(): void
    {
        $this->configure();
        $this->mockHttpGet(['message' => ['boom']], 401);

        $this->assertSame([], $this->createProvider()->fetchTemplates());
    }

    // --- Status callback ---

    public function testValidateStatusCallbackRejectsWhenNoTokenConfigured(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'whatever']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure(['callback_token' => self::CALLBACK_TOKEN]);
        $request = $this->buildRequest('POST', '/x', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure(['callback_token' => self::CALLBACK_TOKEN]);
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure(['callback_token' => self::CALLBACK_TOKEN]);
        $request = $this->buildRequest('POST', '/x', ['token' => self::CALLBACK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsDelivered(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'request_id' => 'req-1',
            'status'     => 'DELIVERED',
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('req-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testParseStatusCallbackMapsFailedAsPermanent(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'request_id' => 'req-2',
            'status'     => 'FAILED',
            'error_code' => 'DND_BLOCK',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('DND_BLOCK', $update->errorCode);
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $this->assertSame([], $this->createProvider()->parseStatusCallback($this->buildRequest('POST', '/x', [])));
    }

    // --- SupportsRegulatoryIds ---

    public function testBuildRegulatoryPayloadShapesEntityId(): void
    {
        $payload = $this->createProvider()->buildRegulatoryPayload([
            'principal_entity_id' => self::ENTITY_ID,
        ]);

        $this->assertSame(['entity_id' => self::ENTITY_ID], $payload);
    }

    public function testBuildRegulatoryPayloadEmptyWhenNoEntity(): void
    {
        $this->assertSame([], $this->createProvider()->buildRegulatoryPayload([]));
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = []): \WP_REST_Request
    {
        return new class($method, $route, $params, $headers) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, array $params, array $headers) {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) $this->set_param($k, $v);
                foreach ($headers as $k => $v) $this->set_header($k, $v);
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }
}
