<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\UpsideWirelessProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class UpsideWirelessProviderTest extends AbstractProviderTestCase
{
    private const TOKEN     = '11111111-2222-3333-4444-555555555555';
    private const SIGNATURE = 'sig-secret';

    protected function createProvider(): AbstractProvider
    {
        return new UpsideWirelessProvider();
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

    private function configure(array $sharedOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'upsidewireless' => [
                'shared' => array_merge([
                    'token'     => self::TOKEN,
                    'signature' => self::SIGNATURE,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => [],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '+16043434343', string $body = 'Hello'): Message
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

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(UpsideWirelessProvider::TESTED);
    }

    public function testGetIdAndChannels(): void
    {
        $p = $this->createProvider();
        $this->assertSame('upsidewireless', $p->getId());
        $this->assertSame(['sms'], $p->getSupportedChannels());
    }

    public function testSendSuccessReturnsTrackingId(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'HasException' => false,
            'SMSMessage'   => [
                'Status'     => 'QUEUED',
                'TrackingId' => 'abc-123',
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('abc-123', $result->providerId);
    }

    public function testSendPostsTokenInPathAndStripsLeadingPlus(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'HasException' => false,
            'SMSMessage'   => ['Status' => 'QUEUED', 'TrackingId' => 't1'],
        ]);

        $this->createProvider()->send($this->createMessage('+16041234567', 'Hi'));

        $url = $GLOBALS['_test_wp_remote_post_last_url'];
        $this->assertStringContainsString('/RESTv1/' . rawurlencode(self::TOKEN) . '/Message', $url);
        $this->assertStringContainsString('responsetype=JSON', $url);

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame(self::SIGNATURE, $body['signature']);
        $this->assertSame('sms', $body['type']);
        $this->assertSame('16041234567', $body['recipient']);
        $this->assertSame('Hi', $body['message']);
        $this->assertSame('16', $body['encoding']);
    }

    public function testSendFailsWhenCredentialsMissing(): void
    {
        $this->configure(['signature' => '']);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailsOnApiException(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'HasException' => true,
            'ErrorCode'    => 101,
            'ErrorMessage' => 'Unauthorized',
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unauthorized', $result->error);
        $this->assertStringContainsString('101', $result->error);
    }

    public function testSendFailsOnRejection(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'HasException' => false,
            'SMSMessage'   => [
                'Status'       => 'REJECTED',
                'RejectReason' => 'Number blocked',
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Number blocked', $result->error);
    }

    public function testTestConnectionOkOnPassed(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'HasException' => false,
            'SMSMessage'   => ['Status' => 'PASSED'],
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Upside Wireless', $result->message);
    }

    public function testTestConnectionFailsOn401(): void
    {
        $this->configure();
        $this->mockHttpPost(['error' => 'unauthorized'], 401);

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
}
