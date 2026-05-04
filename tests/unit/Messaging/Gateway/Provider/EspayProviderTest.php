<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\EspayProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class EspayProviderTest extends AbstractProviderTestCase
{
    private const SENDER_ID     = 'SGOPLUS';
    private const SIGNATURE_KEY = 'sgoplus201711aa';
    private const WA_USERNAME   = 'wa_user';
    private const WA_PASSWORD   = 'wa_secret';
    private const SEND_URL      = 'https://api.espay.id/btext/send/outgoing';
    private const RECIPIENT     = '+6281218816222';

    /** Documented test vector from https://docs.espay.id/sms-wa-gateway/hash-based-signature/ */
    private const DOCS_RQ_UUID = 'smspr-test-011';
    private const DOCS_SIGNATURE = '3ac657060474d31095e27eb49699098c81b317ca9d34e39489c9f77ba80ab758';

    protected function createProvider(): AbstractProvider
    {
        return new EspayProvider();
    }

    private function createDeterministicProvider(string $rqUuid = self::DOCS_RQ_UUID): EspayProvider
    {
        return new class($rqUuid) extends EspayProvider {
            public function __construct(private string $pinnedRqUuid) {}
            protected function generateRqUuid(): string { return $this->pinnedRqUuid; }
        };
    }

    private function configure(array $sharedOverrides = [], array $whatsappOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'espay' => [
                'shared'   => array_merge([
                    'sender_id'     => self::SENDER_ID,
                    'signature_key' => self::SIGNATURE_KEY,
                ], $sharedOverrides),
                'channels' => [
                    'whatsapp' => array_merge([
                        'basic_auth_username' => self::WA_USERNAME,
                        'basic_auth_password' => self::WA_PASSWORD,
                    ], $whatsappOverrides),
                ],
            ],
        ];
    }

    private function createSmsMessage(string $recipient = self::RECIPIENT, string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function createWhatsappMessage(
        string $recipient = self::RECIPIENT,
        string $body = 'Hi there',
        array $meta = [],
    ): Message {
        return new Message('whatsapp', $recipient, $body, null, $meta);
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

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(EspayProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('espay', $p->getId());
        $this->assertSame(['sms', 'whatsapp'], $p->getSupportedChannels());
    }

    public function testGetConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('sender_id', $schema['shared']);
        $this->assertTrue($schema['shared']['sender_id']['required']);
        $this->assertSame('string', $schema['shared']['sender_id']['type']);

        $this->assertArrayHasKey('signature_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['signature_key']['type']);
        $this->assertTrue($schema['shared']['signature_key']['required']);

        $this->assertArrayNotHasKey('sms', $schema['channels'] ?? []);

        $this->assertArrayHasKey('basic_auth_username', $schema['channels']['whatsapp']);
        $this->assertSame('string', $schema['channels']['whatsapp']['basic_auth_username']['type']);
        $this->assertTrue($schema['channels']['whatsapp']['basic_auth_username']['required']);

        $this->assertArrayHasKey('basic_auth_password', $schema['channels']['whatsapp']);
        $this->assertSame('secret', $schema['channels']['whatsapp']['basic_auth_password']['type']);
        $this->assertTrue($schema['channels']['whatsapp']['basic_auth_password']['required']);
    }

    public function testIsConfiguredForSmsWithSharedOnly(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'espay' => [
                'shared' => [
                    'sender_id'     => self::SENDER_ID,
                    'signature_key' => self::SIGNATURE_KEY,
                ],
                'channels' => [],
            ],
        ];

        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('whatsapp'));
    }

    public function testGetVariableStyleIsNamed(): void
    {
        $this->assertSame(VariableStyle::Named, $this->createProvider()->getVariableStyle());
    }

    public function testRequiresTemplateForChannel(): void
    {
        $p = $this->createProvider();
        $this->assertTrue($p->requiresTemplateForChannel('whatsapp'));
        $this->assertFalse($p->requiresTemplateForChannel('sms'));
    }

    // --- SMS path ---

    public function testSmsSendBuildsSignatureFromDocsExample(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000', 'error_desc' => 'Success']);

        $provider = $this->createDeterministicProvider();
        $provider->send($this->createSmsMessage(self::RECIPIENT, 'Hi'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertIsArray($body);
        $this->assertSame(self::DOCS_SIGNATURE, $body['signature']);
        $this->assertSame(self::DOCS_RQ_UUID, $body['rq_uuid']);
        $this->assertSame('SMS', $body['message_type']);
    }

    public function testSmsSendUsesFormEncodedBodyAndCorrectUrl(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000']);

        $this->createDeterministicProvider()->send($this->createSmsMessage(self::RECIPIENT, 'Hello'));

        $this->assertSame(self::SEND_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertIsArray($args['body']);
        $this->assertSame('SGOPLUS', $args['body']['sender_id']);
        $this->assertSame('Hello', $args['body']['message']);
        $this->assertArrayNotHasKey('headers', $args);
    }

    public function testSmsSendIncludesGeneratedRqUuid(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000']);

        $this->createProvider()->send($this->createSmsMessage());

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertArrayHasKey('rq_uuid', $body);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $body['rq_uuid'],
        );
    }

    public function testSendNormalizesRecipientStripsLeadingPlus(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000']);

        $this->createDeterministicProvider()->send($this->createSmsMessage('+6281218816222'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('6281218816222', $body['phone_number']);
    }

    public function testSendSuccessReturnsQueuedWithRqUuidAsProviderId(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000', 'error_desc' => 'Success']);

        $result = $this->createDeterministicProvider()->send($this->createSmsMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame(self::DOCS_RQ_UUID, $result->providerId);
    }

    /**
     * @dataProvider knownErrorCodesProvider
     */
    public function testSendMapsKnownErrorCodes(string $code, string $expectedFragment): void
    {
        $this->configure();
        $this->mockHttpPost([
            'error_code' => $code,
            'error_message' => 'raw message that should be ignored when mapped',
            'error_desc' => 'raw desc that should be ignored when mapped',
        ]);

        $result = $this->createDeterministicProvider()->send($this->createSmsMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString($expectedFragment, $result->error);
        $this->assertSame($code, $result->meta['espay_error_code']);
    }

    public static function knownErrorCodesProvider(): array
    {
        return [
            ['0001', 'Malformed'],
            ['0011', 'Invalid signature'],
            ['0015', 'internal error'],
            ['0041', 'Invalid recipient'],
            ['0050', 'Empty required'],
            ['0096', 'Unsupported message type'],
            ['0401', 'declined'],
            ['0601', 'IP not whitelisted'],
            ['800',  'Insufficient balance'],
        ];
    }

    public function testSendFallsBackToProviderErrorMessage(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'error_code' => '9999',
            'error_message' => 'Some unexpected reason',
        ]);

        $result = $this->createDeterministicProvider()->send($this->createSmsMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Some unexpected reason', $result->error);
    }

    public function testSendFallsBackToProviderErrorDesc(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'error_code' => '9998',
            'error_desc' => 'Description-only fallback',
        ]);

        $result = $this->createDeterministicProvider()->send($this->createSmsMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Description-only fallback', $result->error);
    }

    public function testSendHandlesNon2xxResponse(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0015'], 500);

        $result = $this->createDeterministicProvider()->send($this->createSmsMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
        $this->assertSame(500, $result->meta['espay_http_code']);
    }

    public function testSendHandlesEmptyResponseBody(): void
    {
        $this->configure();
        $this->mockHttpPostRaw('', 200);

        $result = $this->createDeterministicProvider()->send($this->createSmsMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('unexpected', $result->error);
    }

    public function testSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createSmsMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testTestConnectionReturnsNotSupportedByDefault(): void
    {
        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not supported', $result->message);
    }

    // --- WhatsApp path ---

    public function testWhatsappSendBuildsBasicAuthHeader(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000']);

        $this->createDeterministicProvider()->send($this->createWhatsappMessage(
            self::RECIPIENT,
            'Hi',
            ['template_mode' => true, 'provider_template_id' => 'tpl_abc'],
        ));

        $headers = $GLOBALS['_test_wp_remote_post_last_args']['headers'] ?? [];
        $this->assertSame(
            'Basic ' . base64_encode(self::WA_USERNAME . ':' . self::WA_PASSWORD),
            $headers['Authorization'],
        );
    }

    public function testWhatsappSendUsesWaMessageType(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000']);

        $this->createDeterministicProvider()->send($this->createWhatsappMessage(
            self::RECIPIENT,
            'Hi',
            ['template_mode' => true, 'provider_template_id' => 'tpl_abc'],
        ));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('WA', $body['message_type']);

        $expectedSignature = hash(
            'sha256',
            strtoupper(sprintf('#%s#%s#%s#%s#', self::SENDER_ID, self::DOCS_RQ_UUID, 'WA', '6281218816222'))
                . self::SIGNATURE_KEY . '#',
        );
        $this->assertSame($expectedSignature, $body['signature']);
        $this->assertNotSame(self::DOCS_SIGNATURE, $body['signature'], 'WA signature must differ from SMS docs vector');
    }

    public function testWhatsappSendIncludesTemplateIdFromDirectMode(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000']);

        $this->createDeterministicProvider()->send($this->createWhatsappMessage(
            self::RECIPIENT,
            'Hi {{name}}',
            [
                'template_mode'        => true,
                'provider_template_id' => 'tpl_abc',
                'template_variables'   => ['name' => 'Alice'],
            ],
        ));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('tpl_abc', $body['template_id']);
        $this->assertSame('Hi Alice', $body['message']);
    }

    public function testWhatsappSendResolvesTemplateIdFromCatalog(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000']);

        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'tpl_otp_v1',
            gatewayId: 'espay',
            language: 'id',
            variableMap: ['otp_code' => 'otp_code'],
        );

        $catalog = $this->createCatalogStub($mapping);

        $provider = $this->createDeterministicProvider();
        $provider->setCatalogManager($catalog);

        $provider->send($this->createWhatsappMessage(
            self::RECIPIENT,
            'Your OTP is {{otp_code}}.',
            [
                'template_type'      => 'otp',
                'template_variables' => ['otp_code' => '482916'],
            ],
        ));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('tpl_otp_v1', $body['template_id']);
        $this->assertSame('Your OTP is 482916.', $body['message']);
    }

    public function testWhatsappSendFailsWithoutTemplate(): void
    {
        $this->configure();
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );

        $result = $this->createDeterministicProvider()->send($this->createWhatsappMessage(
            self::RECIPIENT,
            'Hi',
            [],
        ));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('template', $result->error);
        $this->assertArrayNotHasKey('_test_wp_remote_post_last_url', $GLOBALS);
    }

    public function testWhatsappSendForwardsBroadcastFlag(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000']);

        $this->createDeterministicProvider()->send($this->createWhatsappMessage(
            self::RECIPIENT,
            'Hi',
            [
                'template_mode'        => true,
                'provider_template_id' => 'tpl_abc',
                'broadcast'            => 'Y',
            ],
        ));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('Y', $body['broadcast']);
    }

    public function testWhatsappSendOmitsBroadcastWhenNotProvided(): void
    {
        $this->configure();
        $this->mockHttpPost(['error_code' => '0000']);

        $this->createDeterministicProvider()->send($this->createWhatsappMessage(
            self::RECIPIENT,
            'Hi',
            ['template_mode' => true, 'provider_template_id' => 'tpl_abc'],
        ));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertArrayNotHasKey('broadcast', $body);
    }

    public function testWhatsappSendFailsWhenBasicAuthMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'espay' => [
                'shared' => [
                    'sender_id'     => self::SENDER_ID,
                    'signature_key' => self::SIGNATURE_KEY,
                ],
                'channels' => [
                    'whatsapp' => [
                        'basic_auth_username' => '',
                        'basic_auth_password' => '',
                    ],
                ],
            ],
        ];

        $result = $this->createDeterministicProvider()->send($this->createWhatsappMessage(
            self::RECIPIENT,
            'Hi',
            ['template_mode' => true, 'provider_template_id' => 'tpl_abc'],
        ));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Basic auth', $result->error);
    }

    private function createCatalogStub(TemplateMapping $mapping): TemplateCatalogManager
    {
        return new class($mapping) extends TemplateCatalogManager {
            public function __construct(private TemplateMapping $mapping)
            {
                // Skip parent constructor — we don't need a real GatewayRegistry for stubbing.
            }

            public function resolveMapping(string $templateType, string $gatewayId): ?TemplateMapping
            {
                if ($templateType === $this->mapping->templateType && $gatewayId === $this->mapping->gatewayId) {
                    return $this->mapping;
                }
                return null;
            }
        };
    }
}
