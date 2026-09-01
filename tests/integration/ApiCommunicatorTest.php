<?php

namespace WP_SMS\Tests\Admin\LicenseManagement;

use WP_SMS\Admin\LicenseManagement\ApiCommunicator;
use WP_SMS\Exceptions\LicenseException;
use WP_UnitTestCase;

class ApiCommunicatorTest extends WP_UnitTestCase
{
    /**
     * @dataProvider supported_license_key_provider
     */
    public function test_validate_license_contacts_server_for_supported_key_formats($licenseKey)
    {
        $requestCount = 0;

        add_filter('pre_http_request', function ($preempt, $parsedArgs, $url) use (&$requestCount, $licenseKey) {
            $requestCount++;

            $this->assertStringContainsString('/license/status', $url);
            $this->assertStringContainsString('license_key=' . rawurlencode($licenseKey), $url);

            return [
                'headers'  => [],
                'body'     => wp_json_encode([
                    'status'          => 'valid',
                    'license_details' => [
                        'type'        => 'standard',
                        'sku'         => 'wp-sms-pro',
                        'max_domains' => 1,
                        'user'        => 'test@example.com',
                    ],
                    'products'        => [],
                ]),
                'response' => [
                    'code'    => 200,
                    'message' => 'OK',
                ],
                'cookies'  => [],
                'filename' => null,
            ];
        }, 10, 3);

        $result = (new ApiCommunicator())->validateLicense($licenseKey);

        $this->assertSame(1, $requestCount);
        $this->assertSame('valid', $result->status);
    }

    public function supported_license_key_provider()
    {
        return [
            '16-character key' => ['E0SNWPAPWYTHVPNV'],
            '32-character key' => [str_repeat('A', 32)],
            'legacy UUID key'  => ['123e4567-e89b-12d3-a456-426614174000'],
        ];
    }

    /**
     * @dataProvider malformed_license_key_provider
     */
    public function test_validate_license_rejects_malformed_keys_without_contacting_server($licenseKey)
    {
        $requestCount = 0;

        add_filter('pre_http_request', function () use (&$requestCount) {
            $requestCount++;

            return new \WP_Error('unexpected_request', 'The license server should not be contacted.');
        });

        try {
            (new ApiCommunicator())->validateLicense($licenseKey);
            $this->fail('Expected malformed license key to be rejected.');
        } catch (LicenseException $exception) {
            $this->assertSame('invalid_license', $exception->getStatus());
        }

        $this->assertSame(0, $requestCount);
    }

    public function malformed_license_key_provider()
    {
        return [
            'empty key'             => [''],
            'key containing spaces' => ['INVALID LICENSE KEY'],
            'key containing symbols' => ['INVALID_KEY!'],
        ];
    }
}
