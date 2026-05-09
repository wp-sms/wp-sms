<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\EsmsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class EsmsProviderTest extends AbstractProviderTestCase
{
    private const API_BASE = 'https://rest.esms.vn/MainService.svc/json';
    private const API_KEY = 'esms-api-key-1234';
    private const SECRET_KEY = 'esms-secret-key-5678';
    private const BRANDNAME = 'WSMS';
    private const VIBER_BRANDNAME = 'WSMS-Viber';

    protected function createProvider(): AbstractProvider
    {
        return new EsmsProvider();
    }

    private function configure(array $sharedOverrides = [], array $channelOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'esms' => [
                'shared'   => array_merge([
                    'api_key'    => self::API_KEY,
                    'secret_key' => self::SECRET_KEY,
                ], $sharedOverrides),
                'channels' => array_merge([
                    'sms'   => [
                        'brandname'  => self::BRANDNAME,
                        'sms_type'   => '2',
                        'is_unicode' => false,
                        'sandbox'    => false,
                    ],
                    'viber' => [
                        'brandname' => self::VIBER_BRANDNAME,
                        'sandbox'   => false,
                    ],
                ], $channelOverrides),
            ],
        ];
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function lastRequestBody(): array
    {
        return json_decode($GLOBALS['_test_wp_remote_post_last_args']['body'], true);
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(EsmsProvider::TESTED);
    }

    public function testGetIdReturnsExpectedSlug(): void
    {
        $this->assertSame('esms', $this->createProvider()->getId());
    }

    public function testGetSupportedChannelsReturnsSmsAndViber(): void
    {
        $this->assertSame(['sms', 'viber'], $this->createProvider()->getSupportedChannels());
    }

    public function testGetConfigSchemaShape(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertTrue((bool) $schema['shared']['api_key']['required']);

        $this->assertArrayHasKey('secret_key', $schema['shared']);
        $this->assertSame('secret', $schema['shared']['secret_key']['type']);
        $this->assertTrue((bool) $schema['shared']['secret_key']['required']);

        $this->assertArrayHasKey('brandname', $schema['channels']['sms']);
        $this->assertTrue((bool) $schema['channels']['sms']['brandname']['required']);
        $this->assertTrue((bool) $schema['channels']['sms']['brandname']['dynamic']);

        $this->assertArrayHasKey('sms_type', $schema['channels']['sms']);
        $this->assertSame('select', $schema['channels']['sms']['sms_type']['type']);
        $smsTypeOptions = $schema['channels']['sms']['sms_type']['options'];
        $this->assertSame(['value' => '2', 'label' => __('Brandname (customer care)', 'wp-sms')], $smsTypeOptions[0]);
        $this->assertSame('8', $smsTypeOptions[1]['value']);

        $this->assertArrayHasKey('is_unicode', $schema['channels']['sms']);
        $this->assertSame('boolean', $schema['channels']['sms']['is_unicode']['type']);

        $this->assertArrayHasKey('sandbox', $schema['channels']['sms']);

        $this->assertArrayHasKey('brandname', $schema['channels']['viber']);
        $this->assertTrue((bool) $schema['channels']['viber']['brandname']['required']);
        $this->assertTrue((bool) $schema['channels']['viber']['brandname']['dynamic']);

        $this->assertArrayHasKey('sandbox', $schema['channels']['viber']);
    }

    public function testIsConfiguredRequiresBothApiKeyAndSecret(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'esms' => [
                'shared'   => ['api_key' => self::API_KEY],
                'channels' => ['sms' => ['brandname' => self::BRANDNAME, 'sms_type' => '2']],
            ],
        ];

        $this->assertFalse($this->createProvider()->isConfigured());
    }

    public function testIsConfiguredForChannelRequiresBrandname(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'esms' => [
                'shared'   => [
                    'api_key'    => self::API_KEY,
                    'secret_key' => self::SECRET_KEY,
                ],
                'channels' => [
                    'sms'   => ['sms_type' => '2'],
                    'viber' => [],
                ],
            ],
        ];

        $p = $this->createProvider();
        $this->assertFalse($p->isConfiguredForChannel('sms'));
        $this->assertFalse($p->isConfiguredForChannel('viber'));
    }

    public function testIsConfiguredForChannelTrueWhenBrandnameSet(): void
    {
        $this->configure();
        $p = $this->createProvider();
        $this->assertTrue($p->isConfiguredForChannel('sms'));
        $this->assertTrue($p->isConfiguredForChannel('viber'));
    }

    // --- SMS send ---

    public function testSendSmsBuildsCorrectJsonBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['CodeResult' => '100', 'CountRegenerate' => 0, 'SMSID' => 'sms-1']);

        $this->createProvider()->send(new Message('sms', '+84901234567', 'Hello there'));

        $this->assertSame(
            self::API_BASE . '/SendMultipleMessage_V4_post_json/',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame('application/json', $args['headers']['Content-Type']);

        $body = $this->lastRequestBody();
        $this->assertSame(self::API_KEY, $body['ApiKey']);
        $this->assertSame(self::SECRET_KEY, $body['SecretKey']);
        $this->assertSame('+84901234567', $body['Phone']);
        $this->assertSame('Hello there', $body['Content']);
        $this->assertSame(0, $body['IsUnicode']);
        $this->assertSame(self::BRANDNAME, $body['Brandname']);
        $this->assertSame('2', $body['SmsType']);
        $this->assertSame(0, $body['Sandbox']);
    }

    public function testSendSmsUnicodeFlagDrivenByConfig(): void
    {
        $this->configure(channelOverrides: [
            'sms' => [
                'brandname'  => self::BRANDNAME,
                'sms_type'   => '2',
                'is_unicode' => true,
                'sandbox'    => false,
            ],
        ]);
        $this->mockHttpPost(['CodeResult' => '100', 'SMSID' => 'sms-1']);

        $this->createProvider()->send(new Message('sms', '+84901234567', 'Xin chào'));

        $this->assertSame(1, $this->lastRequestBody()['IsUnicode']);
    }

    public function testSendSmsSandboxFlagDrivenByConfig(): void
    {
        $this->configure(channelOverrides: [
            'sms' => [
                'brandname'  => self::BRANDNAME,
                'sms_type'   => '2',
                'is_unicode' => false,
                'sandbox'    => true,
            ],
        ]);
        $this->mockHttpPost(['CodeResult' => '100', 'SMSID' => 'sms-1']);

        $this->createProvider()->send(new Message('sms', '+84901234567', 'hi'));

        $this->assertSame(1, $this->lastRequestBody()['Sandbox']);
    }

    public function testSendSmsHonoursSmsType8(): void
    {
        $this->configure(channelOverrides: [
            'sms' => [
                'brandname'  => self::BRANDNAME,
                'sms_type'   => '8',
                'is_unicode' => false,
                'sandbox'    => false,
            ],
        ]);
        $this->mockHttpPost(['CodeResult' => '100', 'SMSID' => 'sms-1']);

        $this->createProvider()->send(new Message('sms', '+84901234567', 'hi'));

        $this->assertSame('8', $this->lastRequestBody()['SmsType']);
    }

    // --- Viber send ---

    public function testSendViberBuildsCorrectJsonBody(): void
    {
        $this->configure();
        $this->mockHttpPost(['CodeResult' => '100', 'SMSID' => 'viber-1']);

        $this->createProvider()->send(new Message('viber', '+84901234567', 'Viber hi'));

        $this->assertSame(
            self::API_BASE . '/Send_Multiple_Sms_OTT/',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );

        $body = $this->lastRequestBody();
        $this->assertSame(self::API_KEY, $body['ApiKey']);
        $this->assertSame(self::SECRET_KEY, $body['SecretKey']);
        $this->assertSame(self::VIBER_BRANDNAME, $body['Brandname']);
        $this->assertSame(23, $body['SmsType']);
        $this->assertSame(['+84901234567'], $body['Phones']);
        $this->assertSame('Viber hi', $body['Content']);
        $this->assertSame(0, $body['Sandbox']);
        $this->assertArrayNotHasKey('OttImgUrl', $body);
        $this->assertArrayNotHasKey('OttUrl', $body);
        $this->assertArrayNotHasKey('OTTLabel', $body);
    }

    public function testSendViberWithImageAndButton(): void
    {
        $this->configure();
        $this->mockHttpPost(['CodeResult' => '100', 'SMSID' => 'viber-2']);

        $message = new Message('viber', '+84901234567', 'rich', null, [
            'image_url'    => 'https://cdn.example.com/img.jpg',
            'button_url'   => 'https://example.com/buy',
            'button_label' => 'Buy now',
        ]);
        $this->createProvider()->send($message);

        $body = $this->lastRequestBody();
        $this->assertSame('https://cdn.example.com/img.jpg', $body['OttImgUrl']);
        $this->assertSame('https://example.com/buy', $body['OttUrl']);
        $this->assertSame('Buy now', $body['OTTLabel']);
    }

    // --- Result parsing (both channels) ---

    public function testSendQueuedWhenCodeResult100(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'CodeResult'      => '100',
            'CountRegenerate' => 0,
            'SMSID'           => '24342680',
        ]);

        $sms = $this->createProvider()->send(new Message('sms', '+84901234567', 'hi'));
        $this->assertTrue($sms->success);
        $this->assertSame('queued', $sms->status);
        $this->assertSame('24342680', $sms->providerId);

        $this->mockHttpPost([
            'CodeResult'      => '100',
            'CountRegenerate' => 0,
            'SMSID'           => 'viber-99',
        ]);
        $viber = $this->createProvider()->send(new Message('viber', '+84901234567', 'hi'));
        $this->assertTrue($viber->success);
        $this->assertSame('viber-99', $viber->providerId);
    }

    public function testSendFailedWhenCodeResultNon100(): void
    {
        $this->configure();
        $this->mockHttpPost([
            'CodeResult'   => '101',
            'ErrorMessage' => 'Authorize Failed',
        ]);

        $result = $this->createProvider()->send(new Message('sms', '+84901234567', 'hi'));

        $this->assertFalse($result->success);
        $this->assertSame('Authorize Failed', $result->error);
        $this->assertSame('101', $result->meta['esms_code']);
    }

    public function testSendFailedWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send(new Message('sms', '+84901234567', 'hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendFailedWhenBrandnameMissing(): void
    {
        $this->configure(channelOverrides: ['sms' => ['sms_type' => '2']]);

        $result = $this->createProvider()->send(new Message('sms', '+84901234567', 'hi'));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Brandname', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsFormattedBalance(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'Balance'      => '150000',
            'CodeResponse' => '00',
            'UserID'       => 12345,
        ]);

        $this->assertSame('150000 VND', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullOnAuthFailure(): void
    {
        $this->configure();
        $this->mockHttpGet(['CodeResponse' => '01']);

        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $this->assertNull($this->createProvider()->getCredit());
    }

    // --- Test connection ---

    public function testTestConnectionOkOnValidBalance(): void
    {
        $this->configure();
        $this->mockHttpGet([
            'Balance'      => '150000',
            'CodeResponse' => '00',
            'UserID'       => 12345,
        ]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('150000', $result->message);
        $this->assertStringContainsString('VND', $result->message);
        $this->assertSame('150000', $result->details['balance']);
    }

    public function testTestConnectionFailsOn401Equivalent(): void
    {
        $this->configure();
        $this->mockHttpGet(['CodeResponse' => '01']);

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

    // --- Dynamic options ---

    public function testGetConfigOptionsForBrandnameFetchesList(): void
    {
        $this->mockHttpGet([
            'CodeResponse'  => '00',
            'ListBrandName' => [
                ['Brandname' => 'WSMS-Care',     'Type' => 2],
                ['Brandname' => 'WSMS-Marketing', 'Type' => 2],
            ],
        ]);

        $config = [
            'shared'   => ['api_key' => self::API_KEY, 'secret_key' => self::SECRET_KEY],
            'channels' => [],
        ];

        foreach (['sms', 'viber'] as $section) {
            $options = $this->createProvider()->getConfigOptions('brandname', $section, $config);

            $this->assertCount(2, $options, "section={$section}");
            $this->assertSame(['value' => 'WSMS-Care', 'label' => 'WSMS-Care'], $options[0]);
            $this->assertSame(['value' => 'WSMS-Marketing', 'label' => 'WSMS-Marketing'], $options[1]);
        }
    }

    public function testGetConfigOptionsForBrandnameReturnsEmptyWhenUnconfigured(): void
    {
        $options = $this->createProvider()->getConfigOptions('brandname', 'sms', [
            'shared'   => [],
            'channels' => [],
        ]);

        $this->assertSame([], $options);
    }

    public function testGetConfigOptionsReturnsEmptyForUnknownField(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('unknown', 'sms', []));
    }

    public function testGetConfigOptionsReturnsEmptyForUnsupportedSection(): void
    {
        $this->assertSame([], $this->createProvider()->getConfigOptions('brandname', 'shared', []));
    }
}
