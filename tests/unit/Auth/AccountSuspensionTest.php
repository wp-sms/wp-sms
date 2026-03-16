<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\AccountSuspension;

class AccountSuspensionTest extends TestCase
{
    private AccountSuspension $suspension;

    protected function setUp(): void
    {
        $this->suspension = new AccountSuspension();
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_sessions_destroyed'] = [];
        \WP_Session_Tokens::resetInstances();
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_user_meta'] = [];
        unset($GLOBALS['_test_sessions_destroyed']);
        \WP_Session_Tokens::resetInstances();
    }

    public function testIsSuspendedReturnsFalseInitially(): void
    {
        $result = $this->suspension->isSuspended(1);

        $this->assertFalse($result['suspended']);
        $this->assertNull($result['at']);
        $this->assertNull($result['by']);
    }

    public function testSuspendSetsMeta(): void
    {
        $this->suspension->suspend(1, 99);

        $result = $this->suspension->isSuspended(1);

        $this->assertTrue($result['suspended']);
        $this->assertNotNull($result['at']);
        $this->assertSame(99, $result['by']);
    }

    public function testSuspendDestroysAllSessions(): void
    {
        $this->suspension->suspend(1, 99);

        $this->assertTrue($GLOBALS['_test_sessions_destroyed'][1] ?? false);
    }

    public function testUnsuspendClearsMeta(): void
    {
        $this->suspension->suspend(1, 99);

        $this->assertTrue($this->suspension->isSuspended(1)['suspended']);

        $this->suspension->unsuspend(1);

        $result = $this->suspension->isSuspended(1);

        $this->assertFalse($result['suspended']);
        $this->assertNull($result['at']);
        $this->assertNull($result['by']);
    }

    public function testIsSuspendedReturnsTimestamp(): void
    {
        $this->suspension->suspend(1, 5);

        $result = $this->suspension->isSuspended(1);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $result['at']);
    }

    public function testSuspendDifferentUsersAreIndependent(): void
    {
        $this->suspension->suspend(1, 99);

        $this->assertTrue($this->suspension->isSuspended(1)['suspended']);
        $this->assertFalse($this->suspension->isSuspended(2)['suspended']);
    }

    public function testUnsuspendNonSuspendedUserDoesNothing(): void
    {
        $this->suspension->unsuspend(999);

        $this->assertFalse($this->suspension->isSuspended(999)['suspended']);
    }
}
