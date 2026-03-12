<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Mfa\MfaManager;
use WSms\Rest\AdminController;

class AdminControllerTest extends TestCase
{
    private AdminController $controller;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&MfaManager $mfaManager;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->mfaManager = $this->createMock(MfaManager::class);

        $this->controller = new AdminController(
            $this->auditLogger,
            $this->mfaManager,
        );

        unset(
            $GLOBALS['_test_current_user_can'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_current_user_id'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_current_user_can'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_current_user_id'],
        );
    }

    public function testCheckAdminReturnsTrueWhenCapable(): void
    {
        $GLOBALS['_test_current_user_can'] = true;
        $request = new \WP_REST_Request();
        $this->assertTrue($this->controller->checkAdmin($request));
    }

    public function testCheckAdminReturnsFalseWhenNotCapable(): void
    {
        $GLOBALS['_test_current_user_can'] = false;
        $request = new \WP_REST_Request();
        $this->assertFalse($this->controller->checkAdmin($request));
    }

    public function testGetSettingsReturnsCurrentSettings(): void
    {
        $request = new \WP_REST_Request('GET', '/auth/admin/settings');

        $response = $this->controller->handleGetSettings($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertArrayHasKey('settings', $response->get_data());
    }

    public function testUpdateSettingsUpdatesOption(): void
    {
        $request = new \WP_REST_Request('PUT', '/auth/admin/settings');
        $request->set_param('log_verbosity', 'verbose');

        $response = $this->controller->handleUpdateSettings($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertSame('Settings updated.', $response->get_data()['message']);
    }

    public function testGetLogsPaginates(): void
    {
        $this->auditLogger->method('getEvents')->willReturn([
            'items' => [],
            'total' => 0,
        ]);

        $request = new \WP_REST_Request('GET', '/auth/admin/logs');
        $request->set_param('page', 2);
        $request->set_param('per_page', 25);

        $response = $this->controller->handleGetLogs($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame(2, $data['page']);
        $this->assertSame(25, $data['per_page']);
    }

    public function testDisableUserMfaSucceeds(): void
    {
        $user = $this->makeUser(5);
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_current_user_id'] = 1;

        $this->mfaManager->expects($this->once())->method('disableAllFactors')->with(5);
        $this->auditLogger->expects($this->once())->method('log');

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/5/mfa');
        $request->set_param('id', 5);

        $response = $this->controller->handleDisableUserMfa($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testDisableUserMfaReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/999/mfa');
        $request->set_param('id', 999);

        $response = $this->controller->handleDisableUserMfa($request);

        $this->assertSame(404, $response->get_status());
        $this->assertSame('user_not_found', $response->get_data()['error']);
    }

    public function testValidationRejectsInvalidCodeLength(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [];

        $request = new \WP_REST_Request('PUT', '/auth/admin/settings');
        $request->set_param('phone', ['code_length' => 5]);

        $response = $this->controller->handleUpdateSettings($request);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testValidationRejectsInvalidAuthBaseUrl(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [];

        $request = new \WP_REST_Request('PUT', '/auth/admin/settings');
        $request->set_param('auth_base_url', 'no-leading-slash');

        $response = $this->controller->handleUpdateSettings($request);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testValidationAcceptsValidCodeLength(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [];

        $request = new \WP_REST_Request('PUT', '/auth/admin/settings');
        $request->set_param('phone', ['code_length' => 4]);

        $response = $this->controller->handleUpdateSettings($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testRewriteFlushTransientSetWhenAuthBaseUrlChanges(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = ['auth_base_url' => '/account'];
        $GLOBALS['_test_transients'] = [];

        $request = new \WP_REST_Request('PUT', '/auth/admin/settings');
        $request->set_param('auth_base_url', '/my-auth');

        $response = $this->controller->handleUpdateSettings($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('1', get_transient('wsms_flush_rewrite'));
    }

    public function testNoRewriteFlushWhenAuthBaseUrlUnchanged(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = ['auth_base_url' => '/account'];
        $GLOBALS['_test_transients'] = [];

        $request = new \WP_REST_Request('PUT', '/auth/admin/settings');
        $request->set_param('auth_base_url', '/account');

        $response = $this->controller->handleUpdateSettings($request);

        $this->assertSame(200, $response->get_status());
        $this->assertFalse(get_transient('wsms_flush_rewrite'));
    }

    private function makeUser(int $id): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_email = 'admin@example.com';
        $user->user_login = 'admin';
        $user->display_name = 'Admin';
        $user->roles = ['administrator'];

        return $user;
    }
}
