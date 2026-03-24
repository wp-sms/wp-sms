<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WSms\PhoneRestriction\CountryResolver;
use WSms\PhoneRestriction\DatabaseUpdater;
use WSms\PhoneRestriction\EnhancedPhoneDatabase;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\PhoneRestriction\SendingPolicyGuard;
use WSms\Rest\PhoneRestrictionController;

class PhoneRestrictionControllerTest extends TestCase
{
    private RestrictionSettings $settings;
    private CountryResolver $resolver;
    private SendingPolicyGuard $guard;
    private DatabaseUpdater $dbUpdater;
    private PhoneRestrictionController $controller;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/wsms-ctrl-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $GLOBALS['_test_upload_dir'] = $this->tmpDir;

        unset($GLOBALS['_test_options']['wsms_phone_restriction_settings']);
        unset($GLOBALS['_test_wp_remote_get']);

        EnhancedPhoneDatabase::resetCache();

        $this->settings  = new RestrictionSettings();
        $this->resolver  = new CountryResolver();
        $this->guard     = new SendingPolicyGuard($this->resolver, $this->settings);
        $this->dbUpdater = new DatabaseUpdater($this->settings);
        $this->controller = new PhoneRestrictionController($this->settings, $this->resolver, $this->guard, $this->dbUpdater);
    }

    protected function tearDown(): void
    {
        EnhancedPhoneDatabase::resetCache();
        unset($GLOBALS['_test_upload_dir']);
        unset($GLOBALS['_test_wp_remote_get']);
        $this->deleteDir($this->tmpDir);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    // --- GET /settings ---

    public function test_get_settings_returns_defaults(): void
    {
        $response = $this->controller->getSettings();
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('db_status', $data);
        $this->assertFalse($data['settings']['auth']['enabled']);
        $this->assertFalse($data['settings']['messaging']['enabled']);
        $this->assertSame([], $data['settings']['auth']['allowed_countries']);
    }

    public function test_get_settings_returns_saved_values(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US', 'GB']],
        ]);

        $response = $this->controller->getSettings();
        $data     = $response->get_data();

        $this->assertTrue($data['settings']['auth']['enabled']);
        $this->assertSame(['US', 'GB'], $data['settings']['auth']['allowed_countries']);
    }

    public function test_get_settings_includes_db_status(): void
    {
        $response = $this->controller->getSettings();
        $data     = $response->get_data();

        $this->assertFalse($data['db_status']['installed']);
        $this->assertNull($data['db_status']['version']);
        $this->assertNull($data['db_status']['updated_at']);
    }

    // --- PUT /settings ---

    public function test_update_settings_saves_partial_update(): void
    {
        $request = new \WP_REST_Request('PUT');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US']],
        ]));

        $response = $this->controller->updateSettings($request);
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertTrue($data['settings']['auth']['enabled']);
        $this->assertSame(['US'], $data['settings']['auth']['allowed_countries']);
        // Messaging should remain default
        $this->assertFalse($data['settings']['messaging']['enabled']);
    }

    public function test_update_settings_merges_with_existing(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US', 'GB']],
        ]);

        $request = new \WP_REST_Request('PUT');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'auth' => ['allowed_countries' => ['US', 'DE']],
        ]));

        $response = $this->controller->updateSettings($request);
        $data     = $response->get_data();

        // enabled should persist, countries should update
        $this->assertTrue($data['settings']['auth']['enabled']);
        $this->assertSame(['US', 'DE'], $data['settings']['auth']['allowed_countries']);
    }

    public function test_update_settings_rejects_empty_body(): void
    {
        $request = new \WP_REST_Request('PUT');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([]));

        $response = $this->controller->updateSettings($request);

        $this->assertSame(422, $response->get_status());
    }

    public function test_update_settings_ignores_unknown_sections(): void
    {
        $request = new \WP_REST_Request('PUT');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'unknown_section' => ['foo' => 'bar'],
            'auth'            => ['enabled' => true],
        ]));

        $response = $this->controller->updateSettings($request);
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertTrue($data['settings']['auth']['enabled']);
    }

    public function test_update_settings_sanitizes_boolean_values(): void
    {
        $request = new \WP_REST_Request('PUT');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'auth' => ['enabled' => 1],
        ]));

        $response = $this->controller->updateSettings($request);
        $data     = $response->get_data();

        $this->assertTrue($data['settings']['auth']['enabled']);
    }

    public function test_update_settings_sanitizes_country_codes(): void
    {
        $request = new \WP_REST_Request('PUT');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'auth' => ['allowed_countries' => ['US', '', 'GB']],
        ]));

        $response = $this->controller->updateSettings($request);
        $data     = $response->get_data();

        // Empty strings should be filtered out
        $this->assertSame(['US', 'GB'], $data['settings']['auth']['allowed_countries']);
    }

    public function test_update_settings_saves_block_mode(): void
    {
        $request = new \WP_REST_Request('PUT');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'auth' => ['mode' => 'block', 'enabled' => true, 'allowed_countries' => ['US']],
        ]));

        $response = $this->controller->updateSettings($request);
        $data     = $response->get_data();

        $this->assertSame('block', $data['settings']['auth']['mode']);
        $this->assertTrue($data['settings']['auth']['enabled']);
        $this->assertSame(['US'], $data['settings']['auth']['allowed_countries']);
    }

    public function test_update_settings_defaults_invalid_mode_to_allow(): void
    {
        $request = new \WP_REST_Request('PUT');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'auth' => ['mode' => 'invalid'],
        ]));

        $response = $this->controller->updateSettings($request);
        $data     = $response->get_data();

        $this->assertSame('allow', $data['settings']['auth']['mode']);
    }

    // --- POST /db/download ---

    public function test_download_db_returns_success_on_valid_response(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode([
                'version'     => '1.0.0',
                'territories' => ['US' => ['country_code' => '1']],
            ]),
            'response' => ['code' => 200],
        ];

        $response = $this->controller->downloadDb();
        $data     = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('db_status', $data);
    }

    public function test_download_db_returns_502_on_failure(): void
    {
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'Timeout');

        $response = $this->controller->downloadDb();
        $data     = $response->get_data();

        $this->assertSame(502, $response->get_status());
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('db_status', $data);
    }

    // --- GET /db/status ---

    public function test_db_status_returns_not_installed(): void
    {
        $response = $this->controller->dbStatus();
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertFalse($data['db_status']['installed']);
    }

    // --- POST /check ---

    public function test_check_phone_allowed(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'phone'   => '+12025551234',
            'context' => 'auth',
        ]));

        $response = $this->controller->checkPhone($request);
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertTrue($data['auth']['allowed']);
        $this->assertSame('US', $data['country']);
        $this->assertSame('United States', $data['country_name']);
        $this->assertNull($data['auth']['reason']);
    }

    public function test_check_phone_blocked_by_country(): void
    {
        $this->settings->save([
            'auth' => ['enabled' => true, 'allowed_countries' => ['US']],
        ]);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'phone'   => '+447911123456',
            'context' => 'auth',
        ]));

        $response = $this->controller->checkPhone($request);
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertFalse($data['auth']['allowed']);
        $this->assertSame('GB', $data['country']);
        $this->assertSame('country_blocked', $data['auth']['reason']);
    }

    public function test_check_phone_returns_both_contexts(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'phone' => '+12025551234',
        ]));

        $response = $this->controller->checkPhone($request);
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('auth', $data);
        $this->assertArrayHasKey('messaging', $data);
        $this->assertTrue($data['auth']['allowed']);
    }

    public function test_check_phone_rejects_empty_phone(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'phone' => '',
        ]));

        $response = $this->controller->checkPhone($request);

        $this->assertSame(422, $response->get_status());
    }

    public function test_check_phone_rejects_missing_phone(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'context' => 'auth',
        ]));

        $response = $this->controller->checkPhone($request);

        $this->assertSame(422, $response->get_status());
    }

    public function test_check_phone_messaging_context(): void
    {
        $this->settings->save([
            'messaging' => ['enabled' => true, 'allowed_countries' => ['US']],
        ]);

        $request = new \WP_REST_Request('POST');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'phone' => '+447911123456',
        ]));

        $response = $this->controller->checkPhone($request);
        $data     = $response->get_data();

        $this->assertFalse($data['messaging']['allowed']);
        $this->assertSame('country_blocked', $data['messaging']['reason']);
    }

    // --- GET /countries ---

    public function test_countries_returns_list(): void
    {
        $response = $this->controller->countries();
        $data     = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertIsArray($data['countries']);
        $this->assertArrayHasKey('US', $data['countries']);
        $this->assertSame('United States', $data['countries']['US']);
        $this->assertArrayHasKey('GB', $data['countries']);
    }
}
