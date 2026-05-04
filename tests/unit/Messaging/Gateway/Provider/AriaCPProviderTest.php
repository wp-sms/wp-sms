<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\AriaCPProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class AriaCPProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(): AbstractProvider
    {
        return new AriaCPProvider();
    }

    private function configureProvider(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'payamakaria' => [
                'shared' => [
                    'username' => 'panel-user',
                    'password' => 'panel-pass',
                ],
                'channels' => [
                    'sms' => ['sender' => '50001234567890'],
                ],
            ],
        ];
    }

    private function createMessage(string $recipient = '09121234567', string $body = 'Hello', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, meta: $meta);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    // --- Send tests ---

    public function testSendReturnsSuccessWithMessageId(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Value'        => '8675309',
            'RetStatus'    => 1,
            'StrRetStatus' => 'Ok',
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('8675309', $result->providerId);
    }

    public function testSendReturnsFailedOnApiError(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Value'        => '5',
            'RetStatus'    => 0,
            'StrRetStatus' => 'اعتبار کافی نیست',
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('اعتبار کافی نیست', $result->error);
    }

    public function testSendReturnsFailedWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendPassesCorrectPayload(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Value'        => '1',
            'RetStatus'    => 1,
            'StrRetStatus' => 'Ok',
        ]);

        $provider = $this->createProvider();
        $provider->send($this->createMessage('09121234567', 'Test message'));

        $this->assertSame(
            'https://rest.payamak-panel.com/api/SendSMS/SendSMS',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('panel-user', $body['username']);
        $this->assertSame('panel-pass', $body['password']);
        $this->assertSame('09121234567', $body['to']);
        $this->assertSame('50001234567890', $body['from']);
        $this->assertSame('Test message', $body['text']);
        $this->assertSame('false', $body['isFlash']);
    }

    public function testTemplateModeSendUsesBaseServiceNumber(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Value'        => '99887',
            'RetStatus'    => 1,
            'StrRetStatus' => 'Ok',
        ]);

        $provider = $this->createProvider();
        $result = $provider->send($this->createMessage('09121234567', '', [
            'template_mode'        => true,
            'provider_template_id' => '12345',
            'template_variables'   => ['Code' => '4321', 'Name' => 'Ali'],
        ]));

        $this->assertTrue($result->success);
        $this->assertSame('99887', $result->providerId);

        $this->assertSame(
            'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertSame('panel-user', $body['username']);
        $this->assertSame('12345', $body['bodyId']);
        $this->assertSame('09121234567', $body['to']);
        $this->assertSame('4321;Ali', $body['text']);
    }

    // --- Credit tests ---

    public function testGetCreditReturnsBalance(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Value'        => '100',
            'RetStatus'    => 1,
            'StrRetStatus' => 'Ok',
        ]);

        $provider = $this->createProvider();
        $this->assertSame('100', $provider->getCredit());
    }

    public function testGetCreditReturnsNullWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $provider = $this->createProvider();
        $this->assertNull($provider->getCredit());
    }

    // --- Test connection tests ---

    public function testTestConnectionSuccess(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Value'        => '500',
            'RetStatus'    => 1,
            'StrRetStatus' => 'Ok',
        ]);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('500', $result->message);
        $this->assertSame('500', $result->details['credit']);
    }

    public function testTestConnectionFailureOnRetStatusZero(): void
    {
        $this->configureProvider();
        $this->mockHttpPost([
            'Value'        => '',
            'RetStatus'    => 0,
            'StrRetStatus' => 'Invalid credentials',
        ]);

        $provider = $this->createProvider();
        $result = $provider->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid credentials', $result->message);
    }

    // --- Tested-flag pin ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(AriaCPProvider::TESTED);
    }
}
