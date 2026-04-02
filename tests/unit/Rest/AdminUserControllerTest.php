<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\SettingsRepository;
use WSms\Auth\ValueObjects\OperationResult;
use WSms\Enums\EventType;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Template\TemplateManager;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\MfaManager;
use WSms\Dependencies\Psr\Log\LoggerInterface;
use WSms\Rest\AdminUserController;
use WSms\Social\SocialAccountRepository;

class AdminUserControllerTest extends TestCase
{
    private AdminUserController $controller;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&MfaManager $mfaManager;
    private MockObject&SocialAccountRepository $socialRepository;
    private MockObject&AccountLockout $lockout;
    private MockObject&AccountManager $accountManager;
    private MockObject&SettingsRepository $settingsRepo;
    private MockObject&TemplateManager $templateManager;
    private MockObject&MessageDispatcher $messageDispatcher;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->mfaManager = $this->createMock(MfaManager::class);
        $this->socialRepository = $this->createMock(SocialAccountRepository::class);
        $this->lockout = $this->createMock(AccountLockout::class);
        $this->accountManager = $this->createMock(AccountManager::class);
        $this->settingsRepo = $this->createMock(SettingsRepository::class);
        $this->templateManager = $this->createMock(TemplateManager::class);
        $this->messageDispatcher = $this->createMock(MessageDispatcher::class);

        $this->controller = new AdminUserController(
            $this->auditLogger,
            $this->mfaManager,
            $this->socialRepository,
            $this->lockout,
            $this->accountManager,
            $this->settingsRepo,
            $this->templateManager,
            $this->messageDispatcher,
            $this->createMock(LoggerInterface::class),
        );

