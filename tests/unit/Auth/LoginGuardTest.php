<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\AuthSession;
use WSms\Auth\LoginGuard;
use WSms\Auth\PolicyEngine;
use WSms\Auth\SettingsRepository;
use WSms\Enums\SessionStage;
use WSms\Mfa\MfaManager;

/**
 * Testable LoginGuard that overrides redirect+exit.
 */
class TestableLoginGuard extends LoginGuard
{
    public ?string $redirectedTo = null;

    protected function redirect(string $url): void
    {
        $this->redirectedTo = $url;
    }
}

class LoginGuardTest extends TestCase
{
    private TestableLoginGuard $guard;
    private MockObject&PolicyEngine $policy;
    private MockObject&AuthSession $session;
    private MockObject&MfaManager $mfaManager;

    protected function setUp(): void
    {
        $this->policy = $this->createMock(PolicyEngine::class);
        $this->session = $this->createMock(AuthSession::class);
        $this->mfaManager = $this->createMock(MfaManager::class);

        $this->guard = new TestableLoginGuard(
            $this->policy,
            $this->session,
            $this->mfaManager,
            new SettingsRepository(),
        );

        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_auth_cookie_cleared'] = false;
        $GLOBALS['_test_current_user_id'] = null;
        $GLOBALS['_test_doing_ajax'] = false;
        $GLOBALS['_test_options'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_user_meta'],
            $GLOBALS['_test_auth_cookie_cleared'],
            $GLOBALS['_test_current_user_id'],
            $GLOBALS['_test_doing_ajax'],
        );
    }

    // --- blockPendingUsers tests (existing) ---

    public function testBlocksPendingUser(): void
    {
        $user = new \WP_User(1);
        $GLOBALS['_test_user_meta'][1]['wsms_registration_status'] = 'pending';

        $result = $this->guard->blockPendingUsers($user, 'testuser', 'pass');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('account_pending_verification', $result->get_error_code());
    }

    public function testAllowsActiveUser(): void
    {
        $user = new \WP_User(2);
        $GLOBALS['_test_user_meta'][2]['wsms_registration_status'] = 'active';

        $result = $this->guard->blockPendingUsers($user, 'testuser', 'pass');

        $this->assertSame($user, $result);
    }

    public function testAllowsUserWithNoStatusMeta(): void
    {
        $user = new \WP_User(3);

        $result = $this->guard->blockPendingUsers($user, 'testuser', 'pass');

        $this->assertSame($user, $result);
    }

    public function testPassesThroughWpError(): void
    {
        $error = new \WP_Error('invalid', 'bad credentials');

        $result = $this->guard->blockPendingUsers($error, 'testuser', 'pass');

        $this->assertSame($error, $result);
    }

    public function testPassesThroughNull(): void
    {
        $result = $this->guard->blockPendingUsers(null, '', '');

        $this->assertNull($result);
    }

    // --- enforceMfaOnWpLogin tests ---

    public function testEnforceMfaRedirectsWhenRequired(): void
    {
        $user = new \WP_User(1);
        $user->user_login = 'admin';

        $this->policy->method('isMfaRequired')->with(1)->willReturn(true);

        $this->mfaManager->method('getActiveMfaFactors')->with(1)->willReturn([
            ['channel_id' => 'phone', 'name' => 'Phone'],
        ]);

        $this->session->method('create')
            ->with(1, 'password', SessionStage::PrimaryVerified)
            ->willReturn('mfa-session-token');

        $this->guard->enforceMfaOnWpLogin('admin', $user);

        $this->assertTrue($GLOBALS['_test_auth_cookie_cleared']);
        $this->assertSame(0, $GLOBALS['_test_current_user_id']);
        $this->assertNotNull($this->guard->redirectedTo);
        $this->assertStringContainsString('/account/login?wp_mfa=', $this->guard->redirectedTo);
        $this->assertStringContainsString('mfa-session-token', urldecode($this->guard->redirectedTo));
    }

    public function testEnforceMfaSkipsWhenNotRequired(): void
    {
        $user = new \WP_User(1);

        $this->policy->method('isMfaRequired')->with(1)->willReturn(false);

        $this->guard->enforceMfaOnWpLogin('admin', $user);

        $this->assertNull($this->guard->redirectedTo);
    }

