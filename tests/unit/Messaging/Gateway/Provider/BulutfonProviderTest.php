<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\BulutfonProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class BulutfonProviderTest extends AbstractProviderTestCase
{
    private const TOKEN = 'bulutfon-test-token';
    private const TITLE = 'BASLIGIM';

    protected function createProvider(): AbstractProvider
    {
        return new BulutfonProvider();
    }

    protected function setUp(): void
    {
        parent::setUp();
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_get_last_url'],
            $GLOBALS['_test_wp_remote_get_last_args'],
        );
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulutfon' => [
                'shared' => array_merge([
                    'access_token' => self::TOKEN,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['title' => self::TITLE], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '905311234567', string $body = 'Merhaba'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    /** @param string|callable $body */
    private function mockHttpGet($body, int $statusCode = 200): void
    {
        if (is_callable($body)) {
            $GLOBALS['_test_wp_remote_get'] = $body;
            return;
        }
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];
    }

    private function capturePostUrlAndArgs(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = function (string $url, array $args) use ($body, $statusCode) {
            $GLOBALS['_test_wp_remote_post_last_url']  = $url;
            $GLOBALS['_test_wp_remote_post_last_args'] = $args;
            return ['body' => $body, 'response' => ['code' => $statusCode]];
        };
    }

    private function captureGetUrl(string $body, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use ($body, $statusCode) {
            $GLOBALS['_test_wp_remote_get_last_url']  = $url;
            $GLOBALS['_test_wp_remote_get_last_args'] = $args;
            return ['body' => $body, 'response' => ['code' => $statusCode]];
        };
    }

    // --- Identity / schema ---

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('bulutfon', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(BulutfonProvider::TESTED);
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('access_token', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['access_token']['type']);
        $this->assertTrue($schema['shared']['access_token']['required']);

        $this->assertArrayHasKey('title', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['title']['required']);
        $this->assertTrue($schema['channels']['sms']['title']['dynamic']);
    }

    // --- Send ---

    public function testSendSucceeds(): void
    {
        $this->configure();
        $this->mockHttpPost(json_encode([
            'message' => [
                'id'         => 1234,
                'recipients' => [
                    ['number' => '905311234567', 'state' => 'WAITING'],
                ],
            ],
        ]), 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('1234', $result->providerId);
    }

    public function testSendFailsWithoutAccessToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsWithoutTitle(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'bulutfon' => [
                'shared'   => ['access_token' => self::TOKEN],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Title', $result->error);
    }

    public function testSendUrlAndBodyShape(): void
    {
        $this->configure();
        $this->capturePostUrlAndArgs(json_encode([
            'message' => ['id' => 9, 'recipients' => []],
        ]), 200);

        $this->createProvider()->send($this->createMessage('905311234567', 'Test body'));

        $url = $GLOBALS['_test_wp_remote_post_last_url'];
        $this->assertStringStartsWith('https://api.bulutfon.com/messages.json', $url);
        $this->assertStringContainsString('access_token=' . self::TOKEN, $url);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $body);
        $this->assertSame(self::TITLE, $body['title']);
        $this->assertSame('905311234567', $body['receivers']);
        $this->assertSame('Test body', $body['content']);
    }

    public function testSendHandles401(): void
    {
        $this->configure();
        $this->mockHttpPost('{"error":"invalid_token"}', 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendSurfacesNon2xxBodyAsError(): void
    {
        $this->configure();
        $this->mockHttpPost('Title is not approved', 422);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Title is not approved', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsSmsCredit(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode(['credit' => ['sms_credit' => '500']]), 200);

        $this->assertSame('500', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpGet('{"error":"invalid_token"}', 401);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWithoutToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditQueriesMeEndpointWithToken(): void
    {
        $this->configure();
        $this->captureGetUrl(json_encode(['credit' => ['sms_credit' => '42']]), 200);

        $this->createProvider()->getCredit();

        $url = $GLOBALS['_test_wp_remote_get_last_url'];
        $this->assertStringStartsWith('https://api.bulutfon.com/me.json', $url);
        $this->assertStringContainsString('access_token=' . self::TOKEN, $url);
    }

    // --- Test connection ---

    public function testTestConnectionOk(): void
    {
        $this->configure();
        $this->mockHttpGet(json_encode([
            'message_titles' => [
                ['name' => 'BASLIGIM', 'state' => 'CONFIRMED'],
                ['name' => 'OTHER',    'state' => 'CONFIRMED'],
            ],
        ]), 200);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('2', $result->message);
    }

    public function testTestConnectionFailsOn401(): void
    {
        $this->configure();
        $this->mockHttpGet('{"error":"invalid_token"}', 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid access token', $result->message);
    }

    public function testTestConnectionRequiresToken(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    public function testTestConnectionUsesMessageTitlesEndpoint(): void
    {
        $this->configure();
        $this->captureGetUrl(json_encode(['message_titles' => []]), 200);

        $this->createProvider()->testConnection();

        $url = $GLOBALS['_test_wp_remote_get_last_url'];
        $this->assertStringStartsWith('https://api.bulutfon.com/message-titles.json', $url);
        $this->assertStringContainsString('access_token=' . self::TOKEN, $url);
    }

    // --- Dynamic options ---

    public function testGetConfigOptionsReturnsConfirmedTitlesOnly(): void
    {
        $this->mockHttpGet(json_encode([
            'message_titles' => [
                ['name' => 'APPROVED1', 'state' => 'CONFIRMED'],
                ['name' => 'PENDING',   'state' => 'DRAFT'],
                ['name' => 'BLOCKED',   'state' => 'REJECTED'],
                ['name' => 'APPROVED2', 'state' => 'CONFIRMED'],
            ],
        ]), 200);

        $options = $this->createProvider()->getConfigOptions('title', 'sms', [
            'shared'   => ['access_token' => self::TOKEN],
            'channels' => [],
        ]);

        $this->assertSame([
            ['value' => 'APPROVED1', 'label' => 'APPROVED1'],
            ['value' => 'APPROVED2', 'label' => 'APPROVED2'],
        ], $options);
    }

    public function testGetConfigOptionsThrowsOn401(): void
    {
        $this->mockHttpGet('{"error":"invalid_token"}', 401);

        $this->expectException(\RuntimeException::class);

        $this->createProvider()->getConfigOptions('title', 'sms', [
            'shared'   => ['access_token' => 'bad'],
            'channels' => [],
        ]);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('unknown', 'sms', []));
        $this->assertSame([], $this->createProvider()->getConfigOptions('title', 'whatsapp', []));
    }

    public function testGetConfigOptionsReturnsEmptyWithoutToken(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('title', 'sms', [
            'shared'   => [],
            'channels' => [],
        ]));
    }
}
