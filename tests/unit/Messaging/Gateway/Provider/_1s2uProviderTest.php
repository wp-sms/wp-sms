<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\_1s2uProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class _1s2uProviderTest extends AbstractProviderTestCase
{
    private const USERNAME       = '1s2u-user';
    private const PASSWORD       = 'super-secret';
    private const SENDER_ID      = 'WSMSAB';
    private const WEBHOOK_TOKEN  = 'webhook-secret-xyz';

    protected function createProvider(): AbstractProvider
    {
        return new _1s2uProvider();
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

    private function configure(array $extraShared = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            '1s2u' => [
                'shared' => array_merge([
                    'username' => self::USERNAME,
                    'password' => self::PASSWORD,
                ], $extraShared),
                'channels' => [
                    'sms' => ['from' => self::SENDER_ID],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '491701234567', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    private function mockHttpPost(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsTrue(): void
    {
        $this->assertTrue(_1s2uProvider::TESTED);
    }

    public function testGetIdReturnsSlug(): void
    {
        $this->assertSame('1s2u', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    // --- doSend ---

    public function testDoSendSuccessReturnsQueuedWithProviderId(): void
    {
        $this->configure();
        $this->mockHttpPost('OK: abc-123-uuid');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('abc-123-uuid', $result->providerId);
        $this->assertSame('https://api.1s2u.io/bulksms', $GLOBALS['_test_wp_remote_post_last_url']);
    }

    public function testDoSendErrorReturnsFailedWithMappedMessage(): void
    {
        $this->configure();
        $this->mockHttpPost('ERROR: 0030');

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('Invalid Sender ID', $result->error);
        $this->assertSame('0030', $result->meta['1s2u_code']);
        // 0030 (invalid sender ID) is permanent — not retryable.
        $this->assertFalse($result->retryable);
    }

    public function testDoSendStripsPlusAndLeadingZeros(): void
    {
        $this->configure();

        $cases = [
            '+491701234567'      => '491701234567',
            '0049-170-12345 67'  => '491701234567',
            '491701234567'       => '491701234567',
            '00 49 170 12345 67' => '491701234567',
        ];

        foreach ($cases as $input => $expected) {
            $this->mockHttpPost('OK:msg-' . md5($input));
            $this->createProvider()->send($this->createMessage($input, 'Hi'));

            $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
            $this->assertSame($expected, $body['mno'], "wrong normalization for {$input}");
        }
    }

    public function testDoSendDetectsUnicode(): void
    {
        $this->configure();
        $this->mockHttpPost('OK:msg-1');

        $this->createProvider()->send($this->createMessage('491701234567', 'Hello 🌍'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(1, $body['mt']);
    }

    public function testDoSendUsesGsmModeForAsciiBody(): void
    {
        $this->configure();
        $this->mockHttpPost('OK:msg-1');

        $this->createProvider()->send($this->createMessage('491701234567', 'plain ascii'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(0, $body['mt']);
    }

    public function testDoSendUrlEncodesMessage(): void
    {
        $this->configure();
        $this->mockHttpPost('OK:msg-1');

        $this->createProvider()->send($this->createMessage('491701234567', 'a b&c=1'));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(rawurlencode('a b&c=1'), $body['msg']);
    }

    public function testDoSendSendsLowercaseSidPerDocs(): void
    {
        $this->configure();
        $this->mockHttpPost('OK:msg-1');

        $this->createProvider()->send($this->createMessage());

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(self::SENDER_ID, $body['sid']);
        $this->assertArrayNotHasKey('Sid', $body);
        $this->assertSame(self::USERNAME, $body['username']);
        $this->assertSame(self::PASSWORD, $body['password']);
    }

    public function testDoSendFlashMetaSetsFlFlag(): void
    {
        $this->configure();
        $this->mockHttpPost('OK:msg-1');

        $this->createProvider()->send($this->createMessage('491701234567', 'Hi', ['flash' => true]));

        $this->assertSame(1, $GLOBALS['_test_wp_remote_post_last_args']['body']['fl']);
    }

    public function testDoSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    // --- getCredit ---

    public function testGetCreditReturnsTrimmedBalance(): void
    {
        $this->configure();
        $this->mockHttpGet("  493.20\n");

        $this->assertSame('493.20', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnAuthError(): void
    {
        $this->configure();
        $this->mockHttpGet('00');

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditUsesLowercaseUserPassPerDocs(): void
    {
        $this->configure();
        $captured = ['url' => null];
        $GLOBALS['_test_wp_remote_get'] = function ($url) use (&$captured) {
            $captured['url'] = $url;
            return ['body' => '100', 'response' => ['code' => 200]];
        };

        $this->createProvider()->getCredit();

        $this->assertNotNull($captured['url']);
        $parsed = parse_url($captured['url']);
        parse_str($parsed['query'] ?? '', $query);
        $this->assertSame(self::USERNAME, $query['user']);
        $this->assertSame(self::PASSWORD, $query['pass']);
        $this->assertArrayNotHasKey('USER', $query);
        $this->assertArrayNotHasKey('PASS', $query);
    }

    // --- testConnection ---

    public function testTestConnectionFailsOnBadCredentials(): void
    {
        $this->configure();
        $this->mockHttpGet('00');

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionSucceedsWithBalance(): void
    {
        $this->configure();
        $this->mockHttpGet('493.20');

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('493.20', $result->message);
        $this->assertSame('493.20', $result->details['balance']);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- SupportsStatusCallback ---

    public function testValidateStatusCallbackRejectsWhenNoTokenConfigured(): void
    {
        $this->configure();
        $request = $this->buildRequest(['token' => 'whatever']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsMissingToken(): void
    {
        $this->configure(['webhook_token' => self::WEBHOOK_TOKEN]);
        $request = $this->buildRequest([]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRequiresMatchingToken(): void
    {
        $this->configure(['webhook_token' => self::WEBHOOK_TOKEN]);
        $request = $this->buildRequest(['token' => 'wrong']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configure(['webhook_token' => self::WEBHOOK_TOKEN]);
        $request = $this->buildRequest(['token' => self::WEBHOOK_TOKEN]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsResponseField(): void
    {
        $cases = [
            'DELIVRD' => ['delivered', false],
            'UNDELIV' => ['failed', true],
            'EXPIRED' => ['failed', true],
        ];

        foreach ($cases as $raw => [$expectedStatus, $expectedPermanent]) {
            $request = $this->buildRequest([
                'sms_id'   => 'msg-1',
                'response' => $raw,
                'msisdn'   => '491701234567',
            ]);

            $updates = $this->createProvider()->parseStatusCallback($request);

            $this->assertCount(1, $updates, "no update for {$raw}");
            $this->assertSame('msg-1', $updates[0]->providerId);
            $this->assertSame($expectedStatus, $updates[0]->status);
            $this->assertSame($expectedPermanent, $updates[0]->permanent, "permanent flag wrong for {$raw}");
        }
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest([]);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Helpers ---

    private function buildRequest(array $params, array $headers = []): \WP_REST_Request
    {
        return new class('POST', '/x', $params, $headers) extends \WP_REST_Request {
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
