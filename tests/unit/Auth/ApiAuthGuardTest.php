<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_User;
use WSms\Auth\ApiAuthGuard;
use WSms\Mfa\MfaManager;

class ApiAuthGuardTest extends TestCase
{
    private ApiAuthGuard $guard;
    private MockObject&MfaManager $mfaManager;

    protected function setUp(): void
    {
        $this->mfaManager = $this->createMock(MfaManager::class);
        $this->guard = new ApiAuthGuard($this->mfaManager);

        $GLOBALS['_test_did_action'] = [];
        $GLOBALS['wp'] = (object) ['query_vars' => []];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_did_action'],
            $GLOBALS['wp'],
        );
    }

    /**
     * Non-API requests (no REST_REQUEST constant) should pass through.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAllowsNonApiRequests(): void
    {
        // REST_REQUEST is not defined — this is a normal web request.
        $user = new WP_User(1);

        $this->mfaManager->method('hasActiveFactors')->with(1)->willReturn(true);

        $result = $this->guard->blockApiLoginForMfaUsers($user, 'admin', 'pass');

        $this->assertSame($user, $result);
    }

    /**
     * WSMS auth routes (/wsms/v1/auth/*) should be allowed even for MFA users.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAllowsWsmsAuthRoutes(): void
    {
        define('REST_REQUEST', true);
        $GLOBALS['wp']->query_vars['rest_route'] = '/wsms/v1/auth/login';

        $user = new WP_User(1);

        $this->mfaManager->method('hasActiveFactors')->with(1)->willReturn(true);

        $result = $this->guard->blockApiLoginForMfaUsers($user, 'admin', 'pass');

        $this->assertSame($user, $result);
    }

    /**
     * External API requests should be blocked for users with active MFA factors.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testBlocksExternalApiForMfaUsers(): void
    {
        define('REST_REQUEST', true);
        $GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';

        $user = new WP_User(1);

        $this->mfaManager->method('hasActiveFactors')->with(1)->willReturn(true);

        $result = $this->guard->blockApiLoginForMfaUsers($user, 'admin', 'pass');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mfa_api_blocked', $result->get_error_code());
    }

    /**
     * External API requests should pass through for users without MFA factors.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAllowsExternalApiWithoutMfa(): void
    {
        define('REST_REQUEST', true);
        $GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';

        $user = new WP_User(1);

        $this->mfaManager->method('hasActiveFactors')->with(1)->willReturn(false);

        $result = $this->guard->blockApiLoginForMfaUsers($user, 'admin', 'pass');

        $this->assertSame($user, $result);
    }

    /**
     * Application password authentication should bypass MFA check.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAllowsApplicationPasswordAuth(): void
    {
        define('REST_REQUEST', true);
        $GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';
        $GLOBALS['_test_did_action']['application_password_did_authenticate'] = 1;

        $user = new WP_User(1);

        $this->mfaManager->method('hasActiveFactors')->with(1)->willReturn(true);

        $result = $this->guard->blockApiLoginForMfaUsers($user, 'admin', 'pass');

        $this->assertSame($user, $result);
    }

    /**
     * The wsms_user_api_login_enable filter can allow API access for MFA users.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAllowsViaFilter(): void
    {
        define('REST_REQUEST', true);
        $GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';

        $user = new WP_User(1);

        $this->mfaManager->method('hasActiveFactors')->with(1)->willReturn(true);

        // Stub apply_filters to return true for wsms_user_api_login_enable.
        $GLOBALS['_test_apply_filters']['wsms_user_api_login_enable'] = fn() => true;

        $result = $this->guard->blockApiLoginForMfaUsers($user, 'admin', 'pass');

        $this->assertSame($user, $result);

        unset($GLOBALS['_test_apply_filters']['wsms_user_api_login_enable']);
    }

    /**
     * WSMS non-auth routes (e.g. /wsms/v1/admin/settings) should NOT be exempt.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testBlocksWsmsNonAuthRoutes(): void
    {
        define('REST_REQUEST', true);
        $GLOBALS['wp']->query_vars['rest_route'] = '/wsms/v1/admin/settings';

        $user = new WP_User(1);

        $this->mfaManager->method('hasActiveFactors')->with(1)->willReturn(true);

        $result = $this->guard->blockApiLoginForMfaUsers($user, 'admin', 'pass');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mfa_api_blocked', $result->get_error_code());
    }
}
