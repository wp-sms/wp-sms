<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\MensatekProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class MensatekProviderTest extends AbstractProviderTestCase
{
    private const API_USER      = 'api-user-1234';
    private const API_TOKEN     = 'api-token-abcdef';
    private const WEBHOOK_TOKEN = 'wh-token-xyz';
    private const SMS_FROM      = 'WSMS';
    private const WA_FROM       = '+34612345678';
    private const RCS_AGENT     = 'agent-001';

    protected function createProvider(): AbstractProvider
    {
        return new MensatekProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $defaultChannels = [
            'sms'      => ['from' => self::SMS_FROM],
            'whatsapp' => ['from' => self::WA_FROM],
            'rcs'      => ['agent_id' => self::RCS_AGENT, 'default_template_id' => ''],
        ];
        foreach ($channelOverrides as $channel => $overrides) {
            $defaultChannels[$channel] = array_merge($defaultChannels[$channel] ?? [], $overrides);
        }

        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mensatek' => [
                'shared' => array_merge([
                    'api_user'      => self::API_USER,
                    'api_token'     => self::API_TOKEN,
                    'webhook_token' => self::WEBHOOK_TOKEN,
                ], $sharedOverrides),
                'channels' => $defaultChannels,
            ],
        ];
    }

    private function createMessage(string $channel = 'sms', string $recipient = '+34612345678', string $body = 'Hola', array $meta = []): Message
    {
        return new Message($channel, $recipient, $body, null, $meta);
    }

    private function mockSendOk(int $msgid = 12345, float $cred = 9.5, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode([
                'Res'   => 1,
                'Msgid' => $msgid,
                'Cred'  => $cred,
                'Destinatarios' => [['Movil' => '+34612345678', 'Status' => 'OK']],
            ]),
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

    private function buildRequest(array $params = [], ?string $jsonBody = null, array $headers = []): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/x');
        foreach ($params as $k => $v) {
            $request->set_param($k, $v);
        }
        foreach ($headers as $k => $v) {
            $request->set_header($k, $v);
        }
        if ($jsonBody !== null) {
            $request->set_body($jsonBody);
        }
        return $request;
    }

    // --- Identity & schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('mensatek', $p->getId());
        $this->assertSame(['sms', 'whatsapp', 'rcs'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(MensatekProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('string', $schema['shared']['api_user']['type']);
        $this->assertTrue($schema['shared']['api_user']['required']);
        $this->assertSame('secret', $schema['shared']['api_token']['type']);
        $this->assertTrue($schema['shared']['api_token']['required']);
        $this->assertSame('secret', $schema['shared']['webhook_token']['type']);
        $this->assertFalse($schema['shared']['webhook_token']['required']);

        $this->assertArrayHasKey('sms', $schema['channels']);
        $this->assertArrayHasKey('whatsapp', $schema['channels']);
        $this->assertArrayHasKey('rcs', $schema['channels']);

        $this->assertTrue($schema['channels']['sms']['from']['required']);
        $this->assertFalse($schema['channels']['whatsapp']['from']['required']);
        $this->assertFalse($schema['channels']['rcs']['agent_id']['required']);
        $this->assertFalse($schema['channels']['rcs']['default_template_id']['required']);
    }

    // --- isConfiguredForChannel ---

    public function testIsConfiguredForChannelSmsRequiresFrom(): void
    {
        $this->configure([], ['sms' => ['from' => '']]);
        $this->assertFalse($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testIsConfiguredForChannelSmsOkWhenFromSet(): void
    {
        $this->configure();
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('sms'));
    }

    public function testIsConfiguredForChannelWhatsappOkWithoutChannelFields(): void
    {
        $this->configure([], ['whatsapp' => ['from' => '']]);
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('whatsapp'));
    }

    public function testIsConfiguredForChannelRcsOkWithoutChannelFields(): void
    {
        $this->configure([], ['rcs' => ['agent_id' => '', 'default_template_id' => '']]);
        $this->assertTrue($this->createProvider()->isConfiguredForChannel('rcs'));
    }

    // --- Send: SMS ---

    public function testSmsSendQueuedReturnsMsgid(): void
    {
        $this->configure();
        $this->mockSendOk(987654);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('987654', $result->providerId);
    }

    public function testSmsSendPostsFormEncodedToEnviarSmsWithBasicAuth(): void
    {
        $this->configure();
        $this->mockSendOk();

        $this->createProvider()->send($this->createMessage('sms', '+34612345678', 'Hola mundo'));

        $this->assertSame('https://api.mensatek.com/v7/EnviarSMS', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $expectedAuth = 'Basic ' . base64_encode(self::API_USER . ':' . self::API_TOKEN);
        $this->assertSame($expectedAuth, $args['headers']['Authorization']);
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $form);
        $this->assertSame('JSON', $form['Resp']);
        $this->assertSame(self::SMS_FROM, $form['Remitente']);
        $this->assertSame('Hola mundo', $form['Mensaje']);

        $destinatarios = json_decode($form['Destinatarios'], true);
        $this->assertIsArray($destinatarios);
        $this->assertSame('+34612345678', $destinatarios[0]['Movil']);
    }

    public function testSmsSendInsufficientCreditsReturnsFailed(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['Res' => -2]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credit', strtolower($result->error));
    }

    public function testSmsSendAuthErrorReturnsFailed(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['Res' => -1]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credentials', strtolower($result->error));
    }

    public function testSmsSendParamErrorSurfacesErrorString(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['Res' => -3, 'Error' => 'Invalid Remitente length']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid Remitente length', $result->error);
    }

    public function testSmsSendFailsWhenSenderMissing(): void
    {
        $this->configure([], ['sms' => ['from' => '']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- Send: WhatsApp ---

    public function testWhatsappSendTextDispatchesToEnviarWHATSAPP(): void
    {
        $this->configure();
        $this->mockSendOk();

        $this->createProvider()->send($this->createMessage('whatsapp', '+34612345678', 'Hola WA'));

        $this->assertSame('https://api.mensatek.com/v7/EnviarWHATSAPP', $GLOBALS['_test_wp_remote_post_last_url']);

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('TEXTO', $form['Tipomensaje']);

        $destinatarios = json_decode($form['Destinatarios'], true);
        $this->assertSame('+34612345678', $destinatarios[0]['Telefono']);
    }

    public function testWhatsappSendWithMediaUrlsBecomesIMAGEN(): void
    {
        $this->configure();
        $this->mockSendOk();

        $this->createProvider()->send($this->createMessage('whatsapp', '+34612345678', 'see attached', [
            'media_urls' => ['https://example.com/photo.jpg'],
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('IMAGEN', $form['Tipomensaje']);

        $datos = json_decode($form['DatosMensaje'], true);
        $this->assertSame('https://example.com/photo.jpg', $datos['URL']);
    }

    public function testWhatsappSendTemplateFromMeta(): void
    {
        $this->configure();
        $this->mockSendOk();

        $this->createProvider()->send($this->createMessage('whatsapp', '+34612345678', '', [
            'template_name' => 'verify_code',
        ]));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('PLANTILLA', $form['Tipomensaje']);

        $datos = json_decode($form['DatosMensaje'], true);
        $this->assertSame('verify_code', $datos['Plantilla']);
    }

    // --- Send: RCS ---

    public function testRcsSendIncludesPlantillaAndAgente(): void
    {
        $this->configure();
        $this->mockSendOk();

        $this->createProvider()->send($this->createMessage('rcs', '+34612345678', 'Hi via RCS', [
            'template_id' => '4242',
        ]));

        $this->assertSame('https://api.mensatek.com/v7/EnviarRCS', $GLOBALS['_test_wp_remote_post_last_url']);

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('4242', $form['Plantilla']);
        $this->assertSame(self::RCS_AGENT, $form['Agente']);

        $destinatarios = json_decode($form['Destinatarios'], true);
        $this->assertSame('+34612345678', $destinatarios[0]['Telefono']);
    }

    public function testRcsSendUsesDefaultTemplateIdFromConfig(): void
    {
        $this->configure([], ['rcs' => ['agent_id' => self::RCS_AGENT, 'default_template_id' => '777']]);
        $this->mockSendOk();

        $this->createProvider()->send($this->createMessage('rcs', '+34612345678', 'msg'));

        parse_str($GLOBALS['_test_wp_remote_post_last_args']['body'], $form);
        $this->assertSame('777', $form['Plantilla']);
    }

    public function testRcsSendFailsWhenNoTemplateProvidedAndNoDefault(): void
    {
        $this->configure();

        $result = $this->createProvider()->send($this->createMessage('rcs'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('template', strtolower($result->error));
    }

    // --- Credit / Test connection ---

    public function testGetCreditPostsToGetCreditosWithBasicAuth(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['Cred' => 12.34]);

        $credit = $this->createProvider()->getCredit();

        $this->assertSame('https://api.mensatek.com/v7/GetCreditos', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $expectedAuth = 'Basic ' . base64_encode(self::API_USER . ':' . self::API_TOKEN);
        $this->assertSame($expectedAuth, $args['headers']['Authorization']);

        $this->assertNotNull($credit);
        $this->assertStringContainsString('12.34', $credit);
    }

    public function testGetCreditReturnsNullOnAuthError(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['error' => 'unauth'], 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionOkWhenCreditEndpointReturns200(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['Cred' => 5.0]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Mensatek', $result->message);
    }

    public function testTestConnectionInvalidOn401(): void
    {
        $this->configure();
        $this->mockRawPostResponse(['error' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    // --- Status callback validation/parsing ---

    public function testStatusCallbackUrlIncludesTokenWhenConfigured(): void
    {
        $this->configure();
        $url = $this->createProvider()->getStatusCallbackUrl();
        $this->assertStringContainsString('token=' . self::WEBHOOK_TOKEN, $url);
    }

    public function testStatusCallbackUrlOmitsTokenWhenNotConfigured(): void
    {
        $this->configure(['webhook_token' => '']);
        $url = $this->createProvider()->getStatusCallbackUrl();
        $this->assertStringNotContainsString('token=', $url);
    }

    public function testStatusCallbackTokenRejectedWhenWebhookTokenSetButQueryMissing(): void
    {
        $this->configure();
        $request = $this->buildRequest([]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptedWhenTokenMatches(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => self::WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackRejectedWhenTokenMismatched(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => 'wrong-token']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testStatusCallbackAcceptedWhenWebhookTokenUnset(): void
    {
        $this->configure(['webhook_token' => '']);
        $request = $this->buildRequest([]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackDeliveredCode14(): void
    {
        $request = $this->buildRequest([], json_encode([
            'Servicio'   => 'SMSMASIVO',
            'Resultado'  => 14,
            'idMensaje'  => 12345,
            'Movil'      => '+34612345678',
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('12345', $update->providerId);
        $this->assertSame('delivered', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testParseStatusCallbackQueuedCode11(): void
    {
        $request = $this->buildRequest([], json_encode([
            'Servicio'   => 'SMSMASIVO',
            'Resultado'  => 11,
            'idMensaje'  => 12345,
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('queued', $update->status);
    }

    public function testParseStatusCallbackPermanentFailureCode51(): void
    {
        $request = $this->buildRequest([], json_encode([
            'Servicio'   => 'SMSMASIVO',
            'Resultado'  => 51,
            'idMensaje'  => 12345,
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
    }

    public function testParseStatusCallbackTransientFailureCode120(): void
    {
        $request = $this->buildRequest([], json_encode([
            'Servicio'   => 'SMSMASIVO',
            'Resultado'  => 120,
            'idMensaje'  => 12345,
        ]));

        $update = $this->createProvider()->parseStatusCallback($request)[0];
        $this->assertSame('failed', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testStatusCallbackIgnoresSMSRECIBIDO(): void
    {
        $request = $this->buildRequest([], json_encode([
            'Servicio'   => 'SMSRECIBIDO',
            'idR'        => 99,
            'Mensaje'    => 'STOP',
        ]));

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Inbound callback validation/parsing ---

    public function testInboundCallbackUrlIncludesTokenWhenConfigured(): void
    {
        $this->configure();
        $url = $this->createProvider()->getInboundCallbackUrl();
        $this->assertStringContainsString('token=' . self::WEBHOOK_TOKEN, $url);
    }

    public function testInboundCallbackUsesSameTokenCheck(): void
    {
        $this->configure();
        $ok = $this->buildRequest(['token' => self::WEBHOOK_TOKEN]);
        $bad = $this->buildRequest(['token' => 'nope']);

        $p = $this->createProvider();
        $this->assertTrue($p->validateInboundCallback($ok));
        $this->assertFalse($p->validateInboundCallback($bad));
    }

    public function testParseInboundCallbackBuildsInboundMessage(): void
    {
        $request = $this->buildRequest([], json_encode([
            'Servicio'  => 'SMSRECIBIDO',
            'timestamp' => 1714400000,
            'Fecha'     => '2026-04-30 10:00:00',
            'Movil'     => '+34900111222',
            'Remitente' => '+34612345678',
            'Mensaje'   => 'HELP me',
            'idR'       => 4242,
            'idM'       => 9999,
            'EsCert'    => 0,
            'Referencia' => 'ref-1',
        ]));

        $msg = $this->createProvider()->parseInboundCallback($request)[0];
        $this->assertSame('+34612345678', $msg->from);
        $this->assertSame('+34900111222', $msg->to);
        $this->assertSame('HELP me', $msg->body);
        $this->assertSame('4242', $msg->providerId);
    }

    public function testInboundIgnoresStatusReports(): void
    {
        $request = $this->buildRequest([], json_encode([
            'Servicio'   => 'SMSMASIVO',
            'Resultado'  => 14,
            'idMensaje'  => 12345,
        ]));

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }
}