        $GLOBALS['_test_user_meta'] = [];
        unset(
            $GLOBALS['_test_current_user_can'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_current_user_id'],
            $GLOBALS['_test_get_users_result'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_user_meta'] = [];
        unset(
            $GLOBALS['_test_current_user_can'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_current_user_id'],
            $GLOBALS['_test_get_users_result'],
        );
    }

    // --- Permission ---

    public function testCanManageReturnsTrueWhenCapable(): void
    {
        $GLOBALS['_test_current_user_can'] = true;
        $this->assertTrue($this->controller->canManage());
    }

    public function testCanManageReturnsFalseWhenNotCapable(): void
    {
        $GLOBALS['_test_current_user_can'] = false;
        $this->assertFalse($this->controller->canManage());
    }

    // --- Auth Summary ---

    public function testGetAuthSummaryReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('GET', '/auth/admin/users/999/auth-summary');
        $request->set_param('id', 999);

        $response = $this->controller->handleGetAuthSummary($request);

        $this->assertSame(404, $response->get_status());
        $this->assertSame('not_found', $response->get_data()['error']);
    }

    public function testGetAuthSummaryReturnsFullData(): void
    {
        $user = $this->makeUser(5);
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][5] = [
            'wsms_email_verified' => '1',
            'wsms_phone_verified' => '0',
            'wsms_phone'          => '+1234567890',
        ];

        $this->mfaManager->method('getActiveMfaFactors')->with(5)->willReturn([
            ['channel_id' => 'totp', 'name' => 'Authenticator App'],
        ]);

        $this->socialRepository->method('findByUserId')->with(5)->willReturn([
            (object) [
                'channel_id' => 'google',
                'meta'       => json_encode(['email' => 'user@gmail.com']),
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        $this->auditLogger->method('getEvents')->willReturn([
            'items' => [(object) ['event' => 'login_success', 'status' => 'success', 'created_at' => '2026-01-01']],
            'total' => 1,
        ]);

        $this->lockout->method('isLocked')->with(5)->willReturn([
            'locked' => false, 'until' => null, 'attempts' => 0,
        ]);

        $request = new \WP_REST_Request('GET', '/auth/admin/users/5/auth-summary');
        $request->set_param('id', 5);

        $response = $this->controller->handleGetAuthSummary($request);

        $this->assertSame(200, $response->get_status());
        $responseData = $response->get_data();
        $this->assertTrue($responseData['success']);
        $data = $responseData['data'];

        // Verification
        $this->assertTrue($data['verification']['email']['verified']);
        $this->assertSame('admin@example.com', $data['verification']['email']['address']);
        $this->assertFalse($data['verification']['phone']['verified']);
        $this->assertSame('+1234567890', $data['verification']['phone']['number']);

        // MFA
        $this->assertCount(1, $data['mfa_factors']);
        $this->assertSame('totp', $data['mfa_factors'][0]['channel_id']);

        // Social
        $this->assertCount(1, $data['social_accounts']);
        $this->assertSame('google', $data['social_accounts'][0]['provider']);
        $this->assertSame('user@gmail.com', $data['social_accounts'][0]['email']);

        // Activity
        $this->assertCount(1, $data['recent_activity']);

        // New fields
        $this->assertArrayHasKey('lockout', $data);
        $this->assertFalse($data['lockout']['locked']);
        $this->assertArrayHasKey('has_password', $data);
        $this->assertArrayHasKey('is_placeholder_email', $data);
    }

    public function testGetAuthSummaryWithNoPhoneReturnsNull(): void
    {
        $user = $this->makeUser(5);
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][5] = [
            'wsms_email_verified' => '0',
        ];

        $this->mfaManager->method('getActiveMfaFactors')->willReturn([]);
        $this->socialRepository->method('findByUserId')->willReturn([]);
        $this->auditLogger->method('getEvents')->willReturn(['items' => [], 'total' => 0]);
        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $request = new \WP_REST_Request('GET', '/auth/admin/users/5/auth-summary');
        $request->set_param('id', 5);

        $response = $this->controller->handleGetAuthSummary($request);
        $data = $response->get_data()['data'];

        $this->assertNull($data['verification']['phone']['number']);
        $this->assertEmpty($data['mfa_factors']);
        $this->assertEmpty($data['social_accounts']);
    }

    // --- Reset MFA Channel ---

    public function testResetMfaChannelReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/999/mfa/totp');
        $request->set_param('id', 999);
        $request->set_param('channel', 'totp');

        $response = $this->controller->handleResetMfaChannel($request);

        $this->assertSame(404, $response->get_status());
        $this->assertSame('not_found', $response->get_data()['error']);
    }

    public function testResetMfaChannelReturns404ForUnknownChannel(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);

        $this->mfaManager->method('getChannel')->with('invalid')->willReturn(null);

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/5/mfa/invalid');
        $request->set_param('id', 5);
        $request->set_param('channel', 'invalid');

        $response = $this->controller->handleResetMfaChannel($request);

        $this->assertSame(404, $response->get_status());
        $this->assertSame('not_found', $response->get_data()['error']);
    }

    public function testResetMfaChannelSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getName')->willReturn('Authenticator App');
        $channel->expects($this->once())->method('unenroll')->with(5);

        $this->mfaManager->method('getChannel')->with('totp')->willReturn($channel);
        $this->mfaManager->method('hasActiveFactors')->with(5)->willReturn(false);
        $this->auditLogger->expects($this->once())->method('log')
            ->with(EventType::MfaAdminBypass, 'success', 5, $this->callback(function (array $meta) {
                return $meta['channel'] === 'totp' && $meta['admin_id'] === 1;
            }), 'totp');

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/5/mfa/totp');
        $request->set_param('id', 5);
        $request->set_param('channel', 'totp');

        $response = $this->controller->handleResetMfaChannel($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertSame('0', $GLOBALS['_test_user_meta'][5]['wsms_mfa_enabled']);
    }

    public function testResetMfaChannelDoesNotDisableMfaWhenOtherFactorsExist(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getName')->willReturn('Email OTP');
        $channel->method('unenroll')->willReturn(true);

        $this->mfaManager->method('getChannel')->with('email')->willReturn($channel);
        $this->mfaManager->method('hasActiveFactors')->with(5)->willReturn(true);
        $this->auditLogger->expects($this->once())->method('log');

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/5/mfa/email');
        $request->set_param('id', 5);
        $request->set_param('channel', 'email');

        $this->controller->handleResetMfaChannel($request);

        $this->assertArrayNotHasKey('wsms_mfa_enabled', $GLOBALS['_test_user_meta'][5] ?? []);
    }

    // --- Set Verification ---

    public function testSetVerificationReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('PUT', '/auth/admin/users/999/verification');
        $request->set_param('id', 999);
        $request->set_param('channel', 'email');
        $request->set_param('verified', true);

        $response = $this->controller->handleSetVerification($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testSetVerificationRejectsInvalidChannel(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);

        $request = new \WP_REST_Request('PUT', '/auth/admin/users/5/verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'sms');
        $request->set_param('verified', true);

        $response = $this->controller->handleSetVerification($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testSetEmailVerifiedSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;

        $this->auditLogger->expects($this->once())->method('log')
            ->with(EventType::EmailVerified, 'success', 5, $this->callback(function (array $meta) {
                return $meta['admin_override'] === true && $meta['verified'] === true;
            }), 'email');

        $request = new \WP_REST_Request('PUT', '/auth/admin/users/5/verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'email');
        $request->set_param('verified', true);

        $response = $this->controller->handleSetVerification($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertSame('1', $GLOBALS['_test_user_meta'][5]['wsms_email_verified']);
    }

    public function testSetPhoneUnverifiedSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][5] = ['wsms_phone_verified' => '1'];

        $this->auditLogger->expects($this->once())->method('log')
            ->with(EventType::PhoneVerified, 'success', 5, $this->anything(), 'phone');

        $request = new \WP_REST_Request('PUT', '/auth/admin/users/5/verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'phone');
        $request->set_param('verified', false);

        $response = $this->controller->handleSetVerification($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('0', $GLOBALS['_test_user_meta'][5]['wsms_phone_verified']);
    }

    // --- Disconnect Social ---

    public function testDisconnectSocialReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/999/social/google');
        $request->set_param('id', 999);
        $request->set_param('provider', 'google');

        $response = $this->controller->handleDisconnectSocial($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testDisconnectSocialRejectsInvalidProvider(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);

        $this->socialRepository->method('isKnownProvider')->with('invalid')->willReturn(false);

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/5/social/invalid');
        $request->set_param('id', 5);
        $request->set_param('provider', 'invalid');

        $response = $this->controller->handleDisconnectSocial($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testDisconnectSocialSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;

        $this->socialRepository->method('isKnownProvider')->with('google')->willReturn(true);
        $this->socialRepository->expects($this->once())->method('unlinkAccount')->with(5, 'google');
        $this->auditLogger->expects($this->once())->method('log')
            ->with(EventType::SocialAccountUnlinked, 'success', 5, $this->callback(function (array $meta) {
                return $meta['provider'] === 'google' && $meta['admin_id'] === 1;
            }));

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/5/social/google');
        $request->set_param('id', 5);
        $request->set_param('provider', 'google');

        $response = $this->controller->handleDisconnectSocial($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    // --- Unlock Account ---

    public function testUnlockReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/999/lockout');
        $request->set_param('id', 999);

        $response = $this->controller->handleUnlockAccount($request);

        $this->assertSame(404, $response->get_status());
        $this->assertSame('not_found', $response->get_data()['error']);
    }

    public function testUnlockSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;

        $this->lockout->expects($this->once())->method('reset')->with(5);
        $this->auditLogger->expects($this->once())->method('log')
            ->with(EventType::AccountUnlocked, 'success', 5, $this->callback(function (array $meta) {
                return $meta['admin_id'] === 1;
            }));

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/5/lockout');
        $request->set_param('id', 5);

        $response = $this->controller->handleUnlockAccount($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertSame('Account unlocked.', $response->get_data()['data']['message']);
    }

    public function testUnlockIsIdempotent(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;

        $this->lockout->expects($this->once())->method('reset')->with(5);
        $this->auditLogger->expects($this->once())->method('log');

        $request = new \WP_REST_Request('DELETE', '/auth/admin/users/5/lockout');
        $request->set_param('id', 5);

        $response = $this->controller->handleUnlockAccount($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    // --- Set Phone ---

    public function testSetPhoneReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('PUT', '/auth/admin/users/999/phone');
        $request->set_param('id', 999);
        $request->set_param('phone', '+1234567890');

        $response = $this->controller->handleSetPhone($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testSetPhoneSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_get_users_result'] = [];

        $this->auditLogger->expects($this->once())->method('log')
            ->with(EventType::PhoneChange, 'success', 5, $this->callback(function (array $meta) {
                return $meta['admin_override'] === true
                    && $meta['action'] === 'phone_set'
                    && $meta['phone'] === '+1234567890';
            }));

        $request = new \WP_REST_Request('PUT', '/auth/admin/users/5/phone');
        $request->set_param('id', 5);
        $request->set_param('phone', '+1234567890');

        $response = $this->controller->handleSetPhone($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertSame('+1234567890', $GLOBALS['_test_user_meta'][5]['wsms_phone']);
        $this->assertSame('0', $GLOBALS['_test_user_meta'][5]['wsms_phone_verified']);
    }

    public function testSetPhoneRejectsInvalidFormat(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);

        $request = new \WP_REST_Request('PUT', '/auth/admin/users/5/phone');
        $request->set_param('id', 5);
        $request->set_param('phone', '1234567890'); // missing +

        $response = $this->controller->handleSetPhone($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testSetPhoneRejectsTakenNumber(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $otherUser = new \stdClass();
        $otherUser->ID = 10;
        $GLOBALS['_test_get_users_result'] = [$otherUser];

        $request = new \WP_REST_Request('PUT', '/auth/admin/users/5/phone');
        $request->set_param('id', 5);
        $request->set_param('phone', '+1234567890');

        $response = $this->controller->handleSetPhone($request);

        $this->assertSame(409, $response->get_status());
        $this->assertSame('conflict', $response->get_data()['error']);
    }

    public function testRemovePhoneSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][5] = [
            'wsms_phone'          => '+1234567890',
            'wsms_phone_verified' => '1',
        ];

        $this->auditLogger->expects($this->once())->method('log')
            ->with(EventType::PhoneChange, 'success', 5, $this->callback(function (array $meta) {
                return $meta['action'] === 'phone_removed';
            }));

        $request = new \WP_REST_Request('PUT', '/auth/admin/users/5/phone');
        $request->set_param('id', 5);
        $request->set_param('phone', '');

        $response = $this->controller->handleSetPhone($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertArrayNotHasKey('wsms_phone', $GLOBALS['_test_user_meta'][5] ?? []);
        $this->assertArrayNotHasKey('wsms_phone_verified', $GLOBALS['_test_user_meta'][5] ?? []);
    }

    // --- Password Reset ---

    public function testPasswordResetReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('POST', '/auth/admin/users/999/password-reset');
        $request->set_param('id', 999);

        $response = $this->controller->handlePasswordReset($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testPasswordResetRejectsPlaceholderEmail(): void
    {
        $user = $this->makeUser(5);
        $user->user_email = 'abc123@noreply.wsms.local';
        $GLOBALS['_test_userdata'] = $user;

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/password-reset');
        $request->set_param('id', 5);

        $response = $this->controller->handlePasswordReset($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testPasswordResetSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;

        $this->accountManager->expects($this->once())
            ->method('initiatePasswordReset')
            ->with('admin@example.com');

        $this->auditLogger->expects($this->once())->method('log')
            ->with(EventType::PasswordResetRequest, 'success', 5, $this->callback(function (array $meta) {
                return $meta['admin_initiated'] === true && $meta['admin_id'] === 1;
            }));

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/password-reset');
        $request->set_param('id', 5);

        $response = $this->controller->handlePasswordReset($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertSame('Password reset email sent.', $response->get_data()['data']['message']);
    }

    // --- Activate User ---

    public function testActivateReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('POST', '/auth/admin/users/999/activate');
        $request->set_param('id', 999);

        $response = $this->controller->handleActivateUser($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testActivateRejectsNonPendingUser(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_user_meta'][5] = ['wsms_registration_status' => 'active'];

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/activate');
        $request->set_param('id', 5);

        $response = $this->controller->handleActivateUser($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testActivateRejectsUserWithNoStatus(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_user_meta'][5] = [];

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/activate');
        $request->set_param('id', 5);

        $response = $this->controller->handleActivateUser($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testActivateSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_current_user_id'] = 1;
        $GLOBALS['_test_user_meta'][5] = [
            'wsms_registration_status'     => 'pending',
            'wsms_registration_created_at' => '2026-01-01 00:00:00',
        ];

        $this->auditLogger->expects($this->once())->method('log')
            ->with(EventType::Register, 'success', 5, $this->callback(function (array $meta) {
                return $meta['admin_override'] === true && $meta['admin_id'] === 1;
            }));

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/activate');
        $request->set_param('id', 5);

        $response = $this->controller->handleActivateUser($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertSame('active', $GLOBALS['_test_user_meta'][5]['wsms_registration_status']);
        $this->assertArrayNotHasKey('wsms_registration_created_at', $GLOBALS['_test_user_meta'][5]);
    }

    // --- Send Verification ---

    public function testSendVerificationReturns404ForUnknownUser(): void
    {
        $GLOBALS['_test_userdata'] = false;

        $request = new \WP_REST_Request('POST', '/auth/admin/users/999/send-verification');
        $request->set_param('id', 999);
        $request->set_param('channel', 'email');

        $response = $this->controller->handleSendVerification($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testSendVerificationRejectsInvalidChannel(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/send-verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'sms');

        $response = $this->controller->handleSendVerification($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testSendVerificationRejectsDisabledChannel(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $this->settingsRepo->method('channel')->with('phone')->willReturn(['enabled' => false]);

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/send-verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'phone');

        $response = $this->controller->handleSendVerification($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testSendVerificationRejectsPlaceholderEmail(): void
    {
        $user = $this->makeUser(5);
        $user->user_email = 'abc123@noreply.wsms.local';
        $GLOBALS['_test_userdata'] = $user;
        $this->settingsRepo->method('channel')->with('email')->willReturn(['enabled' => true]);

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/send-verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'email');

        $response = $this->controller->handleSendVerification($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testSendVerificationRejectsNoPhone(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_user_meta'][5] = [];
        $this->settingsRepo->method('channel')->with('phone')->willReturn(['enabled' => true]);

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/send-verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'phone');

        $response = $this->controller->handleSendVerification($request);

        $this->assertSame(422, $response->get_status());
        $this->assertSame('validation_failed', $response->get_data()['error']);
    }

    public function testSendEmailVerificationSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $this->settingsRepo->method('channel')->with('email')->willReturn(['enabled' => true]);

        $this->accountManager->expects($this->once())
            ->method('resendVerification')
            ->with(5, 'email')
            ->willReturn(OperationResult::ok('Verification resent.'));

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/send-verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'email');

        $response = $this->controller->handleSendVerification($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testSendPhoneVerificationSucceeds(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $GLOBALS['_test_user_meta'][5] = ['wsms_phone' => '+1234567890'];
        $this->settingsRepo->method('channel')->with('phone')->willReturn(['enabled' => true]);

        $this->accountManager->expects($this->once())
            ->method('resendVerification')
            ->with(5, 'phone')
            ->willReturn(OperationResult::ok('Verification resent.'));

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/send-verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'phone');

        $response = $this->controller->handleSendVerification($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testSendVerificationReturns400OnCooldown(): void
    {
        $GLOBALS['_test_userdata'] = $this->makeUser(5);
        $this->settingsRepo->method('channel')->with('email')->willReturn(['enabled' => true]);

        $this->accountManager->expects($this->once())
            ->method('resendVerification')
            ->with(5, 'email')
            ->willReturn(OperationResult::fail('cooldown', 'Please wait before requesting a new code.'));

        $request = new \WP_REST_Request('POST', '/auth/admin/users/5/send-verification');
        $request->set_param('id', 5);
        $request->set_param('channel', 'email');

        $response = $this->controller->handleSendVerification($request);

        $this->assertSame(400, $response->get_status());
        $this->assertFalse($response->get_data()['success']);
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
