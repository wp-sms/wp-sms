<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\TextAnywhereProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class TextAnywhereProviderTest extends AbstractProviderTestCase
{
    private const USERNAME       = 'demo@example.com';
    private const API_PASSWORD   = 'api-pass-xyz';
    private const SENDER         = 'WPSMS';
    private const CALLBACK_TOKEN = 'webhook-secret-abc';
    private const USER_KEY       = 'u-key-123';
    private const ACCESS_TOKEN   = 'a-tok-456';

    protected function createProvider(): AbstractProvider
    {
        return new TextAnywhereProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'textanywhere' => [
                'shared'   => array_merge([
                    'username'       => self::USERNAME,
                    'api_password'   => self::API_PASSWORD,
                    'message_type'   => 'GP',
                    'callback_token' => self::CALLBACK_TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+447700900123', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    /**
     * Mock GET so /token returns the semicolon-separated string and /status (or
     * any other GET) returns the supplied JSON body. Tests that don't need the
     * status response can pass [] and ignore it.
     */
    private function mockGetWithToken(array $statusBody = [], int $statusCode = 200, int $tokenCode = 200, ?string $tokenBody = null): void
    {
        $tokenBody ??= self::USER_KEY . ';' . self::ACCESS_TOKEN;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use ($tokenBody, $tokenCode, $statusBody, $statusCode) {
            $GLOBALS['_test_wp_remote_get_last_url'] = $url;
            $GLOBALS['_test_wp_remote_get_last_args'] = $args;
            if (str_contains($url, '/token')) {
                return ['body' => $tokenBody, 'response' => ['code' => $tokenCode]];
            }
            return ['body' => json_encode($statusBody), 'response' => ['code' => $statusCode]];
        };
    }

    private function mockHttpPost(array $body, int $code = 201): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($body),
            'response' => ['code' => $code],
        ];
    }

    /**
     * WP_REST_Request with overridden get_method() — providers don't read method
     * here, but the helper mirrors the pattern used by the other provider tests.
     */
    private function buildRequest(string $method, array $params): \WP_REST_Request
    {
        return new class($method, $params) extends \WP_REST_Request {
            private string $methodOverride;
            public function __construct(string $method, array $params)
            {
                parent::__construct($method, '/');
                $this->methodOverride = $method;
                foreach ($params as $k => $v) {
                    $this->set_param($k, $v);
                }
            }
            public function get_method(): string
            {
                return $this->methodOverride;
            }
        };
    }

    // --- Identity / pinning ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(TextAnywhereProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('textanywhere', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertSame('secret', $schema['shared']['api_password']['type']);
        $this->assertSame('select', $schema['shared']['message_type']['type']);
        $this->assertSame('GP', $schema['shared']['message_type']['default']);
        $this->assertFalse($schema['shared']['callback_token']['required']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
    }

    // --- doSend ---

    public function testDoSendIssuesTokenThenPostsSms(): void
    {
        $this->configure();
        $this->mockGetWithToken();
        $this->mockHttpPost(['order_id' => 'msg-7', 'total_sent' => 1, 'result' => 'OK', 'remaining_credits' => 99]);

        $result = $this->createProvider()->send($this->createMessage('+447700900123', 'Hi'));

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('msg-7', $result->providerId);

        $this->assertSame('https://api.textanywhere.com/API/v1.0/REST/sms', $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::USER_KEY, $args['headers']['user_key']);
        $this->assertSame(self::ACCESS_TOKEN, $args['headers']['Access_token']);
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = json_decode($args['body'], true);
        $this->assertSame('GP', $body['message_type']);
        $this->assertSame('Hi', $body['message']);
        $this->assertSame('+447700900123', $body['recipient']);
        $this->assertSame(self::SENDER, $body['sender']);
        $this->assertStringContainsString('callbacks/textanywhere/status', $body['statusnotificationURL']);
        $this->assertStringContainsString('token=' . self::CALLBACK_TOKEN, $body['statusnotificationURL']);
    }

    public function testDoSendUsesBasicAuthOnTokenRequest(): void
    {
        $this->configure();
        $this->mockGetWithToken();
        $this->mockHttpPost(['order_id' => 'msg-1']);

        $this->createProvider()->send($this->createMessage());

        $tokenArgs = $GLOBALS['_test_wp_remote_get_last_args'];
        $this->assertSame(
            'Basic ' . base64_encode(self::USERNAME . ':' . self::API_PASSWORD),
            $tokenArgs['headers']['Authorization'],
        );
    }

    public function testDoSendOmitsStatusnotificationUrlWhenCallbackTokenMissing(): void
    {
        $this->configure(sharedOverrides: ['callback_token' => '']);
        $this->mockGetWithToken();
        $this->mockHttpPost(['order_id' => 'msg-1']);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertArrayNotHasKey('statusnotificationURL', $body);
    }

    public function testDoSendUsesGsMessageTypeWhenConfigured(): void
    {
        $this->configure(sharedOverrides: ['message_type' => 'GS']);
        $this->mockGetWithToken();
        $this->mockHttpPost(['order_id' => 'msg-1']);

        $this->createProvider()->send($this->createMessage());

        $body = json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
        $this->assertSame('GS', $body['message_type']);
    }

    public function testDoSendReturnsFailedOnApiErrorMessage(): void
    {
        $this->configure();
        $this->mockGetWithToken();
        $this->mockHttpPost(['error_message' => 'Insufficient credit'], 400);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Insufficient credit', $result->error);
    }

    public function testDoSendReturnsFailedOn401TokenResponse(): void
    {
        $this->configure();
        $this->mockGetWithToken(tokenCode: 401, tokenBody: 'unauthorised');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testDoSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'textanywhere' => [
                'shared'   => ['username' => '', 'api_password' => ''],
                'channels' => ['sms' => ['from' => self::SENDER]],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->error);
    }

    public function testDoSendReturnsFailedWhenSenderMissing(): void
    {
        $this->configure(smsOverrides: ['from' => '']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    public function testDoSendReturnsFailedOnMalformedTokenBody(): void
    {
        $this->configure();
        $this->mockGetWithToken(tokenBody: 'no-semicolon-here');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unexpected token response', $result->error);
    }

    // --- Credit / test connection ---

    public function testGetCreditConcatenatesCreditTypes(): void
    {
        $this->configure();
        $this->mockGetWithToken([
            'sms' => [
                ['type' => 'GP', 'quantity' => 50],
                ['type' => 'GS', 'quantity' => 10],
            ],
        ]);

        $this->assertSame('GP: 50, GS: 10', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenStatusEmpty(): void
    {
        $this->configure();
        $this->mockGetWithToken(['sms' => []]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionSucceeds(): void
    {
        $this->configure();
        $this->mockGetWithToken([
            'sms' => [['type' => 'GP', 'quantity' => 25]],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('GP: 25', $result->details['balance']);
    }

    public function testTestConnectionFailsOn401(): void
    {
        $this->configure();
        $this->mockGetWithToken(tokenCode: 401, tokenBody: 'unauth');

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

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['token' => self::CALLBACK_TOKEN, 'messagereference' => 'm-1']);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['messagereference' => 'm-1']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['token' => 'wrong', 'messagereference' => 'm-1']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenTokenUnset(): void
    {
        $this->configure(sharedOverrides: ['callback_token' => '']);
        $request = $this->buildRequest('GET', ['token' => 'anything', 'messagereference' => 'm-1']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsTerminalCodes(): void
    {
        $cases = [
            // code => [status, permanent, unsubscribe]
            400 => ['delivered', true,  false],
            499 => ['delivered', true,  false],
            511 => ['failed',    true,  false],
            515 => ['failed',    true,  true],
            599 => ['failed',    true,  false],
            600 => ['sent',      false, false],
            601 => ['sent',      false, false],
        ];

        $p = $this->createProvider();
        foreach ($cases as $code => [$status, $permanent, $unsubscribe]) {
            $request = $this->buildRequest('GET', [
                'messagereference'  => 'm-' . $code,
                'messagestatuscode' => (string) $code,
            ]);
            $updates = $p->parseStatusCallback($request);

            $this->assertCount(1, $updates, "no update for code {$code}");
            $this->assertSame($status, $updates[0]->status, "wrong status for code {$code}");
            $this->assertSame($permanent, $updates[0]->permanent, "wrong permanent for code {$code}");
            $this->assertSame($unsubscribe, $updates[0]->unsubscribe, "wrong unsubscribe for code {$code}");
            $this->assertSame((string) $code, $updates[0]->errorCode);
        }
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('GET', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testStatusCallbackUrlIsBare(): void
    {
        $this->assertStringContainsString(
            'callbacks/textanywhere/status',
            $this->createProvider()->getStatusCallbackUrl(),
        );
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackAcceptsMatchingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['token' => self::CALLBACK_TOKEN, 'Originator' => '+447700900123']);

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('GET', ['Originator' => '+447700900123']);

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundCallbackBuildsInboundMessage(): void
    {
        $request = $this->buildRequest('GET', [
            'Originator'  => '+447700900999',
            'Destination' => '1234_KEYWORD',
            'Body'        => 'STOP',
            'RBID'        => 'rbid-77',
            'Date'        => '01/05/2026',
            'Time'        => '12:34:56',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $this->assertSame('+447700900999', $messages[0]->from);
        $this->assertSame('1234_KEYWORD', $messages[0]->to);
        $this->assertSame('STOP', $messages[0]->body);
        $this->assertSame('rbid-77', $messages[0]->providerId);
        $this->assertSame('01/05/2026', $messages[0]->meta['date']);
        $this->assertSame('12:34:56', $messages[0]->meta['time']);
    }

    public function testParseInboundCallbackEmptyWithoutOriginator(): void
    {
        $request = $this->buildRequest('GET', []);
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }
}
