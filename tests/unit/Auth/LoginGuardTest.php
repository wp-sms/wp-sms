<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\LoginGuard;

class LoginGuardTest extends TestCase
{
    private LoginGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new LoginGuard();
        $GLOBALS['_test_user_meta'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_user_meta']);
    }

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
}
