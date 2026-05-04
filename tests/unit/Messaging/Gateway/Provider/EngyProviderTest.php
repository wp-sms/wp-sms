<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\EngyProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class EngyProviderTest extends AbstractProviderTestCase
{
    private const API_KEY  = 'engy-test-api-key';
    private const FROM     = 'MyBrand';
    private const SEND_URL = 'https://api.engy.solutions/outbound/sms';

    protected function createProvider(): AbstractProvider
    {
        return new EngyProvider();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_post']);
        parent::tearDown();
    }

    private function configure(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'engy' => [
                'shared'   => array_merge([
                    'api_key' => self::API_KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => array_merge([
                        'from' => self::FROM,
                    ], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+491775156000', string $body = 'Hallo'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200, ?callable $capture = null): void
    {
        $payload = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];

        if ($capture) {
            $GLOBALS['_test_wp_remote_post'] = function ($url, $args) use ($payload, $capture) {
                $capture($url, $args);
                return $payload;
            };
        } else {
            $GLOBALS['_test_wp_remote_post'] = $payload;
        }
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(EngyProvider::TESTED);
    }

    public function testIdAndChannel(): void
    {
        $p = $this->createProvider();
        $this->assertSame('engy', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertTrue((bool) ($schema['shared']['api_key']['required'] ?? false));

        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertSame('string', $schema['channels']['sms']['from']['type']);
        $this->assertTrue((bool) ($schema['channels']['sms']['from']['required'] ?? false));

        $this->assertArrayHasKey('flash', $schema['channels']['sms']);
        $this->assertSame('boolean', $schema['channels']['sms']['flash']['type']);
    }

    // --- Send ---

    public function testSendPostsExpectedJson(): void
    {
        $this->configure();
        $captured = ['url' => null, 'args' => null];
        $this->mockHttpPost(
            ['statusCode' => 200, 'messageIds' => ['m-1']],
            200,
            function ($url, $args) use (&$captured) {
                $captured['url']  = $url;
                $captured['args'] = $args;
            },
        );

        $result = $this->createProvider()->send($this->createMessage('+491775156000', 'Hallo Welt'));

        $this->assertSame(self::SEND_URL, $captured['url']);
        $this->assertSame(self::API_KEY, $captured['args']['headers']['Authorization']);
        $this->assertSame('application/json', $captured['args']['headers']['Content-Type']);
        $this->assertSame('application/json', $captured['args']['headers']['Accept']);

        $payload = json_decode($captured['args']['body'], true);
        $this->assertSame(self::FROM, $payload['From']);
        $this->assertSame('491775156000', $payload['To']);
        $this->assertSame('Hallo Welt', $payload['Text']);

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
    }

    public function testSendIncludesFlashWhenChannelOptionSet(): void
    {
        $this->configure([], ['flash' => true]);
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['statusCode' => 200, 'messageIds' => ['m-1']],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage());

        $payload = json_decode($captured['args']['body'], true);
        $this->assertTrue($payload['Flash']);
    }

    public function testSendOmitsFlashWhenChannelOptionUnset(): void
    {
        $this->configure();
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['statusCode' => 200, 'messageIds' => ['m-1']],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage());

        $payload = json_decode($captured['args']['body'], true);
        $this->assertArrayNotHasKey('Flash', $payload);
    }

    public function testSendStripsLeadingPlusFromRecipient(): void
    {
        $this->configure();
        $captured = ['args' => null];
        $this->mockHttpPost(
            ['statusCode' => 200, 'messageIds' => ['m-1']],
            200,
            function ($url, $args) use (&$captured) {
                $captured['args'] = $args;
            },
        );

        $this->createProvider()->send($this->createMessage('+491775156000'));

        $payload = json_decode($captured['args']['body'], true);
        $this->assertSame('491775156000', $payload['To']);
    }

    public function testSendReturnsFailedOnHttp500(): void
    {
        $this->configure();
        $this->mockHttpPost([], 500);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('500', $result->error);
    }

    public function testSendReturnsFailedOnMalformedBody(): void
    {
        $this->configure();
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => 'not-json',
            'response' => ['code' => 200],
        ];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('unexpected', strtolower($result->error));
    }

    public function testSendReturnsFailedWhenStatusCodeMissing(): void
    {
        $this->configure();
        $this->mockHttpPost(['messageIds' => ['m-1']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('unexpected', strtolower($result->error));
    }

    public function testSendReturnsSentWithFirstMessageId(): void
    {
        $this->configure();
        $this->mockHttpPost(['statusCode' => 200, 'messageIds' => ['m-1', 'm-2']]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('m-1', $result->providerId);
        $this->assertSame(['m-1', 'm-2'], $result->meta['message_ids']);
    }

    public function testSendFailsWhenApiKeyMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $called = false;
        $GLOBALS['_test_wp_remote_post'] = function () use (&$called) {
            $called = true;
            return ['body' => '', 'response' => ['code' => 200]];
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
        $this->assertFalse($called, 'No HTTP request should be made when credentials are missing.');
    }
}
