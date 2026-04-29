<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmscProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmscProviderTest extends AbstractProviderTestCase
{
    private const LOGIN     = 'wsms-test-login';
    private const PASSWORD  = 'wsms-test-password';
    private const SMS_FROM  = 'WSms';
    private const VIBER_FROM = 'WsmsViber';
    private const WA_BOT    = '79991234567';
    private const RECIPIENT = '+380501234567';

    protected function createProvider(): AbstractProvider
    {
        return new SmscProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsc' => [
                'shared' => array_merge([
                    'host'     => 'smsc.ua',
                    'login'    => self::LOGIN,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms'      => ['from' => self::SMS_FROM],
                    'viber'    => ['from' => self::VIBER_FROM],
                    'whatsapp' => ['bot_number' => self::WA_BOT],
                    'telegram' => ['bot_handle' => ''],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $body = 'Hello', array $meta = [], string $recipient = self::RECIPIENT): Message
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

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmscProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smsc', $p->getId());
        $this->assertSame(['sms', 'viber', 'whatsapp', 'telegram'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('select', $schema['shared']['host']['type']);
        $this->assertSame('smsc.ua', $schema['shared']['host']['default']);
        $hostValues = array_column($schema['shared']['host']['options'], 'value');
        $this->assertSame(['smsc.ua', 'smsc.ru', 'smsc.kz', 'smsc.tj', 'smscentre.com'], $hostValues);

        $this->assertTrue($schema['shared']['login']['required']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $this->assertTrue($schema['channels']['sms']['from']['dynamic']);
        $this->assertTrue($schema['channels']['viber']['from']['required']);
        $this->assertTrue($schema['channels']['whatsapp']['bot_number']['required']);
        $this->assertFalse($schema['channels']['telegram']['bot_handle']['required'] ?? false);
    }

    // --- Send: SMS ---

    public function testSendSmsHitsCorrectEndpointAndBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 12345, 'cnt' => 1]);

        $result = $this->createProvider()->send($this->createMessage('sms', 'Hi there'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('12345', $result->providerId);

        $this->assertSame(
            'https://smsc.ua/sys/send.php',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(self::LOGIN, $body['login']);
        $this->assertSame(self::PASSWORD, $body['psw']);
        $this->assertSame(3, $body['fmt']);
        $this->assertSame('utf-8', $body['charset']);
        $this->assertSame('380501234567', $body['phones']);
        $this->assertSame('Hi there', $body['mes']);
        $this->assertSame(self::SMS_FROM, $body['sender']);
        $this->assertArrayNotHasKey('viber', $body);
        $this->assertArrayNotHasKey('bot', $body);
        $this->assertArrayNotHasKey('tg', $body);
    }

    public function testSendSmsUsesAlternateHostWhenConfigured(): void
    {
        $this->configure(['host' => 'smsc.ru']);
        $this->mockHttpPost(['id' => 1, 'cnt' => 1]);

        $this->createProvider()->send($this->createMessage('sms', 'Hi'));

        $this->assertSame(
            'https://smsc.ru/sys/send.php',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testSendSmsFlashMetaPopulatesFlash1(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 2, 'cnt' => 1]);

        $this->createProvider()->send($this->createMessage('sms', 'Hi', ['flash' => true]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(1, $body['flash']);
    }

    public function testSendSmsMediaUrlMetaPopulatesFileurl(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 3, 'cnt' => 1]);

        $this->createProvider()->send($this->createMessage('sms', 'See:', [
            'media_urls' => ['https://cdn.example.com/img.png', 'https://cdn.example.com/two.png'],
        ]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('https://cdn.example.com/img.png', $body['fileurl']);
    }

    public function testSendSmsHandlesMultipleRecipients(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 4, 'cnt' => 2]);

        $msg = new Message('sms', '+380501234567,+380671112233', 'Hi', null, []);
        $this->createProvider()->send($msg);

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('380501234567,380671112233', $body['phones']);
    }

    // --- Send: Viber / WhatsApp / Telegram ---

    public function testSendViberAddsViberFlag(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 'viber-1', 'cnt' => 1]);

        $this->createProvider()->send($this->createMessage('viber', 'Hi Viber'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(1, $body['viber']);
        $this->assertSame(self::VIBER_FROM, $body['sender']);
    }

    public function testSendViberFailsWhenSenderMissing(): void
    {
        $this->configure(channelOverrides: ['viber' => ['from' => '']]);

        $result = $this->createProvider()->send($this->createMessage('viber'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Viber', $result->error);
    }

    public function testSendWhatsAppPrependsWaPrefixToBotNumber(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 'wa-1', 'cnt' => 1]);

        $this->createProvider()->send($this->createMessage('whatsapp', 'Hi WA'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('wa:' . self::WA_BOT, $body['bot']);
    }

    public function testSendWhatsAppFailsWhenBotMissing(): void
    {
        $this->configure(channelOverrides: ['whatsapp' => ['bot_number' => '']]);

        $result = $this->createProvider()->send($this->createMessage('whatsapp'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('WhatsApp', $result->error);
    }

    public function testSendTelegramUsesAccountBotByDefault(): void
    {
        $this->configure();
        $this->mockHttpPost(['id' => 'tg-1', 'cnt' => 1]);

        $this->createProvider()->send($this->createMessage('telegram', 'Hi TG'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(1, $body['tg']);
        $this->assertArrayNotHasKey('bot', $body);
    }

    public function testSendTelegramUsesCustomBotHandleWhenProvided(): void
    {
        $this->configure(channelOverrides: ['telegram' => ['bot_handle' => '@my_bot']]);
        $this->mockHttpPost(['id' => 'tg-2', 'cnt' => 1]);

        $this->createProvider()->send($this->createMessage('telegram'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('@my_bot', $body['bot']);
        $this->assertArrayNotHasKey('tg', $body);
    }

    public function testSendTelegramAddsAtSignToBareHandle(): void
    {
        $this->configure(channelOverrides: ['telegram' => ['bot_handle' => 'my_bot']]);
        $this->mockHttpPost(['id' => 'tg-3', 'cnt' => 1]);

        $this->createProvider()->send($this->createMessage('telegram'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('@my_bot', $body['bot']);
    }

    // --- Send: error handling ---

    public function testSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credentials', $result->error);
    }

    public function testSendMapsErrorCodeToFailureWithMeta(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'cannot deliver', 'error_code' => 8]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('cannot deliver', $result->error);
        $this->assertSame('8', $result->meta['smsc_error_code']);
    }

    public function testSendMarksRateLimitErrorsRetryable(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'too many requests', 'error_code' => 9]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    public function testSendMaps401ToInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost([], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditFormatsBalanceAndCurrency(): void
    {
        $this->configure();
        $this->mockHttpPost(['balance' => '12.345', 'currency' => 'RUB']);

        $credit = $this->createProvider()->getCredit();

        $this->assertSame('12.35 RUB', $credit);
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'auth', 'error_code' => 2]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- testConnection ---

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost(['balance' => '5.00', 'currency' => 'USD']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5.00', $result->message);
    }

    public function testTestConnectionMapsErrorCode2ToAuthFailure(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'invalid login', 'error_code' => 2]);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionMapsErrorCode4ToIpBlock(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'ip blocked', 'error_code' => 4]);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('IP', $result->message);
    }

    public function testTestConnectionMissingCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback ---

    public function testStatusCallbackUrlIncludesToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();

        $this->assertStringContainsString('callbacks/smsc/status', $url);
        $this->assertStringContainsString('token=', $url);
    }

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $expected = hash_hmac('sha256', 'smsc-callback', self::PASSWORD);

        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/status');
        $request->set_param('token', $expected);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/status');
        $request->set_param('token', 'deadbeef');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenPasswordMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/status');
        $request->set_param('token', 'whatever');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsAllStatuses(): void
    {
        $cases = [
            // raw status => [normalized, permanent, unsubscribe]
            '-1' => ['sent',      false, false],
            '0'  => ['sent',      false, false],
            '1'  => ['delivered', false, false],
            '2'  => ['delivered', false, false],
            '3'  => ['failed',    false, false],
            '20' => ['failed',    true,  false],
            '22' => ['failed',    true,  false],
            '23' => ['failed',    true,  true],
            '24' => ['failed',    false, false],
            '25' => ['failed',    true,  false],
        ];

        $p = $this->createProvider();

        foreach ($cases as $raw => [$expectedStatus, $expectedPermanent, $expectedUnsub]) {
            $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/status');
            $request->set_param('id', 'msg-' . $raw);
            $request->set_param('status', $raw);

            $updates = $p->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame($expectedStatus, $updates[0]->status, "wrong status mapping for {$raw}");
            $this->assertSame($expectedPermanent, $updates[0]->permanent, "wrong permanent for {$raw}");
            $this->assertSame($expectedUnsub, $updates[0]->unsubscribe, "wrong unsubscribe for {$raw}");
        }
    }

    public function testParseStatusCallbackPropagatesErrorCode(): void
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/status');
        $request->set_param('id', 'msg-7');
        $request->set_param('status', '22');
        $request->set_param('err', '7');

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('failed', $updates[0]->status);
        $this->assertTrue($updates[0]->permanent);
        $this->assertSame('7', $updates[0]->errorCode);
        $this->assertStringContainsString('22', $updates[0]->errorMessage);
    }

    public function testParseStatusCallbackEmptyWhenIdMissing(): void
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/status');
        $request->set_param('status', '1');

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testInboundCallbackUrlIncludesToken(): void
    {
        $this->configure();
        $url = $this->createProvider()->getInboundCallbackUrl();

        $this->assertStringContainsString('callbacks/smsc/inbound', $url);
        $this->assertStringContainsString('token=', $url);
    }

    public function testValidateInboundCallbackTokenCheck(): void
    {
        $this->configure();
        $expected = hash_hmac('sha256', 'smsc-callback', self::PASSWORD);

        $good = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/inbound');
        $good->set_param('token', $expected);
        $bad = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/inbound');
        $bad->set_param('token', 'wrong');

        $p = $this->createProvider();
        $this->assertTrue($p->validateInboundCallback($good));
        $this->assertFalse($p->validateInboundCallback($bad));
    }

    public function testParseInboundCallbackBuildsInboundMessage(): void
    {
        $this->configure();

        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/inbound');
        $request->set_param('id', 'mo-42');
        $request->set_param('phone', '380501234567');
        $request->set_param('mes', 'STOP');

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('380501234567', $messages[0]->from);
        $this->assertSame('STOP', $messages[0]->body);
        $this->assertSame('mo-42', $messages[0]->providerId);
        $this->assertSame(self::SMS_FROM, $messages[0]->to);
    }

    public function testParseInboundCallbackEmptyWhenPhoneMissing(): void
    {
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/smsc/inbound');
        $request->set_param('mes', 'STOP');

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorTrueOnCode8(): void
    {
        $result = DeliveryResult::failed('cannot deliver', meta: ['smsc_error_code' => '8']);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseOnOtherCodes(): void
    {
        $result = DeliveryResult::failed('invalid number', meta: ['smsc_error_code' => '7']);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    // --- Dynamic options (sender list) ---

    public function testGetConfigOptionsFetchesSenderList(): void
    {
        $this->configure();
        $this->mockHttpPost([
            ['sender' => 'WSms'],
            ['sender' => 'WSmsAlt'],
        ]);

        $options = $this->createProvider()->getConfigOptions(
            'from',
            'sms',
            $GLOBALS['_test_options']['wsms_gateway_configs']['smsc'],
        );

        $this->assertSame(
            [['value' => 'WSms', 'label' => 'WSms'], ['value' => 'WSmsAlt', 'label' => 'WSmsAlt']],
            $options,
        );

        $this->assertSame(
            'https://smsc.ua/sys/senders.php',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(1, $body['get']);
        $this->assertSame(self::LOGIN, $body['login']);
    }

    public function testGetConfigOptionsReturnsEmptyForUnsupportedField(): void
    {
        $this->configure();

        $options = $this->createProvider()->getConfigOptions(
            'from',
            'whatsapp',
            $GLOBALS['_test_options']['wsms_gateway_configs']['smsc'],
        );

        $this->assertSame([], $options);
    }

    public function testGetConfigOptionsThrowsOnAuthError(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'invalid login', 'error_code' => 2]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid/');

        $this->createProvider()->getConfigOptions(
            'from',
            'sms',
            $GLOBALS['_test_options']['wsms_gateway_configs']['smsc'],
        );
    }

    // --- Templates ---

    public function testVariableStyleIsPositional(): void
    {
        $this->assertSame(VariableStyle::Positional, $this->createProvider()->getVariableStyle());
    }

    public function testRequiresTemplateForChannelIsAlwaysFalse(): void
    {
        $p = $this->createProvider();
        foreach (['sms', 'viber', 'whatsapp', 'telegram'] as $channel) {
            $this->assertFalse($p->requiresTemplateForChannel($channel));
        }
    }

    public function testBuildTemplatePayloadSubstitutesPositionalPlaceholders(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'tpl-otp',
            gatewayId: 'smsc',
            language: '',
            variableMap: ['otp_code' => '1', 'site_name' => '2'],
            providerTemplateBody: 'Code %1 for %2',
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, ['1' => '482916', '2' => 'WSms']);

        $this->assertSame(['mes' => 'Code 482916 for WSms'], $payload);
    }

    public function testFetchTemplatesReturnsProviderTemplates(): void
    {
        $this->configure();
        $this->mockHttpPost([
            ['id' => 1, 'name' => 'OTP', 'msg' => 'Your code: %1', 'format' => 'sms'],
            ['id' => 2, 'name' => 'Welcome', 'msg' => 'Hi %1, welcome %2', 'format' => 'sms'],
        ]);

        $templates = $this->createProvider()->fetchTemplates();

        $this->assertCount(2, $templates);
        $this->assertSame('1', $templates[0]->id);
        $this->assertSame('OTP', $templates[0]->name);
        $this->assertSame(1, $templates[0]->variableCount);
        $this->assertSame(2, $templates[1]->variableCount);

        $this->assertSame('https://smsc.ua/sys/templates.php', $GLOBALS['_test_wp_remote_post_last_url']);
    }

    public function testSendDispatchesCatalogTemplateWhenMappingResolves(): void
    {
        // Inject a fake catalog manager that returns a mapping with positional %1 vars.
        $provider = $this->createProvider();
        assert($provider instanceof SmscProvider);

        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'tpl-otp',
            gatewayId: 'smsc',
            language: '',
            variableMap: ['otp_code' => '1'],
            providerTemplateBody: 'Your code is %1',
        );

        $catalog = new class($mapping) extends \WSms\Messaging\Catalog\TemplateCatalogManager {
            public function __construct(private readonly TemplateMapping $stub) {}
            public function resolveMapping(string $templateType, string $gatewayId): ?TemplateMapping
            {
                return $this->stub;
            }
        };

        $provider->setCatalogManager($catalog);

        $this->configure();
        $this->mockHttpPost(['id' => 99, 'cnt' => 1]);

        $provider->send($this->createMessage('sms', 'fallback', [
            'template_type'      => 'otp',
            'template_variables' => ['otp_code' => '482916'],
        ]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('Your code is 482916', $body['mes']);
    }
}
