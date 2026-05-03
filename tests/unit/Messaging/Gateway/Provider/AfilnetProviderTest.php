<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\AfilnetProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class AfilnetProviderTest extends AbstractProviderTestCase
{
    private const USERNAME    = 'me@example.com';
    private const PASSWORD    = 'super-secret';
    private const SMS_FROM    = 'WSMS';
    private const PLATFORM_ID = 'wa-platform-1';
    private const VOICE_LANG  = 'EN';
    private const ENDPOINT    = 'https://www.afilnet.com/api/http/';

    protected function createProvider(): AbstractProvider
    {
        return new AfilnetProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $defaultChannels = [
            'sms'      => ['from' => self::SMS_FROM],
            'email'    => ['subject_prefix' => 'WSMS notice'],
            'voice'    => ['language' => self::VOICE_LANG],
            'whatsapp' => ['platform_id' => self::PLATFORM_ID],
        ];
        foreach ($channelOverrides as $channel => $overrides) {
            $defaultChannels[$channel] = array_merge($defaultChannels[$channel] ?? [], $overrides);
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'afilnet' => [
                'shared'   => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $sharedOverrides),
                'channels' => $defaultChannels,
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+34611223344', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockSuccess(string $result = 'msg-001', int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode(['status' => 'SUCCESS', 'result' => $result]),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockRawPostResponse(array $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($body),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(AfilnetProvider::TESTED);
    }

    public function testGetSupportedChannels(): void
    {
        $this->assertSame(['sms', 'email', 'voice', 'whatsapp'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetIdAndConfigSchema(): void
    {
        $provider = $this->createProvider();
        $this->assertSame('afilnet', $provider->getId());

        $schema = $provider->getConfigSchema();
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $this->assertTrue($schema['channels']['sms']['from']['required']);
        $this->assertFalse($schema['channels']['email']['subject_prefix']['required']);
        $this->assertSame('select', $schema['channels']['voice']['language']['type']);
        $this->assertTrue($schema['channels']['whatsapp']['platform_id']['required']);
    }

    public function testIsConfiguredRequiresUsernameAndPassword(): void
    {
        // Missing password
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'afilnet' => ['shared' => ['username' => self::USERNAME]],
        ];
        $this->assertFalse($this->createProvider()->isConfigured());

        // Both present, plus a configured channel
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send: SMS ---

    public function testSendSmsSuccess(): void
    {
        $this->configure();
        $this->mockSuccess('sms-id-1');

        $result = $this->createProvider()->send($this->createMessage('sms', '+34611223344', 'Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('sms-id-1', $result->providerId);

        $this->assertSame(self::ENDPOINT, $GLOBALS['_test_wp_remote_post_last_url']);
        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $form);
        $this->assertSame(self::USERNAME, $form['user']);
        $this->assertSame(self::PASSWORD, $form['password']);
        $this->assertSame('sms', $form['class']);
        $this->assertSame('sendsms', $form['method']);
        $this->assertSame(self::SMS_FROM, $form['from']);
        $this->assertSame('+34611223344', $form['to']);
        $this->assertSame('Hi', $form['sms']);
    }

    public function testSendSmsErrorReturnsFailed(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['status' => 'ERROR', 'error' => 'NO_CREDITS']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('NO_CREDITS', $result->error);
        $this->assertSame('NO_CREDITS', $result->meta['afilnet_error']);
    }

    public function testSendSmsFailsWhenSenderMissing(): void
    {
        $this->configure([], ['sms' => ['from' => '']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('SMS sender not configured', $result->error);
    }

    // --- Send: Email ---

    public function testSendEmailUsesEmailClassAndSubject(): void
    {
        $this->configure();
        $this->mockSuccess('email-id-1');

        $this->createProvider()->send(
            $this->createMessage('email', 'recipient@example.com', '<p>Hi</p>', ['subject' => 'Welcome'])
        );

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('email', $form['class']);
        $this->assertSame('sendemail', $form['method']);
        $this->assertSame('recipient@example.com', $form['to']);
        $this->assertSame('Welcome', $form['subject']);
        $this->assertSame('<p>Hi</p>', $form['email']);
    }

    public function testSendEmailFallsBackToSubjectPrefix(): void
    {
        $this->configure();
        $this->mockSuccess('email-id-2');

        $this->createProvider()->send($this->createMessage('email', 'recipient@example.com', 'Body'));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('WSMS notice', $form['subject']);
    }

    // --- Send: Voice ---

    public function testSendVoiceUsesVoiceClassAndLanguage(): void
    {
        $this->configure([], ['voice' => ['language' => 'ES']]);
        $this->mockSuccess('voice-id-1');

        $this->createProvider()->send($this->createMessage('voice', '+34611223344', 'Hello caller'));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('voice', $form['class']);
        $this->assertSame('sendvoice', $form['method']);
        $this->assertSame('+34611223344', $form['to']);
        $this->assertSame('Hello caller', $form['message']);
        $this->assertSame('ES', $form['language']);
    }

    // --- Send: WhatsApp ---

    public function testSendWhatsAppUsesPlatformId(): void
    {
        $this->configure();
        $this->mockSuccess('wa-id-1');

        $this->createProvider()->send($this->createMessage('whatsapp', '+34611223344', 'Hi WA'));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('whatsapp', $form['class']);
        $this->assertSame('sendmessage', $form['method']);
        $this->assertSame(self::PLATFORM_ID, $form['platformid']);
        $this->assertSame('+34611223344', $form['destination']);
        $this->assertSame('Hi WA', $form['message']);
    }

    public function testSendWhatsAppWithMediaUrlSwitchesToSendFile(): void
    {
        $this->configure();
        $this->mockSuccess('wa-media-1');

        $this->createProvider()->send($this->createMessage('whatsapp', '+34611223344', 'see attached', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('sendfile', $form['method']);
        $this->assertSame('image', $form['type']);
        $this->assertSame('https://example.com/photo.jpg', $form['fileurl']);
        $this->assertSame('see attached', $form['message']);
    }

    public function testSendWhatsAppMediaDetectsDocumentExtension(): void
    {
        $this->configure();
        $this->mockSuccess();

        $this->createProvider()->send($this->createMessage('whatsapp', '+34611223344', '', [
            'media_urls' => ['https://example.com/file.pdf'],
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('document', $form['type']);
    }

    public function testSendWhatsAppFailsWhenPlatformIdMissing(): void
    {
        $this->configure([], ['whatsapp' => ['platform_id' => '']]);

        $result = $this->createProvider()->send($this->createMessage('whatsapp'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Platform ID not configured', $result->error);
    }

    // --- Templates ---

    public function testTemplateModeSendsByIdtemplate(): void
    {
        $this->configure();
        $this->mockSuccess('tpl-id-1');

        $this->createProvider()->send($this->createMessage('sms', '+34611223344', '', [
            'template_mode'        => true,
            'provider_template_id' => 'TPL_OTP',
            'template_variables'   => ['code' => '482916', 'name' => 'Bob'],
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('sms', $form['class']);
        $this->assertSame('sendsmsfromtemplate', $form['method']);
        $this->assertSame('TPL_OTP', $form['idtemplate']);
        $this->assertSame('code:482916,name:Bob', $form['params']);
        $this->assertArrayNotHasKey('sms', $form);
    }

    public function testCatalogTemplateRoutesViaResolveMapping(): void
    {
        $this->configure();
        $this->mockSuccess('cat-id-1');

        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'TPL_AUTH',
            gatewayId: 'afilnet',
            language: '',
            variableMap: ['otp_code' => 'code'],
        );

        $catalogManager = $this->createMock(TemplateCatalogManager::class);
        $catalogManager->expects($this->once())
            ->method('resolveMapping')
            ->with('otp', 'afilnet')
            ->willReturn($mapping);

        $provider = new AfilnetProvider();
        $provider->setCatalogManager($catalogManager);

        $provider->send($this->createMessage('sms', '+34611223344', '', [
            'template_type'      => 'otp',
            'template_variables' => ['otp_code' => '999111'],
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('sendsmsfromtemplate', $form['method']);
        $this->assertSame('TPL_AUTH', $form['idtemplate']);
        $this->assertSame('code:999111', $form['params']);
    }

    public function testBuildTemplatePayloadReturnsNamedKvString(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'TPL',
            gatewayId: 'afilnet',
            language: '',
            variableMap: [],
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, ['a' => '1', 'b' => '2']);

        $this->assertSame('TPL', $payload['idtemplate']);
        $this->assertSame('a:1,b:2', $payload['params']);
    }

    public function testVariableStyleIsNamed(): void
    {
        $this->assertSame(VariableStyle::Named, $this->createProvider()->getVariableStyle());
    }

    public function testRequiresTemplateForChannelReturnsFalse(): void
    {
        $this->assertFalse($this->createProvider()->requiresTemplateForChannel('sms'));
    }

    // --- Credit / test connection ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['status' => 'SUCCESS', 'result' => '42']);

        $this->assertSame('42', $this->createProvider()->getCredit());
    }

    public function testTestConnectionMapsIncorrectUserPasswordTo401Message(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['status' => 'ERROR', 'error' => 'INCORRECT_USER_PASSWORD']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Afilnet credentials', $result->message);
    }

    public function testTestConnectionOkWhenBalanceReturned(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['status' => 'SUCCESS', 'result' => '100']);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('100', $result->message);
    }

    public function testTestConnectionNetworkFailureReturnsError(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_failure', 'Network down');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }
}
