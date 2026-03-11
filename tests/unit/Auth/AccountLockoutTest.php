<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\AccountLockout;

class AccountLockoutTest extends TestCase
{
    private AccountLockout $lockout;

    protected function setUp(): void
    {
        $this->lockout = new AccountLockout();
        $GLOBALS['_test_user_meta'] = [];
        unset($GLOBALS['_test_options']['wsms_auth_settings']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_user_meta'] = [];
        unset($GLOBALS['_test_options']['wsms_auth_settings']);
    }

    public function testIsLockedReturnsFalseInitially(): void
    {
        $result = $this->lockout->isLocked(1);

        $this->assertFalse($result['locked']);
        $this->assertNull($result['until']);
        $this->assertSame(0, $result['attempts']);
    }

    public function testRecordFailureBelowThresholdDoesNotLock(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->lockout->recordFailure(1);
        }

        $result = $this->lockout->isLocked(1);

        $this->assertFalse($result['locked']);
        $this->assertSame(4, $result['attempts']);
    }

    public function testRecordFailureAtThresholdLocksAccount(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->lockout->recordFailure(1);
        }

        $result = $this->lockout->isLocked(1);

        $this->assertTrue($result['locked']);
        $this->assertNotNull($result['until']);
        $this->assertSame(5, $result['attempts']);
    }

    public function testIsLockedReturnsTrueWhenLocked(): void
    {
        update_user_meta(1, 'wsms_failed_attempts', 5);
        update_user_meta(1, 'wsms_lockout_until', time() + 300);

        $result = $this->lockout->isLocked(1);

        $this->assertTrue($result['locked']);
        $this->assertSame(5, $result['attempts']);
    }

    public function testIsLockedAutoUnlocksAfterExpiry(): void
    {
        update_user_meta(1, 'wsms_failed_attempts', 5);
        update_user_meta(1, 'wsms_lockout_until', time() - 1);

        $result = $this->lockout->isLocked(1);

        $this->assertFalse($result['locked']);
        $this->assertNull($result['until']);
        $this->assertSame(5, $result['attempts']);
    }

    public function testResetClearsFailuresAndLockout(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->lockout->recordFailure(1);
        }

        $this->lockout->reset(1);
        $result = $this->lockout->isLocked(1);

        $this->assertFalse($result['locked']);
        $this->assertSame(0, $result['attempts']);
    }

    public function testProgressiveLockoutIncreasesWithMoreFailures(): void
    {
        // 5 failures → 5 min lock
        for ($i = 0; $i < 5; $i++) {
            $this->lockout->recordFailure(1);
        }

        $after5 = $this->lockout->isLocked(1);
        $this->assertTrue($after5['locked']);
        $until5 = strtotime($after5['until']);

        // Clear lockout but keep attempts.
        delete_user_meta(1, 'wsms_lockout_until');

        // 5 more failures (total 10) → 15 min lock
        for ($i = 0; $i < 5; $i++) {
            $this->lockout->recordFailure(1);
        }

        $after10 = $this->lockout->isLocked(1);
        $this->assertTrue($after10['locked']);
        $this->assertSame(10, $after10['attempts']);
        $until10 = strtotime($after10['until']);

        // 15-min lockout should extend further than 5-min lockout.
        $this->assertGreaterThan($until5, $until10);
    }

    public function testCustomThresholdsFromSettings(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'lockout_thresholds' => [
                3 => 60,
            ],
        ];

        $lockout = new AccountLockout();

        for ($i = 0; $i < 3; $i++) {
            $lockout->recordFailure(1);
        }

        $result = $lockout->isLocked(1);

        $this->assertTrue($result['locked']);
        $this->assertSame(3, $result['attempts']);
    }
}
