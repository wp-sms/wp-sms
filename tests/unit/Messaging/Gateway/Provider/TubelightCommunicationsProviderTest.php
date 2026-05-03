<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\TubelightCommunicationsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class TubelightCommunicationsProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = 'tubelight-user';
    private const PASSWORD = 'tubelight-pass';
    private const SENDER_ID = 'WSMSAB';
    private const ACCESS_TOKEN = 'jwt-access-token-xyz';
    private const LOGIN_PATH = '/api/authentication/login';
    private const SMS_PATH = '/sms/api/v1/websms/bulksend';
    private const WHATSAPP_PATH = '/whatsapp/api/v1/send';
    private const BALANCE_PATH = '/sms/api/v1/balance';

    protected function createProvider(): AbstractProvider
    {
        return new TubelightCommunicationsProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
        parent::tearDown();
    }

    private function configure(array $extraShared = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'tubelightcommunications' => [
                'shared' => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $extraShared),
                'channels' => array_merge([
                    'sms'      => ['sender_id' => self::SENDER_ID],
                    'whatsapp' => [],
                ], $channelOverrides),
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '919811000000', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    /**
     * Wire wp_remote_post to dispatch to per-URL fakes.
     *
     * Each handler returns a wp_remote_post-shaped array
     * (['body' => string, 'response' => ['code' => int]]).
     *
     * @param array<string, callable(string $url, array $args): array> $handlers
     */
    private function mockPostByPath(array $handlers, ?array &$capture = null): void
    {
        $capture = ['calls' => []];
        $GLOBALS['_test_wp_remote_post'] = function ($url, $args) use ($handlers, &$capture) {
            $capture['calls'][] = ['url' => $url, 'args' => $args];
            foreach ($handlers as $needle => $handler) {
                if (str_contains($url, $needle)) {
                    return $handler($url, $args);
                }
            }
            return ['body' => '{}', 'response' => ['code' => 500]];
        };
    }

    private function loginOk(): callable
    {
        return fn() => [
            'body'     => json_encode(['accessToken' => self::ACCESS_TOKEN]),
            'response' => ['code' => 200],
        ];
    }

    private function jsonResponse(array $payload, int $status = 200): callable
    {
        return fn() => [
            'body'     => json_encode($payload),
            'response' => ['code' => $status],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(TubelightCommunicationsProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('tubelightcommunications', $p->getId());
        $this->assertSame(['sms', 'whatsapp'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);

        $this->assertArrayHasKey('password', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);

        $this->assertArrayHasKey('sender_id', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['sender_id']['required']);
        $this->assertSame([], $schema['channels']['whatsapp']);
    }

    // --- SMS ---

    public function testSendSmsRequiresAuthThenSends(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH => $this->loginOk(),
            self::SMS_PATH   => $this->jsonResponse(['refId' => 'sms-ref-1']),
        ], $capture);

        $result = $this->createProvider()->send($this->createMessage('sms', '919811000000', 'Hi there'));

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('sms-ref-1', $result->providerId);

        $this->assertCount(2, $capture['calls']);
        $this->assertStringContainsString(self::LOGIN_PATH, $capture['calls'][0]['url']);
        $this->assertStringContainsString(self::SMS_PATH, $capture['calls'][1]['url']);

        $sendArgs = $capture['calls'][1]['args'];
        $this->assertSame('Bearer ' . self::ACCESS_TOKEN, $sendArgs['headers']['Authorization']);
        $this->assertSame('application/json', $sendArgs['headers']['Content-Type']);

        $body = json_decode($sendArgs['body'], true);
        $this->assertIsArray($body);
        $this->assertCount(1, $body);
        $this->assertSame(self::SENDER_ID, $body[0]['sender']);
        $this->assertSame('919811000000', $body[0]['mobileNo']);
        $this->assertSame('TEXT', $body[0]['messageType']);
        $this->assertSame('Hi there', $body[0]['messages']);
        $this->assertSame('', $body[0]['tempId']);
    }

    public function testSendSmsFailsOnLoginAuthError(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH => $this->jsonResponse(['error' => 'bad credentials'], 401),
        ], $capture);

        $result = $this->createProvider()->send($this->createMessage('sms', '919811000000', 'Hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Tubelight Communications credentials', $result->error);
        $this->assertCount(1, $capture['calls']);
    }

    public function testSendSmsFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage('sms'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendSmsFailsWhenSenderIdMissing(): void
    {
        $this->configure(channelOverrides: ['sms' => []]);
        $this->mockPostByPath([
            self::LOGIN_PATH => $this->loginOk(),
        ], $capture);

        $result = $this->createProvider()->send($this->createMessage('sms', '919811000000', 'Hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    public function testSendSmsBodyIncludesTempIdWhenTemplateMode(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH => $this->loginOk(),
            self::SMS_PATH   => $this->jsonResponse(['refId' => 'sms-tpl-1']),
        ], $capture);

        $this->createProvider()->send($this->createMessage('sms', '919811000000', 'Your code is 482916', [
            'template_mode'        => true,
            'provider_template_id' => '1707171234567890123',
            'template_variables'   => ['1' => '482916'],
        ]));

        $body = json_decode($capture['calls'][1]['args']['body'], true);
        $this->assertSame('1707171234567890123', $body[0]['tempId']);
        $this->assertSame('Your code is 482916', $body[0]['messages']);
    }

    public function testSendSmsRoutesThroughCatalogWhenTemplateTypeProvided(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH => $this->loginOk(),
            self::SMS_PATH   => $this->jsonResponse(['refId' => 'sms-cat-1']),
        ], $capture);

        $catalog = $this->createMock(TemplateCatalogManager::class);
        $catalog->method('resolveMapping')
            ->with('otp', 'tubelightcommunications')
            ->willReturn(new TemplateMapping(
                templateType: 'otp',
                providerTemplateId: '1707170000000099999',
                gatewayId: 'tubelightcommunications',
                language: 'en',
                variableMap: ['otp_code' => '1'],
            ));

        $provider = new TubelightCommunicationsProvider();
        $provider->setCatalogManager($catalog);

        $provider->send($this->createMessage('sms', '919811000000', 'Code: 482916', [
            'template_type'      => 'otp',
            'template_variables' => ['otp_code' => '482916'],
        ]));

        $body = json_decode($capture['calls'][1]['args']['body'], true);
        $this->assertSame('1707170000000099999', $body[0]['tempId']);
    }

    public function testSendSmsBubblesUpProviderErrorMessage(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH => $this->loginOk(),
            self::SMS_PATH   => $this->jsonResponse(['message' => 'Insufficient balance'], 400),
        ], $capture);

        $result = $this->createProvider()->send($this->createMessage('sms', '919811000000', 'Hi'));

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient balance', $result->error);
    }

    // --- WhatsApp ---

    public function testSendWhatsAppRequiresApprovedTemplate(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH => $this->loginOk(),
        ], $capture);

        $result = $this->createProvider()->send($this->createMessage('whatsapp', '919811000000', 'Hi WA'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('approved template', $result->error);
        $this->assertCount(1, $capture['calls']);
    }

    public function testSendWhatsAppTemplateBuildsBodyParams(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH    => $this->loginOk(),
            self::WHATSAPP_PATH => $this->jsonResponse(['messageId' => 'wa-1']),
        ], $capture);

        $result = $this->createProvider()->send($this->createMessage('whatsapp', '919811000000', '', [
            'template_mode'        => true,
            'provider_template_id' => 'tpl_otp',
            'template_variables'   => ['2' => 'Bob', '1' => 'Alice', '3' => '482916'],
        ]));

        $this->assertTrue($result->success);
        $this->assertSame('wa-1', $result->providerId);

        $sendArgs = $capture['calls'][1]['args'];
        $this->assertStringContainsString(self::WHATSAPP_PATH, $capture['calls'][1]['url']);
        $this->assertSame('Bearer ' . self::ACCESS_TOKEN, $sendArgs['headers']['Authorization']);

        $body = json_decode($sendArgs['body'], true);
        $this->assertSame(['919811000000'], $body['to']);
        $this->assertSame('tpl_otp', $body['message']['template_name']);
        $this->assertSame('template', $body['message']['type']);
        $this->assertSame(['Alice', 'Bob', '482916'], $body['message']['body_params']);
        $this->assertSame([], $body['message']['header_params']);
    }

    public function testSendWhatsAppIncludesMediaUrlsAsHeaderParams(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH    => $this->loginOk(),
            self::WHATSAPP_PATH => $this->jsonResponse(['messageId' => 'wa-img-1']),
        ], $capture);

        $this->createProvider()->send($this->createMessage('whatsapp', '919811000000', '', [
            'template_mode'        => true,
            'provider_template_id' => 'tpl_promo',
            'template_variables'   => ['1' => 'Alice'],
            'media_urls'           => ['https://example.com/photo.jpg'],
        ]));

        $body = json_decode($capture['calls'][1]['args']['body'], true);
        $this->assertSame(['https://example.com/photo.jpg'], $body['message']['header_params']);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH   => $this->loginOk(),
            self::BALANCE_PATH => $this->jsonResponse(['balance' => 1234.5]),
        ], $capture);

        $this->assertSame('1234.5', $this->createProvider()->getCredit());
        $this->assertCount(2, $capture['calls']);
        $this->assertStringContainsString(self::BALANCE_PATH, $capture['calls'][1]['url']);
    }

    public function testGetCreditReturnsNullWhenLoginFails(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH => $this->jsonResponse(['error' => 'bad'], 401),
        ], $capture);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionMapsAuthErrors(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH => $this->jsonResponse(['error' => 'bad'], 401),
        ], $capture);

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

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $this->mockPostByPath([
            self::LOGIN_PATH   => $this->loginOk(),
            self::BALANCE_PATH => $this->jsonResponse(['balance' => '500']),
        ], $capture);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500', $result->message);
        $this->assertSame('500', $result->details['balance']);
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

    public function testBuildTemplatePayloadKsortsAndReturnsPositionalVars(): void
    {
        $mapping = new TemplateMapping(
            templateType: 'welcome',
            providerTemplateId: 'tpl_welcome',
            gatewayId: 'tubelightcommunications',
            language: 'en',
            variableMap: [],
        );

        $payload = $this->createProvider()->buildTemplatePayload($mapping, [
            '2' => 'Bob', '1' => 'Alice', '3' => 'Acme',
        ]);

        $this->assertSame('tpl_welcome', $payload['template_id']);
        $this->assertSame(['Alice', 'Bob', 'Acme'], $payload['variables']);
    }
}
