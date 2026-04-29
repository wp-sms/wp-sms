<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\MtargetProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class MtargetProviderTest extends AbstractProviderTestCase
{
    private const USERNAME       = 'mtarget-user';
    private const PASSWORD       = 'mtarget-pass';
    private const SENDER         = 'WSMS';
    private const CALLBACK_TOKEN = 'webhook-secret-xyz';

    protected function createProvider(): AbstractProvider
    {
        return new MtargetProvider();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mtarget' => [
                'shared' => array_merge([
                    'username'       => self::USERNAME,
                    'password'       => self::PASSWORD,
                    'callback_token' => self::CALLBACK_TOKEN,
                    'send_unicode'   => false,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+33612345678', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
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
        $this->assertFalse(MtargetProvider::TESTED);
    }

    public function testIdAndChannelsAreSmsOnly(): void
    {
        $p = $this->createProvider();
        $this->assertSame('mtarget', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertSame('secret', $schema['shared']['password']['type']);
        $this->assertTrue($schema['shared']['password']['required']);
        $this->assertSame('secret', $schema['shared']['callback_token']['type']);
        $this->assertSame('boolean', $schema['shared']['send_unicode']['type']);

        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
    }

    // --- Send ---

    public function testSendReturnsQueuedWithTicketOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'results' => [
                ['code' => 0, 'msisdn' => '+33612345678', 'ticket' => 'ticket-abc-123'],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('ticket-abc-123', $result->providerId);
    }

    public function testSendPostsFormUrlencodedBodyToMessagesEndpoint(): void
    {
        $this->configure();
        $this->mockHttpPost(['results' => [['code' => 0, 'ticket' => 't-1']]]);

        $this->createProvider()->send($this->createMessage('+33611111111', 'Hi there'));

        $this->assertSame(
            'https://api-public-2.mtarget.fr/messages',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        $body = $args['body'];
        $this->assertSame(self::USERNAME, $body['username']);
        $this->assertSame(self::PASSWORD, $body['password']);
        $this->assertSame(self::SENDER, $body['sender']);
        $this->assertSame('+33611111111', $body['msisdn']);
        $this->assertSame('Hi there', $body['msg']);
        $this->assertSame('false', $body['allowunicode']);
    }

    public function testSendUnicodeFlagTogglesAllowunicode(): void
    {
        $this->configure(['send_unicode' => true]);
        $this->mockHttpPost(['results' => [['code' => 0, 'ticket' => 't-1']]]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('true', $GLOBALS['_test_wp_remote_post_last_args']['body']['allowunicode']);
    }

    public function testSendFailsWithDescriptiveMessageForKnownErrorCodes(): void
    {
        $cases = [
            -1  => 'Authentication',
            -2  => 'Invalid recipient',
            -4  => 'No route',
            -10 => 'too long',
            -11 => 'Insufficient credit',
            -12 => 'Invalid parameter',
        ];

        foreach ($cases as $code => $expectedFragment) {
            $this->configure();
            $this->mockHttpPost(['results' => [['code' => $code, 'msisdn' => '+33611111111']]]);

            $result = $this->createProvider()->send($this->createMessage());

            $this->assertFalse($result->success, "code {$code} should fail");
            $this->assertStringContainsString($expectedFragment, $result->error, "code {$code} message");
            $this->assertSame((string) $code, $result->meta['mtarget_code']);
        }
    }

    public function testSendUsesProviderReasonWhenPresent(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'results' => [['code' => -2, 'reason' => 'msisdn malformed']],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('msisdn malformed', $result->error);
        $this->assertSame('msisdn malformed', $result->meta['mtarget_reason']);
    }

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsWhenSenderMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'mtarget' => [
                'shared'   => ['username' => self::USERNAME, 'password' => self::PASSWORD],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender ID', $result->error);
    }

    public function testSendFailsOnUnexpectedResponseShape(): void
    {
        $this->configure();
        $this->mockHttpPost(['unexpected' => true]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unexpected', $result->error);
    }

    // --- getCredit / testConnection ---

    public function testGetCreditReturnsFormattedAmount(): void
    {
        $this->configure();
        $this->mockHttpPost(['amount' => 12.5]);

        $this->assertSame('12.50', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenAmountMissing(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'auth failed']);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configure();
        $this->mockHttpPost(['amount' => 7.25]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('7.25', $result->message);
        $this->assertSame('7.25', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOnAuthFailureBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'authentication failed']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('authentication failed', $result->message);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackRejectsWhenTokenNotConfigured(): void
    {
        $this->configure(['callback_token' => '']);
        $request = $this->buildRequest('POST', '/x', ['token' => 'whatever']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', []);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWrongToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => 'not-the-token']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsCorrectToken(): void
    {
        $this->configure();
        $request = $this->buildRequest('POST', '/x', ['token' => self::CALLBACK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackDeliveredIsPermanent(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'MsgId'  => 'ticket-1',
            'Status' => 3,
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('ticket-1', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertTrue($updates[0]->permanent);
    }

    public function testParseStatusCallbackRefusedIsPermanentFailure(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'MsgId'      => 'ticket-2',
            'Status'     => 4,
            'Reason'     => 'rejected by operator',
            'StatusText' => 'refused',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
        $this->assertStringContainsString('rejected by operator', $update->errorMessage);
    }

    public function testParseStatusCallbackNotDeliveredIsPermanentFailure(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'MsgId'  => 'ticket-3',
            'Status' => 6,
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('failed', $update->status);
        $this->assertTrue($update->permanent);
    }

    public function testParseStatusCallbackInProgressIsTransient(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'MsgId'  => 'ticket-4',
            'Status' => 1,
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('sent', $update->status);
        $this->assertFalse($update->permanent);
    }

    public function testParseStatusCallbackSkipsMoStatusFive(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'MsgId'  => 'mo-1',
            'Status' => 5,
        ]);

        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $this->assertSame([], $this->createProvider()->parseStatusCallback(
            $this->buildRequest('POST', '/x', []),
        ));
    }

    public function testParseStatusCallbackCapturesRsnAsErrorCode(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'MsgId'  => 'ticket-5',
            'Status' => 4,
            'RSN'    => '52',
        ]);

        $update = $this->createProvider()->parseStatusCallback($request)[0];

        $this->assertSame('52', $update->errorCode);
    }

    // --- Inbound callback ---

    public function testParseInboundCallbackBuildsInboundMessageOnStatusFive(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'Status'            => 5,
            'OriginatedAddress' => '+33612345678',
            'DestinationAdress' => self::SENDER,
            'Msg'               => 'Reply text from user',
            'MsgId'             => 'mo-msg-1',
        ]);

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+33612345678', $msg->from);
        $this->assertSame(self::SENDER, $msg->to);
        $this->assertSame('Reply text from user', $msg->body);
        $this->assertSame('mo-msg-1', $msg->providerId);
    }

    public function testParseInboundCallbackSkipsNonMo(): void
    {
        $request = $this->buildRequest('POST', '/x', [
            'Status' => 3, 'MsgId' => 'ticket-1', 'OriginatedAddress' => '+33612345678',
        ]);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testParseInboundCallbackSkipsWhenOriginatorMissing(): void
    {
        $request = $this->buildRequest('POST', '/x', ['Status' => 5]);

        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    public function testValidateInboundCallbackUsesSameTokenScheme(): void
    {
        $this->configure();
        $valid   = $this->buildRequest('POST', '/x', ['token' => self::CALLBACK_TOKEN]);
        $invalid = $this->buildRequest('POST', '/x', ['token' => 'nope']);

        $p = $this->createProvider();
        $this->assertTrue($p->validateInboundCallback($valid));
        $this->assertFalse($p->validateInboundCallback($invalid));
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
