<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\CmtelecomProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class CmtelecomProviderTest extends AbstractProviderTestCase
{
    private const PRODUCT_TOKEN   = 'pt-1234-secret';
    private const ACCOUNT_ID      = 'acct-abc';
    private const CALLBACK_SECRET = 'url-token-xyz';
    private const SMS_FROM        = 'WSMS';
    private const WA_FROM         = '+31612345678';
    private const RCS_FROM        = 'rcs-agent-1';

    protected function createProvider(): AbstractProvider
    {
        return new CmtelecomProvider();
    }

    private function configureAll(array $sharedOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'cmtelecom' => [
                'shared' => array_merge([
                    'product_token'   => self::PRODUCT_TOKEN,
                    'account_id'      => self::ACCOUNT_ID,
                    'callback_secret' => self::CALLBACK_SECRET,
                ], $sharedOverrides),
                'channels' => [
                    'sms'      => ['from' => self::SMS_FROM],
                    'whatsapp' => ['from' => self::WA_FROM],
                    'rcs'      => ['from' => self::RCS_FROM],
                ],
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+31612340000', string $body = 'Hello', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockHttpPost(array|string $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => is_array($responseBody) ? json_encode($responseBody) : $responseBody,
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

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(CmtelecomProvider::TESTED);
    }

    public function testGetIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('cmtelecom', $p->getId());
        $this->assertSame(['sms', 'whatsapp', 'rcs'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();
        $this->assertArrayHasKey('product_token', $schema['shared']);
        $this->assertArrayHasKey('account_id', $schema['shared']);
        $this->assertArrayHasKey('callback_secret', $schema['shared']);
        $this->assertTrue($schema['shared']['product_token']['required']);
        $this->assertSame('secret', $schema['shared']['product_token']['type']);
        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertArrayHasKey('whatsapp', $schema['channels']);
        $this->assertArrayHasKey('rcs', $schema['channels']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
    }

    // --- Send: SMS ---

    public function testSendSmsSuccess(): void
    {
        $this->configureAll();
        $this->mockHttpPost([
            'details' => [[
                'messageDetails' => [['messageId' => 'cm-msg-001', 'status' => 'Accepted']],
            ]],
        ]);

        $result = $this->createProvider()->send($this->createMessage('sms', '+31612340000', 'Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('cm-msg-001', $result->providerId);

        $this->assertSame('https://gw.messaging.cm.com/v1.0/message', $GLOBALS['_test_wp_remote_post_last_url']);
        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::PRODUCT_TOKEN, $args['headers']['X-CM-PRODUCTTOKEN']);

        $body = json_decode($args['body'], true);
        $msg = $body['messages']['msg'][0];
        $this->assertSame(['SMS'], $msg['allowedChannels']);
        $this->assertSame(self::SMS_FROM, $msg['from']);
        $this->assertSame('+31612340000', $msg['to'][0]['number']);
        $this->assertSame('Hi', $msg['body']['content']);
        $this->assertSame('auto', $msg['body']['type']);
        $this->assertStringStartsWith('wsms-', $msg['reference']);
    }

    public function testSendSmsCredentialsFailure(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['errorMessage' => 'Invalid token'], 401);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendSmsServerError(): void
    {
        $this->configureAll();
        $this->mockHttpPost([
            'details' => [[
                'messageDetails' => [['messageErrorCode' => '13', 'messageErrorDescription' => 'Internal']],
            ]],
        ], 500);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertSame('13', $result->meta['cm_code']);
    }

    public function testSendSmsReturnsFailedWhenProductTokenMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $result = $this->createProvider()->send($this->createMessage());
        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendSmsFallsBackToReferenceWhenMessageIdMissing(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['details' => [['messageDetails' => [[]]]]]);

        $result = $this->createProvider()->send($this->createMessage());
        $this->assertTrue($result->success);
        $this->assertStringStartsWith('wsms-', $result->providerId);
    }

    // --- Send: WhatsApp ---

    public function testSendWhatsAppText(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['details' => [['messageDetails' => [['messageId' => 'wa-1']]]]]);

        $this->createProvider()->send($this->createMessage('whatsapp', '+31612340000', 'Hi WA'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $msg = $body['messages']['msg'][0];
        $this->assertSame(['WhatsApp'], $msg['allowedChannels']);
        $this->assertSame(self::WA_FROM, $msg['from']);
        $this->assertSame('Hi WA', $msg['body']['content']);
        $this->assertArrayNotHasKey('richContent', $msg);
    }

    public function testSendWhatsAppViaCatalog(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['details' => [['messageDetails' => [['messageId' => 'wa-tpl-1']]]]]);

        $catalog = $this->createMock(TemplateCatalogManager::class);
        $mapping = new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'verify_code',
            gatewayId: 'cmtelecom',
            language: 'en',
            variableMap: ['namespace' => 'ns-abc', '1' => '1'],
        );
        $catalog->method('resolveMapping')->willReturn($mapping);

        $provider = new CmtelecomProvider();
        $provider->setCatalogManager($catalog);

        $provider->send($this->createMessage('whatsapp', '+31612340000', '', [
            'template_type'      => 'otp',
            'template_variables' => ['1' => '482916'],
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $msg = $body['messages']['msg'][0];
        $this->assertArrayNotHasKey('body', $msg);
        $whatsapp = $msg['richContent']['conversation'][0]['template']['whatsapp'];
        $this->assertSame('verify_code', $whatsapp['name']);
        $this->assertSame('ns-abc', $whatsapp['namespace']);
        $this->assertSame('en', $whatsapp['language']['code']);
        $this->assertSame('482916', $whatsapp['components'][0]['parameters'][0]['text']);
    }

    public function testSendWhatsAppFallsBackToTextWhenNoTemplate(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['details' => [['messageDetails' => [['messageId' => 'wa-fallback']]]]]);

        $catalog = $this->createMock(TemplateCatalogManager::class);
        $catalog->method('resolveMapping')->willReturn(null);

        $provider = new CmtelecomProvider();
        $provider->setCatalogManager($catalog);

        $provider->send($this->createMessage('whatsapp', '+31612340000', 'plain text', [
            'template_type' => 'unknown_type',
        ]));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $msg = $body['messages']['msg'][0];
        $this->assertSame('plain text', $msg['body']['content']);
        $this->assertArrayNotHasKey('richContent', $msg);
    }

    // --- Send: RCS ---

    public function testSendRcs(): void
    {
        $this->configureAll();
        $this->mockHttpPost(['details' => [['messageDetails' => [['messageId' => 'rcs-1']]]]]);

        $this->createProvider()->send($this->createMessage('rcs', '+31612340000', 'Hi RCS'));

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $msg = $body['messages']['msg'][0];
        $this->assertSame(['RCS'], $msg['allowedChannels']);
        $this->assertSame(self::RCS_FROM, $msg['from']);
    }

    // --- Credit / test connection ---

    public function testGetCreditWithoutAccountId(): void
    {
        $this->configureAll(['account_id' => '']);
        // No HTTP mock configured — if provider calls wp_remote_get it will fail.
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditFormatsBalance(): void
    {
        $this->configureAll();
        $this->mockHttpGet(['Currency' => 'EUR', 'Amount' => 84.26]);

        $this->assertSame('84.26 EUR', $this->createProvider()->getCredit());
    }

    public function testTestConnectionMissingAccountId(): void
    {
        $this->configureAll(['account_id' => '']);
        $result = $this->createProvider()->testConnection();
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Account ID', $result->message);
    }

    public function testTestConnectionSuccess(): void
    {
        $this->configureAll();
        $this->mockHttpGet(['Currency' => 'EUR', 'Amount' => 12.50]);

        $result = $this->createProvider()->testConnection();
        $this->assertTrue($result->success);
        $this->assertStringContainsString('12.50 EUR', $result->message);
    }

    public function testTestConnectionInvalidCredentials(): void
    {
        $this->configureAll();
        $this->mockHttpGet(['error' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    // --- Status callback ---

    public function testStatusCallbackDelivered(): void
    {
        $this->configureAll();
        $request = $this->buildRequest('POST', '/x', ['token' => self::CALLBACK_SECRET], [], json_encode([
            'reference' => 'wsms-abc',
            'to'        => '+31612340000',
            'status'    => 2,
            'timestamp' => '2026-05-09T12:00:00Z',
        ]));

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));

        $updates = $this->createProvider()->parseStatusCallback($request);
        $this->assertCount(1, $updates);
        $this->assertSame('wsms-abc', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testStatusCallbackPermanentFailureCode23(): void
    {
        $this->configureAll();
        $request = $this->buildRequest('POST', '/x', ['token' => self::CALLBACK_SECRET], [], json_encode([
            'reference' => 'wsms-abc',
            'status'    => 3,
            'errorCode' => 23,
            'statusDescription' => 'Recipient on blacklist',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertSame('23', $update->errorCode);
    }

    public function testStatusCallbackInvalidToken(): void
    {
        $this->configureAll();

        $missing = $this->buildRequest('POST', '/x', [], [], '{}');
        $this->assertFalse($this->createProvider()->validateStatusCallback($missing));

        $wrong = $this->buildRequest('POST', '/x', ['token' => 'nope'], [], '{}');
        $this->assertFalse($this->createProvider()->validateStatusCallback($wrong));
    }

    public function testStatusCallbackRejectsWhenSecretNotConfigured(): void
    {
        $this->configureAll(['callback_secret' => '']);
        $request = $this->buildRequest('POST', '/x', ['token' => 'anything'], [], '{}');
        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    // --- Inbound callback ---

    public function testInboundMessageParsing(): void
    {
        $this->configureAll();
        $request = $this->buildRequest('POST', '/x', ['token' => self::CALLBACK_SECRET], [], json_encode([
            'from'    => '+31612340000',
            'to'      => self::SMS_FROM,
            'message' => 'STOP',
            'channel' => 'SMS',
            'timeUtc' => '2026-05-09T12:00:00Z',
            'reference' => 'mo-1',
        ]));

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('+31612340000', $msg->from);
        $this->assertSame(self::SMS_FROM, $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('SMS', $msg->meta['channel']);
        $this->assertSame('mo-1', $msg->providerId);
    }

    public function testInboundMessageInvalidToken(): void
    {
        $this->configureAll();
        $request = $this->buildRequest('POST', '/x', ['token' => 'wrong'], [], '{"from":"+31612340000","message":"hi"}');
        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorCodes(): void
    {
        $p = $this->createProvider();
        $this->assertTrue($p->isOptOutError(DeliveryResult::failed('blacklist', ['cm_code' => '23'])));
        $this->assertTrue($p->isOptOutError(DeliveryResult::failed('dnc', ['cm_code' => '37'])));
        $this->assertFalse($p->isOptOutError(DeliveryResult::failed('window closed', ['cm_code' => '40'])));
        $this->assertFalse($p->isOptOutError(DeliveryResult::failed('other', ['cm_code' => '304'])));
        $this->assertFalse($p->isOptOutError(DeliveryResult::failed('no code')));
    }

    // --- SupportsTemplates ---

    public function testRequiresTemplateForChannelReturnsFalse(): void
    {
        $this->assertFalse($this->createProvider()->requiresTemplateForChannel('whatsapp'));
    }

    public function testVariableStyleIsPositional(): void
    {
        $this->assertSame(VariableStyle::Positional, $this->createProvider()->getVariableStyle());
    }

    // --- Helpers ---

    private function buildRequest(string $method, string $route, array $params, array $headers = [], ?string $body = null): \WP_REST_Request
    {
        return new class($method, $route, $params, $headers, $body) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, string $route, array $params, array $headers, ?string $body) {
                parent::__construct($method, $route);
                $this->methodOverride = $method;
                foreach ($params as $k => $v) $this->set_param($k, $v);
                foreach ($headers as $k => $v) $this->set_header($k, $v);
                if ($body !== null) $this->set_body($body);
            }
            public function get_method(): string {
                return $this->methodOverride;
            }
        };
    }
}
