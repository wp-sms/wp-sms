<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\NesssolutionProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class NesssolutionProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'ness-solutions-test-api-key';
    private const SENDER  = 'MyBrand';
    private const API_URL = 'https://traffic.sales.lv/API:0.16/';

    protected function createProvider(): AbstractProvider
    {
        return new NesssolutionProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_post'],
            $GLOBALS['_test_wp_remote_post_last_url'],
            $GLOBALS['_test_wp_remote_post_last_args'],
        );
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'nesssolution' => [
                'shared' => array_merge(['api_key' => self::API_KEY], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge(['from' => self::SENDER], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+37120000000', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body, null, []);
    }

    private function mockHttpPost($responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => is_string($responseBody) ? $responseBody : json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(NesssolutionProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('nesssolution', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue($schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertTrue($schema['channels']['sms']['from']['required']);
        $this->assertTrue($schema['channels']['sms']['from']['dynamic']);
    }

    // --- Send ---

    public function testSendBuildsCorrectFormUrlencodedBody(): void
    {
        $this->configure();
        $this->mockHttpPost([['ID' => 'msg-1']]);

        $this->createProvider()->send($this->createMessage('+37120000000', 'Hi'));

        $this->assertSame(self::API_URL, $GLOBALS['_test_wp_remote_post_last_url']);

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertStringContainsString('application/x-www-form-urlencoded', $args['headers']['Content-Type']);

        parse_str($args['body'], $parsed);
        $this->assertSame(self::API_KEY, $parsed['APIKey']);
        $this->assertSame('Send', $parsed['Command']);
        $this->assertSame(self::SENDER, $parsed['Sender']);
        $this->assertSame('+37120000000', $parsed['Recipients']);
        $this->assertSame('Hi', $parsed['Content']);
    }

    public function testSendSucceedsWithJsonArrayResponse(): void
    {
        $this->configure();
        $this->mockHttpPost([['ID' => 'abc123', 'Recipient' => '+37120000000']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('abc123', $result->providerId);
    }

    /**
     * @dataProvider errorCodeProvider
     */
    public function testSendFailsOnErrorKey(string $code, string $expectedFragment): void
    {
        $this->configure();
        $this->mockHttpPost(['Error' => $code]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString($expectedFragment, $result->error);
        $this->assertSame($code, $result->meta['nesssolution_error']);
    }

    public static function errorCodeProvider(): array
    {
        return [
            'no api key'         => ['NoAPIKey', 'API Key'],
            'invalid api key'    => ['InvalidAPIKey', 'Invalid API Key'],
            'no command'         => ['NoCommand', 'Command'],
            'invalid command'    => ['InvalidCommand', 'Unknown'],
            'no sender'          => ['NoSender', 'Sender'],
            'invalid sender'     => ['InvalidSender', 'Sender'],
            'no recipients'      => ['NoRecipients', 'Recipients'],
            'invalid recipients' => ['InvalidRecipients', 'recipients'],
            'no content'         => ['NoContent', 'content'],
            'invalid content'    => ['InvalidContent', 'content'],
            'not enough credits' => ['NotEnoughCredits', 'credits'],
            'account suspended'  => ['AccountSuspended', 'suspended'],
            'internal error'     => ['InternalError', 'internal error'],
        ];
    }

    public function testSendFailsWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('API Key', $result->error);
    }

    public function testSendFailsWhenSenderMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'nesssolution' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['sms' => []],
            ],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Sender', $result->error);
    }

    // --- Credit / Test connection ---

    public function testGetCreditReturnsQuotaOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost(['Quota' => 1234]);

        $this->assertSame('1234', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnError(): void
    {
        $this->configure();
        $this->mockHttpPost(['Error' => 'InvalidAPIKey']);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testTestConnectionSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost(['Quota' => 500]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500', $result->message);
        $this->assertSame('500', $result->details['balance']);
    }

    public function testTestConnectionFailsOnInvalidApiKey(): void
    {
        $this->configure();
        $this->mockHttpPost(['Error' => 'InvalidAPIKey']);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid API Key', $result->message);
    }

    public function testTestConnectionRequiresApiKey(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback (HMAC) ---

    public function testHandleStatusCallbackVerifiesValidHmac(): void
    {
        $this->configure();

        $mssid = 'mss-1';
        $dlr = 'Delivered';
        $hmac = $this->computeHmac(self::API_KEY, $mssid, $dlr);

        $request = $this->buildRequest('POST', '/x', [
            'MSSID' => $mssid,
            'DLR'   => $dlr,
            'HMAC'  => $hmac,
        ]);

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testHandleStatusCallbackRejectsInvalidHmac(): void
    {
        $this->configure();

        $request = $this->buildRequest('POST', '/x', [
            'MSSID' => 'mss-1',
            'DLR'   => 'Delivered',
            'HMAC'  => 'wrong-hmac',
        ]);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testHandleStatusCallbackRejectsMissingFields(): void
    {
        $this->configure();

        $request = $this->buildRequest('POST', '/x', ['MSSID' => 'mss-1']);

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    /**
     * @dataProvider dlrEnumProvider
     */
    public function testHandleStatusCallbackMapsDlrEnumToStatus(
        string $dlr,
        string $expired,
        string $expectedStatus,
        bool $expectedPermanent
    ): void {
        $this->configure();

        $request = $this->buildRequest('POST', '/x', [
            'MSSID'   => 'mss-9',
            'DLR'     => $dlr,
            'Expired' => $expired,
        ]);

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('mss-9', $updates[0]->providerId);
        $this->assertSame($expectedStatus, $updates[0]->status);
        $this->assertSame($expectedPermanent, $updates[0]->permanent);
    }

    public static function dlrEnumProvider(): array
    {
        return [
            'delivered'                  => ['Delivered',   '0', 'delivered', false],
            'sent'                       => ['Sent',        '0', 'sent',      false],
            'buffered'                   => ['Buffered',    '0', 'queued',    false],
            'undelivered transient'      => ['Undelivered', '0', 'failed',    false],
            'undelivered expired'        => ['Undelivered', '1', 'failed',    true],
            'error permanent'            => ['Error',       '0', 'failed',    true],
            'unknown enum'               => ['Mystery',     '0', 'unknown',   false],
        ];
    }

    public function testParseStatusCallbackEmptyForMissingFields(): void
    {
        $request = $this->buildRequest('POST', '/x', []);
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    // --- Dynamic options ---

    public function testGetDynamicOptionsForSendersReturnsList(): void
    {
        $this->mockHttpPost([
            ['Sender' => 'BrandA', 'Status' => 'Active'],
            ['Sender' => 'BrandB', 'Status' => 'Active'],
        ]);

        $config = [
            'shared'   => ['api_key' => self::API_KEY],
            'channels' => ['sms' => []],
        ];

        $options = $this->createProvider()->getConfigOptions('from', 'sms', $config);

        $this->assertCount(2, $options);
        $this->assertSame('BrandA', $options[0]['value']);
        $this->assertSame('BrandA', $options[0]['label']);
        $this->assertSame('BrandB', $options[1]['value']);
    }

    public function testGetDynamicOptionsThrowsWithoutApiKey(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->createProvider()->getConfigOptions('from', 'sms', [
            'shared'   => [],
            'channels' => ['sms' => []],
        ]);
    }

    public function testGetDynamicOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame(
            [],
            $this->createProvider()->getConfigOptions('unknown', 'sms', ['shared' => [], 'channels' => []]),
        );
    }

    // --- Helpers ---

    private function computeHmac(string $apiKey, string $mssid, string $dlr): string
    {
        $inner = hash('sha256', $apiKey . $mssid . $dlr);
        return hash('sha256', $apiKey . $inner);
    }

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