    public function testEnforceMfaRedirectsToEnrollmentWhenNoFactors(): void
    {
        $user = new \WP_User(1);

        $this->policy->method('isMfaRequired')->with(1)->willReturn(true);
        $this->mfaManager->method('getActiveMfaFactors')->with(1)->willReturn([]);

        $this->guard->enforceMfaOnWpLogin('admin', $user);

        // Should redirect to enrollment page instead of passing through.
        $this->assertNotNull($this->guard->redirectedTo);
        $this->assertStringContainsString('/security?mfa_enroll=required', $this->guard->redirectedTo);
        // Should set enrollment pending meta.
        $this->assertSame('1', $GLOBALS['_test_user_meta'][1]['wsms_mfa_enrollment_pending'] ?? '');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testEnforceMfaSkipsWhenRestRequest(): void
    {
        define('REST_REQUEST', true);

        $user = new \WP_User(1);
        $this->policy->method('isMfaRequired')->willReturn(true);

        $this->guard->enforceMfaOnWpLogin('admin', $user);

        $this->assertNull($this->guard->redirectedTo);
    }

    public function testEnforceMfaSkipsWhenDoingAjax(): void
    {
        $GLOBALS['_test_doing_ajax'] = true;

        $user = new \WP_User(1);
        $this->policy->method('isMfaRequired')->willReturn(true);

        $this->guard->enforceMfaOnWpLogin('admin', $user);

        $this->assertNull($this->guard->redirectedTo);
    }

    // --- Enrollment gate REST filter tests ---

    public function testEnrollmentGateBlocksNonEnrollmentEndpoints(): void
    {
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_mfa_enrollment_pending'] = '1';

        $this->policy->method('isMfaRequired')->with(1)->willReturn(true);
        $this->mfaManager->method('getActiveMfaFactors')->with(1)->willReturn([]);

        $request = new \WP_REST_Request('GET', '/wsms/v1/auth/some-other-endpoint');

        $result = $this->guard->enforceEnrollmentGate(null, null, $request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('mfa_enrollment_required', $result->get_error_code());
    }

    public function testEnrollmentGateAllowsEnrollmentEndpoints(): void
    {
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_mfa_enrollment_pending'] = '1';

        $this->policy->method('isMfaRequired')->with(1)->willReturn(true);
        $this->mfaManager->method('getActiveMfaFactors')->with(1)->willReturn([]);

        $allowedRoutes = [
            '/wsms/v1/auth/mfa/enroll',
            '/wsms/v1/auth/mfa/enroll/verify',
            '/wsms/v1/auth/methods',
            '/wsms/v1/auth/me',
            '/wsms/v1/auth/factors',
            '/wsms/v1/auth/logout',
            '/wsms/v1/auth/config',
        ];

        foreach ($allowedRoutes as $route) {
            $request = new \WP_REST_Request('GET', $route);
            $result = $this->guard->enforceEnrollmentGate(null, null, $request);
            $this->assertNull($result, "Route {$route} should be allowed through the gate.");
        }
    }

    public function testEnrollmentGateClearsWhenSettingsChange(): void
    {
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_mfa_enrollment_pending'] = '1';

        // MFA is no longer required (admin changed settings).
        $this->policy->method('isMfaRequired')->with(1)->willReturn(false);

        $request = new \WP_REST_Request('GET', '/wsms/v1/auth/some-endpoint');

        $result = $this->guard->enforceEnrollmentGate(null, null, $request);

        // Gate should auto-clear and pass through.
        $this->assertNull($result);
        $this->assertEmpty($GLOBALS['_test_user_meta'][1]['wsms_mfa_enrollment_pending'] ?? '');
    }

    public function testEnrollmentGatePassesThroughNonWsmsRoutes(): void
    {
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_mfa_enrollment_pending'] = '1';

        // Non-WSMS routes (Gutenberg, WP core) should pass through.
        $request = new \WP_REST_Request('GET', '/wp/v2/posts');

        $result = $this->guard->enforceEnrollmentGate(null, null, $request);

        $this->assertNull($result);
    }

    public function testEnrollmentGateClearsWhenFactorsExist(): void
    {
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][1]['wsms_mfa_enrollment_pending'] = '1';

        $this->policy->method('isMfaRequired')->with(1)->willReturn(true);
        // User enrolled via another path.
        $this->mfaManager->method('getActiveMfaFactors')->with(1)->willReturn([
            ['channel_id' => 'phone', 'name' => 'Phone'],
        ]);

        $request = new \WP_REST_Request('GET', '/wsms/v1/auth/some-endpoint');

        $result = $this->guard->enforceEnrollmentGate(null, null, $request);

        // Gate should auto-clear and pass through.
        $this->assertNull($result);
        $this->assertEmpty($GLOBALS['_test_user_meta'][1]['wsms_mfa_enrollment_pending'] ?? '');
    }
}
