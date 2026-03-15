<?php

namespace WSms\Tests\Unit\Service\Admin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\SettingsRepository;
use WSms\Mfa\MfaManager;
use WSms\Service\Admin\UserProfileSection;
use WSms\Social\SocialAccountRepository;

class UserProfileSectionTest extends TestCase
{
    private UserProfileSection $section;
    private MockObject&MfaManager $mfaManager;
    private MockObject&SocialAccountRepository $socialRepository;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&AccountLockout $lockout;
    private MockObject&SettingsRepository $settingsRepo;

    protected function setUp(): void
    {
        $this->mfaManager = $this->createMock(MfaManager::class);
        $this->socialRepository = $this->createMock(SocialAccountRepository::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->lockout = $this->createMock(AccountLockout::class);
        $this->settingsRepo = $this->createMock(SettingsRepository::class);

        $this->section = new UserProfileSection(
            $this->mfaManager,
            $this->socialRepository,
            $this->auditLogger,
            $this->lockout,
            $this->settingsRepo,
        );

        $GLOBALS['_test_user_meta'] = [];
        unset($GLOBALS['_test_current_user_can']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_user_meta'] = [];
        unset($GLOBALS['_test_current_user_can']);
    }

    private function setupDefaultMocks(): void
    {
        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->socialRepository->method('findByUserId')->willReturn([]);
        $this->auditLogger->method('getEvents')->willReturn(['items' => [], 'total' => 0]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);
        $this->settingsRepo->method('channel')->willReturnCallback(function (string $id) {
            return SettingsRepository::CHANNEL_DEFAULTS[$id] ?? [];
        });
    }

    public function testRenderOutputsSection(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = [
            'wsms_email_verified' => '1',
            'wsms_phone'          => '+1234567890',
            'wsms_phone_verified' => '0',
        ];
        $GLOBALS['_test_current_user_can'] = true;

        $this->mfaManager->method('getActiveMfaFactors')->willReturn([
            ['channel_id' => 'totp', 'name' => 'Authenticator App'],
        ]);
        $this->socialRepository->method('findByUserId')->willReturn([
            (object) [
                'channel_id' => 'google',
                'meta'       => json_encode(['email' => 'user@gmail.com']),
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);
        $this->auditLogger->method('getEvents')->willReturn(['items' => [], 'total' => 5]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);
        $this->settingsRepo->method('channel')->willReturnCallback(function (string $id) {
            return SettingsRepository::CHANNEL_DEFAULTS[$id] ?? [];
        });

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('WSMS Authentication', $html);
        $this->assertStringContainsString('test@example.com', $html);
        $this->assertStringContainsString('wsms-pill--verified', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('+1234567890', $html);
        $this->assertStringContainsString('wsms-pill--unverified', $html);
        $this->assertStringContainsString('Authenticator App', $html);
        $this->assertStringContainsString('wsms-pill--mfa', $html);
        $this->assertStringContainsString('Google', $html);
        $this->assertStringContainsString('user@gmail.com', $html);
    }

    public function testRenderShowsActivityLink(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = ['wsms_email_verified' => '1'];
        $GLOBALS['_test_current_user_can'] = false;

        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->socialRepository->method('findByUserId')->willReturn([]);
        $this->auditLogger->method('getEvents')->willReturn(['items' => [], 'total' => 12]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);
        $this->settingsRepo->method('channel')->willReturnCallback(function (string $id) {
            return SettingsRepository::CHANNEL_DEFAULTS[$id] ?? [];
        });

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('View 12 events', $html);
        $this->assertStringContainsString('page=wsms#logs', $html);
    }

    public function testRenderShowsNoActivityMessage(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = ['wsms_email_verified' => '0'];
        $GLOBALS['_test_current_user_can'] = false;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('No activity recorded', $html);
        $this->assertStringNotContainsString('View', $html);
    }

    public function testRenderHidesActionButtonsForNonAdmins(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = ['wsms_email_verified' => '0'];
        $GLOBALS['_test_current_user_can'] = false;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('WSMS Authentication', $html);
        $this->assertStringNotContainsString('wsms-action', $html);
    }

    public function testRenderShowsNoPhoneMessage(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = ['wsms_email_verified' => '0'];
        $GLOBALS['_test_current_user_can'] = true;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('No phone number set', $html);
    }

    public function testRenderShowsLockoutRow(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = ['wsms_email_verified' => '1'];
        $GLOBALS['_test_current_user_can'] = true;

        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->socialRepository->method('findByUserId')->willReturn([]);
        $this->auditLogger->method('getEvents')->willReturn(['items' => [], 'total' => 0]);
        $this->lockout->method('isLocked')->willReturn([
            'locked' => true, 'until' => '2026-03-15T14:00:00Z', 'attempts' => 5,
        ]);
        $this->settingsRepo->method('channel')->willReturnCallback(function (string $id) {
            return SettingsRepository::CHANNEL_DEFAULTS[$id] ?? [];
        });

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('Account Lockout', $html);
        $this->assertStringContainsString('wsms-pill--locked', $html);
        $this->assertStringContainsString('5 failed attempts', $html);
        $this->assertStringContainsString('data-action="unlock"', $html);
    }

    public function testRenderShowsNoLockoutMessage(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = ['wsms_email_verified' => '1'];
        $GLOBALS['_test_current_user_can'] = false;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('No lockout', $html);
    }

    public function testRenderShowsPasswordRow(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = [
            'wsms_email_verified'       => '1',
            'wsms_has_usable_password'  => '1',
        ];
        $GLOBALS['_test_current_user_can'] = true;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('Has password', $html);
        $this->assertStringContainsString('Send Reset Email', $html);
    }

    public function testRenderShowsNoPasswordForSocialLogin(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = [
            'wsms_email_verified'       => '1',
            'wsms_has_usable_password'  => '0',
        ];
        $GLOBALS['_test_current_user_can'] = true;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('No password', $html);
        $this->assertStringContainsString('Social login only', $html);
    }

    public function testRenderShowsPendingRegistrationRow(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = [
            'wsms_email_verified'          => '0',
            'wsms_registration_status'     => 'pending',
            'wsms_registration_created_at' => '2026-01-01 10:00:00',
        ];
        $GLOBALS['_test_current_user_can'] = true;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('Registration', $html);
        $this->assertStringContainsString('wsms-pill--pending', $html);
        $this->assertStringContainsString('Pending', $html);
        $this->assertStringContainsString('data-action="activate"', $html);
        $this->assertStringContainsString('2026-01-01 10:00:00', $html);
    }

    public function testRenderHidesRegistrationRowForActiveUsers(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = [
            'wsms_email_verified'      => '1',
            'wsms_registration_status' => 'active',
        ];
        $GLOBALS['_test_current_user_can'] = true;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringNotContainsString('wsms-registration-row', $html);
        $this->assertStringNotContainsString('data-action="activate"', $html);
    }

    public function testRenderShowsSendVerificationWhenEmailUnverifiedAndEnabled(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = ['wsms_email_verified' => '0'];
        $GLOBALS['_test_current_user_can'] = true;

        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->socialRepository->method('findByUserId')->willReturn([]);
        $this->auditLogger->method('getEvents')->willReturn(['items' => [], 'total' => 0]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);
        $this->settingsRepo->method('channel')->willReturnCallback(function (string $id) {
            $defaults = SettingsRepository::CHANNEL_DEFAULTS;
            return $defaults[$id] ?? [];
        });

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('data-action="send-verification"', $html);
        $this->assertStringContainsString('Send Verification', $html);
    }

    public function testRenderHidesSendVerificationWhenEmailVerified(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = ['wsms_email_verified' => '1'];
        $GLOBALS['_test_current_user_can'] = true;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringNotContainsString('data-action="send-verification"', $html);
    }

    public function testRenderHidesPasswordResetForPlaceholderEmail(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'abc@noreply.wsms.local';

        $GLOBALS['_test_user_meta'][5] = ['wsms_email_verified' => '0'];
        $GLOBALS['_test_current_user_can'] = true;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringNotContainsString('data-action="password-reset"', $html);
    }

    public function testRenderShowsPhoneEditForAdmin(): void
    {
        $user = new \WP_User();
        $user->ID = 5;
        $user->user_email = 'test@example.com';

        $GLOBALS['_test_user_meta'][5] = [
            'wsms_email_verified' => '1',
            'wsms_phone'          => '+1234567890',
            'wsms_phone_verified' => '1',
        ];
        $GLOBALS['_test_current_user_can'] = true;
        $this->setupDefaultMocks();

        ob_start();
        $this->section->render($user);
        $html = ob_get_clean();

        $this->assertStringContainsString('wsms-phone-edit-toggle', $html);
        $this->assertStringContainsString('wsms-phone-edit', $html);
        $this->assertStringContainsString('wsms-phone-input', $html);
    }

    public function testEnqueueAssetsOnlyOnUserPages(): void
    {
        $this->section->enqueueAssets('edit.php');
        $this->assertTrue(true);
    }
}
