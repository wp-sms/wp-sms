<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\SmsgatewayatProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class SmsgatewayatProviderTest extends AbstractProviderTestCase
{
    private const USERNAME  = 'sms-test-user';
    private const VALIDPASS = 'sms-test-validpass';
    private const SENDER    = 'MyBrand';
    private const SEND_URL  = 'https://www.sms-gateway.at/sms/sendsms.php';

    protected function createProvider(): AbstractProvider
    {
        return new SmsgatewayatProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_get']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'smsgatewayat' => [
                'shared'   => array_merge(
                    ['username' => self::USERNAME, 'validpass' => self::VALIDPASS],
                    $sharedOverrides,
                ),
                'channels' => [
                    'sms' => $smsOverrides,
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+436641111111', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body, null, []);
    }

    private function mockHttpGet(string $body, int $statusCode = 200, ?string &$capturedUrl = null): void
    {
        $payload = [
            'body'     => $body,
            'response' => ['code' => $statusCode],
        ];

        $GLOBALS['_test_wp_remote_get'] = function ($url, $args) use ($payload, &$capturedUrl) {
            $capturedUrl = $url;
            return $payload;
        };
    }

    private function parseQuery(string $url): array
    {
        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $query);
        return $query;
    }

    private function xmlOk(string $resultValue = 'OK', string $errormessage = ''): string
    {
        return sprintf(
            '<?xml version="1.0"?><response><result>%s</result><errormessage>%s</errormessage></response>',
            $resultValue,
            $errormessage,
        );
    }

    private function xmlError(string $code, string $errormessage = ''): string
    {
        return sprintf(
            '<?xml version="1.0"?><response><result>ERROR:</result><errorcode>%s</errorcode><errormessage>%s</errormessage></response>',
            $code,
            $errormessage,
        );
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(SmsgatewayatProvider::TESTED);
    }

    public function testIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('smsgatewayat', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertSame('string', $schema['shared']['username']['type']);
        $this->assertTrue((bool) ($schema['shared']['username']['required'] ?? false));

        $this->assertArrayHasKey('validpass', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['validpass']['type']);
        $this->assertTrue((bool) ($schema['shared']['validpass']['required'] ?? false));

        $this->assertArrayHasKey('absender', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['absender']['type']);
        $this->assertEmpty($schema['channels']['sms']['absender']['required'] ?? false);

        $this->assertArrayHasKey('flash', $schema['channels']['sms']);
        $this->assertSame('boolean', $schema['channels']['sms']['flash']['type']);
    }

    // --- Send ---

    public function testFailsWithoutCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $GLOBALS['_test_wp_remote_get'] = function () {
            throw new \RuntimeException('wp_remote_get must not be called when unconfigured');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('username', $result->error);
    }

    public function testSendBuildsCorrectUrlWithBracketedNumberParam(): void
    {
        $this->configure();
        $url = null;
        $this->mockHttpGet($this->xmlOk(), 200, $url);

        $this->createProvider()->send($this->createMessage('+436641111111', 'Hi'));

        $this->assertNotNull($url);
        $this->assertStringStartsWith(self::SEND_URL, $url);

        // Bracketed number param uses literal 'number[]', not 'number[0]='
        $this->assertStringContainsString('number%5B%5D=%2B436641111111', $url);

        $query = $this->parseQuery($url);
        $this->assertSame(self::USERNAME, $query['username']);
        $this->assertSame(self::VALIDPASS, $query['validpass']);
        $this->assertSame('Hi', $query['message']);
        $this->assertSame('utf8', $query['encoding']);
        $this->assertArrayNotHasKey('absender', $query);
        $this->assertArrayNotHasKey('flash', $query);
    }

    public function testSendIncludesSenderAndFlashWhenConfigured(): void
    {
        $this->configure([], ['absender' => self::SENDER, 'flash' => true]);
        $url = null;
        $this->mockHttpGet($this->xmlOk(), 200, $url);

        $this->createProvider()->send($this->createMessage());

        $query = $this->parseQuery($url);
        $this->assertSame(self::SENDER, $query['absender']);
        $this->assertSame('1', $query['flash']);
    }

    public function testSendSucceedsOnBareOk(): void
    {
        $this->configure();
        $this->mockHttpGet($this->xmlOk('OK'));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertNull($result->providerId);
    }

    public function testSendSucceedsOnOkColon(): void
    {
        // SDK treats 'OK:' as success too, with no extracted ID.
        $this->configure();
        $this->mockHttpGet($this->xmlOk('OK:', 'queued'));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
    }

    /**
     * @dataProvider errorCodeProvider
     */
    public function testSendFailsOnErrorCode(string $code, string $expectedFragment): void
    {
        $this->configure();
        $this->mockHttpGet($this->xmlError($code));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString($expectedFragment, $result->error);
    }

    public static function errorCodeProvider(): array
    {
        return [
            'message cannot be sent'          => ['100', 'cannot be sent'],
            'wrong username'                  => ['108', 'username'],
            'wrong password'                  => ['109', 'password'],
            'no source number / sender'       => ['110', 'sender'],
            'unsupported destination number'  => ['111', 'destination'],
            'message empty'                   => ['113', 'empty'],
            'message length invalid'          => ['114', 'length'],
            'credit consumed'                 => ['116', 'credit'],
            'unsupported destination address' => ['200', 'destination'],
            'unknown error'                   => ['999', 'Unknown'],
            'unmapped code falls through'     => ['12345', '12345'],
        ];
    }

    public function testSendFailsOnNon2xx(): void
    {
        $this->configure();
        $this->mockHttpGet('Internal Server Error', 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    public function testSendFailsOnUnexpectedResponse(): void
    {
        $this->configure();
        $this->mockHttpGet('this is not xml or known plain text', 200);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
    }
}
