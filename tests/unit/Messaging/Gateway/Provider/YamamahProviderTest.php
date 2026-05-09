<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\YamamahProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class YamamahProviderTest extends AbstractProviderTestCase
{
    private const USERNAME = '966500000000';
    private const PASSWORD = 'panel-pass';
    private const TAGNAME = 'yamamah1';
    private const RECIPIENT = '966512345678';
    private const SEND_URL = 'http://api.yamamah.com/SendSMS';
    private const MESSAGE_ID = '1d7d8d99-2da4-478a-8391-6783f467f479';

    protected function createProvider(): AbstractProvider
    {
        return new YamamahProvider();
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
            'yamamah' => [
                'shared'   => array_merge(
                    ['username' => self::USERNAME, 'password' => self::PASSWORD],
                    $sharedOverrides,
                ),
                'channels' => [
                    'sms' => array_merge(['sender' => self::TAGNAME], $smsOverrides),
                ],
            ],
        ];
    }

    private function createMessage(string $body = 'Hello'): Message
    {
        return new Message('sms', self::RECIPIENT, $body);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Identity ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(YamamahProvider::TESTED);
    }

    // --- Send ---

    public function testSendReturnsMessageIdOnSuccess(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'InvalidMSISDN'     => null,
            'MessageID'         => self::MESSAGE_ID,
            'Status'            => 1,
            'StatusDescription' => 'Success',
        ]);

        $result = $this->createProvider()->send($this->createMessage('Hi'));

        $this->assertTrue($result->success);
        $this->assertSame(self::MESSAGE_ID, $result->providerId);
    }

    public function testSendBuildsCorrectRequestBody(): void
    {
        $this->configure();
        $captured = null;
        $GLOBALS['_test_wp_remote_post'] = function ($url, $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode(['MessageID' => self::MESSAGE_ID, 'Status' => 1]),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->send($this->createMessage('Salam'));

        $this->assertNotNull($captured);
        $this->assertSame(self::SEND_URL, $captured['url']);
        $this->assertSame('application/json', $captured['args']['headers']['Content-Type']);

        $body = json_decode($captured['args']['body'], true);
        $this->assertSame(self::USERNAME, $body['Username']);
        $this->assertSame(self::PASSWORD, $body['Password']);
        $this->assertSame(self::TAGNAME, $body['Tagname']);
        $this->assertSame(self::RECIPIENT, $body['RecepientNumber']);
        $this->assertSame('Salam', $body['Message']);
        $this->assertSame('', $body['VariableList']);
        $this->assertSame('', $body['ReplacementList']);
        $this->assertSame(0, $body['SendDateTime']);
        $this->assertFalse($body['EnableDR']);
    }

    public function testSendMapsAuthFailure(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'MessageID'         => null,
            'Status'            => 10,
            'StatusDescription' => 'Invalid Username/Password',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
        $this->assertStringContainsString('credentials', strtolower($result->error));
    }

    public function testSendMapsInsufficientFund(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'MessageID'         => null,
            'Status'            => 40,
            'StatusDescription' => 'Insufficient Fund',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credit', strtolower($result->error));
    }

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'yamamah' => [
                'shared'   => [],
                'channels' => ['sms' => ['sender' => self::TAGNAME]],
            ],
        ];
        $GLOBALS['_test_wp_remote_post'] = function () {
            throw new \RuntimeException('wp_remote_post must not be called when unconfigured');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsWhenSenderMissing(): void
    {
        $this->configure([], ['sender' => '']);
        $GLOBALS['_test_wp_remote_post'] = function () {
            throw new \RuntimeException('wp_remote_post must not be called when sender missing');
        };

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sender', strtolower($result->error));
    }

    // --- Credit ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configure();
        $captured = null;
        $GLOBALS['_test_wp_remote_post'] = function ($url, $args) use (&$captured) {
            $captured = ['url' => $url, 'args' => $args];
            return [
                'body'     => json_encode([
                    'GetCreditPostResult' => [
                        'Credit'      => 1086,
                        'Description' => 'Success',
                        'Status'      => 1,
                    ],
                ]),
                'response' => ['code' => 200],
            ];
        };

        $credit = $this->createProvider()->getCredit();

        $this->assertSame('1086', $credit);
        $this->assertNotNull($captured);
        $this->assertStringContainsString(
            '/GetCredit/' . self::USERNAME . '/' . self::PASSWORD,
            $captured['url'],
        );
    }

    public function testGetCreditReturnsNullOnAuthFailure(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'GetCreditPostResult' => [
                'Credit'      => 0,
                'Description' => 'Invalid Username/Password',
                'Status'      => 10,
            ],
        ]);

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithCredit(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'GetCreditPostResult' => [
                'Credit'      => 1086,
                'Description' => 'Success',
                'Status'      => 1,
            ],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('1086', $result->message);
    }

    public function testTestConnectionFailsOnInvalidCredentials(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'GetCreditPostResult' => [
                'Credit'      => 0,
                'Description' => 'Invalid Username/Password',
                'Status'      => 10,
            ],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('credentials', strtolower($result->message));
    }
}
