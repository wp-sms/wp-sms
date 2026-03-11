<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\RateLimiter;

class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new RateLimiter();
        $GLOBALS['_test_transients'] = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_transients'] = [];
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testFirstRequestIsAllowed(): void
    {
        $result = $this->limiter->check('login', 5, 60);

        $this->assertTrue($result['allowed']);
        $this->assertSame(4, $result['remaining']);
        $this->assertSame(0, $result['retry_after']);
    }

    public function testRequestsWithinLimitAreAllowed(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $result = $this->limiter->check('login', 5, 60);
            $this->assertTrue($result['allowed']);
        }

        $this->assertSame(1, $result['remaining']);
    }

    public function testExceedingLimitIsBlocked(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->check('login', 5, 60);
        }

        $result = $this->limiter->check('login', 5, 60);

        $this->assertFalse($result['allowed']);
        $this->assertSame(0, $result['remaining']);
        $this->assertGreaterThan(0, $result['retry_after']);
    }

    public function testDifferentActionsAreIndependent(): void
    {
        // Exhaust 'login' limit.
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->check('login', 3, 60);
        }
        $loginResult = $this->limiter->check('login', 3, 60);
        $this->assertFalse($loginResult['allowed']);

        // 'verify' should still be allowed.
        $verifyResult = $this->limiter->check('verify', 3, 60);
        $this->assertTrue($verifyResult['allowed']);
    }

    public function testResetClearsLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->check('login', 5, 60);
        }

        $blocked = $this->limiter->check('login', 5, 60);
        $this->assertFalse($blocked['allowed']);

        $this->limiter->reset('login');

        $after = $this->limiter->check('login', 5, 60);
        $this->assertTrue($after['allowed']);
        $this->assertSame(4, $after['remaining']);
    }

    public function testCloudFlareIpHeaderIsPreferred(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.2.3.4';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        // Exhaust limit from CF IP.
        for ($i = 0; $i < 2; $i++) {
            $this->limiter->check('test', 2, 60);
        }
        $result = $this->limiter->check('test', 2, 60);
        $this->assertFalse($result['allowed']);

        // Different IP should still be allowed.
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        $result2 = $this->limiter->check('test', 2, 60);
        $this->assertTrue($result2['allowed']);
    }
}
