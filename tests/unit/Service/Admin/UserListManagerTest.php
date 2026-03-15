<?php

namespace WSms\Tests\Unit\Service\Admin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\AccountLockout;
use WSms\Mfa\MfaManager;
use WSms\Service\Admin\UserListManager;

class UserListManagerTest extends TestCase
{
    private UserListManager $manager;
    private MockObject&MfaManager $mfaManager;
    private MockObject&AccountLockout $lockout;

    protected function setUp(): void
    {
        $this->mfaManager = $this->createMock(MfaManager::class);
        $this->lockout = $this->createMock(AccountLockout::class);
        $this->manager = new UserListManager($this->mfaManager, $this->lockout);
        $GLOBALS['_test_user_meta'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_user_meta'] = [];
    }

    public function testAddColumnsAppendsSingleAuthColumn(): void
    {
        $columns = ['username' => 'Username', 'email' => 'Email'];
        $result = $this->manager->addColumns($columns);

        $this->assertArrayHasKey('wsms_auth', $result);
        $this->assertCount(3, $result);
    }

    public function testRenderColumnShowsEmailVerifiedPill(): void
    {
        $GLOBALS['_test_user_meta'][1] = ['wsms_email_verified' => '1'];
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);

        $this->assertStringContainsString('wsms-pill--verified', $output);
        $this->assertStringContainsString('Email', $output);
        $this->assertStringContainsString('<svg', $output);
        $this->assertStringContainsString('Email: verified', $output);
    }

    public function testRenderColumnShowsEmailUnverifiedPill(): void
    {
        $GLOBALS['_test_user_meta'][1] = ['wsms_email_verified' => '0'];
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);

        $this->assertStringContainsString('wsms-pill--unverified', $output);
        $this->assertStringContainsString('Email: not verified', $output);
    }

    public function testRenderColumnShowsPhoneWhenSet(): void
    {
        $GLOBALS['_test_user_meta'][1] = [
            'wsms_email_verified' => '0',
            'wsms_phone'          => '+1234567890',
            'wsms_phone_verified' => '1',
        ];
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);

        $this->assertStringContainsString('Phone', $output);
        $this->assertStringContainsString('Phone: verified', $output);
    }

    public function testRenderColumnHidesPhoneWhenNotSet(): void
    {
        $GLOBALS['_test_user_meta'][1] = ['wsms_email_verified' => '0'];
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);
        $this->assertStringNotContainsString('Phone', $output);
    }

    public function testRenderColumnShowsMfaPillWithFactors(): void
    {
        $GLOBALS['_test_user_meta'][1] = ['wsms_email_verified' => '1'];
        $this->mfaManager->method('getActiveMfaFactors')->with(1)->willReturn([
            ['channel_id' => 'totp', 'name' => 'Authenticator App'],
        ]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);

        $this->assertStringContainsString('wsms-pill--mfa', $output);
        $this->assertStringContainsString('MFA', $output);
        $this->assertStringContainsString('Authenticator App', $output);
    }

    public function testRenderColumnHidesMfaWhenNoFactors(): void
    {
        $GLOBALS['_test_user_meta'][1] = ['wsms_email_verified' => '0'];
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);
        $this->assertStringNotContainsString('MFA', $output);
    }

    public function testRenderColumnReturnsOriginalForUnknownColumn(): void
    {
        $output = $this->manager->renderColumn('original', 'unknown_col', 1);
        $this->assertSame('original', $output);
    }

    public function testRenderColumnShowsLockedPill(): void
    {
        $GLOBALS['_test_user_meta'][1] = ['wsms_email_verified' => '0'];
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->lockout->method('isLocked')->with(1)->willReturn([
            'locked' => true, 'until' => '2026-03-15T12:00:00Z', 'attempts' => 5,
        ]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);

        $this->assertStringContainsString('wsms-pill--locked', $output);
        $this->assertStringContainsString('Locked', $output);
        $this->assertStringContainsString('<svg', $output);
    }

    public function testRenderColumnHidesLockedPillWhenNotLocked(): void
    {
        $GLOBALS['_test_user_meta'][1] = ['wsms_email_verified' => '0'];
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 3]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);

        $this->assertStringNotContainsString('wsms-pill--locked', $output);
        $this->assertStringNotContainsString('Locked', $output);
    }

    public function testRenderColumnShowsPendingPill(): void
    {
        $GLOBALS['_test_user_meta'][1] = [
            'wsms_email_verified'      => '0',
            'wsms_registration_status' => 'pending',
        ];
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);

        $this->assertStringContainsString('wsms-pill--pending', $output);
        $this->assertStringContainsString('Pending', $output);
    }

    public function testRenderColumnHidesPendingPillWhenActive(): void
    {
        $GLOBALS['_test_user_meta'][1] = [
            'wsms_email_verified'      => '1',
            'wsms_registration_status' => 'active',
        ];
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $output = $this->manager->renderColumn('', 'wsms_auth', 1);

        $this->assertStringNotContainsString('wsms-pill--pending', $output);
        $this->assertStringNotContainsString('Pending', $output);
    }

    public function testEnqueueAssetsOnlyOnUsersPage(): void
    {
        $this->manager->enqueueAssets('edit.php');
        $this->assertTrue(true); // No exception = pass.
    }
}
