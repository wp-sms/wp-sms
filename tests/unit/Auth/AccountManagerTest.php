<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthSession;
use WSms\Enums\VerificationType;
use WSms\Mfa\MfaManager;
use WSms\Mfa\OtpGenerator;

class AccountManagerTest extends TestCase
{
    private AccountManager $manager;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&OtpGenerator $otpGenerator;
    private MockObject&MfaManager $mfaManager;
    private MockObject&AuthSession $authSession;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->mfaManager = $this->createMock(MfaManager::class);
        $this->authSession = $this->createMock(AuthSession::class);

        $this->manager = new AccountManager(
            $this->auditLogger,
            $this->otpGenerator,
            $this->mfaManager,
            $this->authSession,
        );

        $this->otpGenerator->method('generateToken')->willReturn('test-token-abc');
        $this->otpGenerator->method('hash')->willReturn('hashed-token-abc');
        $this->authSession->method('create')->willReturn('reg-session-token');

        // Stub $wpdb.
        $wpdb = new \stdClass();
        $wpdb->prefix = 'wp_';
        $wpdb->insert_id = 1;
        $GLOBALS['wpdb'] = $wpdb;

        // Allow insert to succeed by default.
        $wpdb->insert = function () {
            return true;
        };

        unset(
            $GLOBALS['_test_wp_insert_user_result'],
            $GLOBALS['_test_wp_insert_user_data'],
            $GLOBALS['_test_wp_check_password_result'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_current_user_id'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wpdb'],
            $GLOBALS['_test_wp_insert_user_result'],
            $GLOBALS['_test_wp_insert_user_data'],
            $GLOBALS['_test_wp_check_password_result'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_current_user_id'],
        );
    }

    // --- registerUser ---

    public function testRegisterUserSucceeds(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;
        $this->stubWpdb();

        $this->auditLogger->expects($this->once())->method('log');

        $result = $this->manager->registerUser([
            'email'    => 'new@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['user_id']);
        $this->assertSame('Registration successful.', $result['message']);
    }

    public function testRegisterUserRequiresMfaOnRegistration(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 10;
        $this->stubWpdb();

        // Simulate on_registration enrollment timing.
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'enrollment_timing'   => 'on_registration',
        ];

        // Override get_option to use test options.
        $result = $this->manager->registerUser([
            'email'    => 'mfa@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testRegisterUserFailsWithDuplicateEmail(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = new \WP_Error('existing_user_email', 'Email already exists.');
        $this->stubWpdb();

        $result = $this->manager->registerUser([
            'email'    => 'taken@example.com',
            'password' => 'Pass123!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('existing_user_email', $result['error']);
    }

    public function testRegisterUserFailsWhenPhoneRequiredButMissing(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'phone' => ['enabled' => true, 'required_at_signup' => true],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_phone', $result['error']);
    }

    public function testRegisterUserSucceedsWhenPhoneRequiredAndProvided(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 50;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'phone' => ['required_at_signup' => true],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
            'phone'    => '+1234567890',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testRegisterUserFailsWhenFirstNameRequired(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password', 'first_name'],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_first_name', $result['error']);
    }

    public function testRegisterUserFailsWithMissingEmail(): void
    {
        $result = $this->manager->registerUser([
            'password' => 'Pass123!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_email', $result['error']);
    }

    public function testRegisterUserFailsWithMissingPassword(): void
    {
        $result = $this->manager->registerUser([
            'email' => 'test@example.com',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_password', $result['error']);
    }

    public function testRegisterUserNoPendingWhenVerifyAtSignupDisabled(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 60;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'phone' => ['required_at_signup' => true],
            'email' => [],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
            'phone'    => '+1234567890',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('pending_verifications', $result);
        $this->assertArrayNotHasKey('registration_token', $result);
    }

    public function testRegisterUserPendingEmailWhenVerifyAtSignupEnabled(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 61;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'email' => ['verify_at_signup' => true],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'verify@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['pending_verifications']);
        $this->assertSame('email', $result['pending_verifications'][0]['type']);
        $this->assertArrayHasKey('registration_token', $result);
    }

    public function testRegisterUserPendingPhoneWhenVerifyAtSignupEnabled(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 62;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'phone' => ['enabled' => true, 'required_at_signup' => true, 'verify_at_signup' => true],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
            'phone'    => '+1234567890',
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['pending_verifications']);
        $this->assertSame('phone', $result['pending_verifications'][0]['type']);
        $this->assertArrayHasKey('registration_token', $result);
    }

    public function testRegisterUserBothPendingWhenBothEnabled(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 63;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'phone' => ['enabled' => true, 'required_at_signup' => true, 'verify_at_signup' => true],
            'email' => ['verify_at_signup' => true],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'both@example.com',
            'password' => 'StrongPass1!',
            'phone'    => '+1234567890',
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['pending_verifications']);

        $types = array_column($result['pending_verifications'], 'type');
        $this->assertContains('phone', $types);
        $this->assertContains('email', $types);
        $this->assertArrayHasKey('registration_token', $result);
    }

    // --- initiatePasswordReset ---

    public function testInitiatePasswordResetCreatesVerification(): void
    {
        $user = $this->makeUser(5);
        $GLOBALS['_test_get_user_by_result'] = $user;
        $this->stubWpdb();

        $this->auditLogger->expects($this->once())->method('log');

        // Should not throw.
        $this->manager->initiatePasswordReset('user@example.com');
        $this->addToAssertionCount(1);
    }

    public function testInitiatePasswordResetSilentlyIgnoresUnknownEmail(): void
    {
        $GLOBALS['_test_get_user_by_result'] = false;

        $this->auditLogger->expects($this->never())->method('log');

        // Should not throw.
        $this->manager->initiatePasswordReset('nobody@example.com');
        $this->addToAssertionCount(1);
    }

    // --- completePasswordReset ---

    public function testCompletePasswordResetSucceeds(): void
    {
        $verification = $this->makeVerification(1, VerificationType::PasswordReset->value);
        $this->stubWpdbLookup($verification);

        $this->auditLogger->expects($this->once())->method('log');

        $result = $this->manager->completePasswordReset('test-token-abc', 'NewPass1!');

        $this->assertTrue($result['success']);
        $this->assertSame('Password has been reset successfully.', $result['message']);
    }

    public function testCompletePasswordResetFailsWithExpiredToken(): void
    {
        $verification = $this->makeVerification(1, VerificationType::PasswordReset->value, expired: true);
        $this->stubWpdbLookup($verification);

        $result = $this->manager->completePasswordReset('test-token-abc', 'NewPass1!');

        $this->assertFalse($result['success']);
        $this->assertSame('expired_token', $result['error']);
    }

    public function testCompletePasswordResetFailsWithUsedToken(): void
    {
        $verification = $this->makeVerification(1, VerificationType::PasswordReset->value, used: true);
        $this->stubWpdbLookup($verification);

        $result = $this->manager->completePasswordReset('test-token-abc', 'NewPass1!');

        $this->assertFalse($result['success']);
        $this->assertSame('used_token', $result['error']);
    }

    public function testCompletePasswordResetFailsWithInvalidToken(): void
    {
        $this->stubWpdbLookup(null);

        $result = $this->manager->completePasswordReset('bad-token', 'NewPass1!');

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_token', $result['error']);
    }

    // --- verifyEmail ---

    public function testVerifyEmailSucceeds(): void
    {
        $verification = $this->makeVerification(3, VerificationType::EmailVerify->value);
        $this->stubWpdbLookup($verification);

        $this->auditLogger->expects($this->once())->method('log');

        $result = $this->manager->verifyEmail('test-token-abc');

        $this->assertTrue($result['success']);
        $this->assertSame('Email verified successfully.', $result['message']);
    }

    // --- updateProfile ---

    public function testUpdateProfileDisplayName(): void
    {
        $result = $this->manager->updateProfile(1, ['display_name' => 'New Name']);

        $this->assertTrue($result['success']);
    }

    public function testUpdateProfilePhoneTriggersVerification(): void
    {
        $this->stubWpdb();

        // Set current phone to something different so the change is detected.
        $GLOBALS['_test_user_meta'][1] = ['wsms_phone' => '+0000000000'];

        $result = $this->manager->updateProfile(1, ['phone' => '+1234567890']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['phone_verification_required']);
        // Phone should be stored as pending, NOT overwritten.
        $this->assertSame('+1234567890', $GLOBALS['_test_user_meta'][1]['wsms_pending_phone']);
        // Original phone should be preserved.
        $this->assertSame('+0000000000', $GLOBALS['_test_user_meta'][1]['wsms_phone']);
    }

    public function testUpdateProfileEmailTriggersVerification(): void
    {
        $this->stubWpdb();

        // Set current email to something different so the change is detected.
        $user = $this->makeUser(1);
        $user->user_email = 'old@example.com';
        $GLOBALS['_test_userdata'] = $user;

        $result = $this->manager->updateProfile(1, ['email' => 'new@example.com']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['email_verification_required']);
    }

    public function testUpdateProfileRejectsInvalidEmail(): void
    {
        $result = $this->manager->updateProfile(1, ['email' => 'not-an-email']);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_email', $result['error']);
    }

    public function testUpdateProfilePhoneSameValueSkipsVerification(): void
    {
        $GLOBALS['_test_user_meta'][1] = ['wsms_phone' => '+1234567890'];

        $result = $this->manager->updateProfile(1, ['phone' => '+1234567890']);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('phone_verification_required', $result);
    }

    public function testUpdateProfileEmailSameValueSkipsVerification(): void
    {
        $user = $this->makeUser(1);
        $user->user_email = 'same@example.com';
        $GLOBALS['_test_userdata'] = $user;

        $result = $this->manager->updateProfile(1, ['email' => 'same@example.com']);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('email_verification_required', $result);
    }

    public function testUpdateProfilePhonePreservesOldPhone(): void
    {
        $this->stubWpdb();
        $GLOBALS['_test_user_meta'][1] = ['wsms_phone' => '+1111111111', 'wsms_phone_verified' => '1'];

        $result = $this->manager->updateProfile(1, ['phone' => '+2222222222']);

        $this->assertTrue($result['success']);
        // Old phone preserved.
        $this->assertSame('+1111111111', $GLOBALS['_test_user_meta'][1]['wsms_phone']);
        // Verified status NOT reset (old phone still verified).
        $this->assertSame('1', $GLOBALS['_test_user_meta'][1]['wsms_phone_verified']);
        // New phone stored as pending.
        $this->assertSame('+2222222222', $GLOBALS['_test_user_meta'][1]['wsms_pending_phone']);
    }

    public function testCancelPendingChangeRemovesPendingMeta(): void
    {
        $this->stubWpdb();
        $GLOBALS['_test_user_meta'][1] = [
            'wsms_phone'         => '+1111111111',
            'wsms_pending_phone' => '+2222222222',
        ];

        $this->manager->cancelPendingChange(1, 'phone');

        $this->assertArrayNotHasKey('wsms_pending_phone', $GLOBALS['_test_user_meta'][1]);
        $this->assertSame('+1111111111', $GLOBALS['_test_user_meta'][1]['wsms_phone']);
    }

    // --- changePassword ---

    public function testChangePasswordSucceeds(): void
    {
        $user = $this->makeUser(1);
        $user->user_pass = 'hashed-pass';
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_wp_check_password_result'] = true;

        $this->auditLogger->expects($this->once())->method('log');

        $result = $this->manager->changePassword(1, 'oldPass', 'newPass');

        $this->assertTrue($result['success']);
        $this->assertSame('Password changed successfully.', $result['message']);
    }

    public function testChangePasswordFailsWithWrongPassword(): void
    {
        $user = $this->makeUser(1);
        $user->user_pass = 'hashed-pass';
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_wp_check_password_result'] = false;

        $result = $this->manager->changePassword(1, 'wrongPass', 'newPass');

        $this->assertFalse($result['success']);
        $this->assertSame('wrong_password', $result['error']);
    }

    // --- logout ---

    public function testLogoutLogsEventAndCallsWpLogout(): void
    {
        $GLOBALS['_test_current_user_id'] = 5;

        $this->auditLogger->expects($this->once())->method('log');

        $this->manager->logout();

        $this->assertSame(0, $GLOBALS['_test_current_user_id']);
    }

    // --- isPlaceholderEmail ---

    public function testIsPlaceholderEmailReturnsTrueForPlaceholder(): void
    {
        $this->assertTrue(AccountManager::isPlaceholderEmail('abc123def0@noreply.wsms.local'));
    }

    public function testIsPlaceholderEmailReturnsFalseForRealEmail(): void
    {
        $this->assertFalse(AccountManager::isPlaceholderEmail('user@example.com'));
    }

    public function testIsPlaceholderEmailReturnsFalseForSimilarDomain(): void
    {
        $this->assertFalse(AccountManager::isPlaceholderEmail('user@notnoreply.wsms.local'));
    }

    // --- Placeholder registration ---

    public function testRegisterUserWithPlaceholderEmailWhenEmailNotRequired(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 100;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email'    => ['required_at_signup' => false],
            'password' => ['required_at_signup' => false],
            'phone'    => ['required_at_signup' => true],
            'registration_fields' => ['phone'],
        ];

        $result = $this->manager->registerUser([
            'phone' => '+1234567890',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(100, $result['user_id']);

        // Placeholder meta should be stored.
        $this->assertSame('1', $GLOBALS['_test_user_meta'][100]['wsms_email_placeholder'] ?? '');
    }

    public function testRegisterUserWithPlaceholderSkipsEmailVerification(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 101;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email'    => ['required_at_signup' => false, 'verify_at_signup' => true],
            'password' => ['required_at_signup' => false],
            'phone'    => ['enabled' => true, 'required_at_signup' => true, 'verify_at_signup' => true],
            'registration_fields' => ['phone'],
        ];

        $result = $this->manager->registerUser([
            'phone' => '+1234567890',
        ]);

        $this->assertTrue($result['success']);

        // Only phone should be pending, not email (since email is a placeholder).
        $pendingTypes = array_column($result['pending_verifications'] ?? [], 'type');
        $this->assertContains('phone', $pendingTypes);
        $this->assertNotContains('email', $pendingTypes);
    }

    public function testRegisterUserPlaceholderGeneratesDummyUsername(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email'    => ['required_at_signup' => false],
            'password' => ['required_at_signup' => false],
            'phone'    => ['required_at_signup' => true],
            'registration_fields' => ['phone'],
        ];

        $GLOBALS['_test_wp_insert_user_result'] = 102;
        $this->stubWpdb();

        $result = $this->manager->registerUser([
            'phone' => '+1234567890',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('1', $GLOBALS['_test_user_meta'][102]['wsms_email_placeholder'] ?? '');

        $capturedUsername = $GLOBALS['_test_wp_insert_user_data']['user_login'] ?? '';
        $this->assertTrue(AccountManager::isPlaceholderUsername($capturedUsername));
        $this->assertStringNotContainsString('+1234567890', $capturedUsername);
    }

    // --- Verification guards for placeholder ---

    public function testResendEmailVerificationRejectsPlaceholderEmail(): void
    {
        $user = $this->makeUser(200);
        $user->user_email = 'abc123def0@noreply.wsms.local';
        $GLOBALS['_test_userdata'] = $user;

        $result = $this->manager->resendVerification(200, 'email');

        $this->assertFalse($result['success']);
        $this->assertSame('no_email', $result['error']);
    }

    public function testGetVerificationStatusExcludesPlaceholderEmail(): void
    {
        $user = $this->makeUser(201);
        $user->user_email = 'abc123def0@noreply.wsms.local';
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_user_meta'][201] = [
            'wsms_email_verified' => '',
            'wsms_phone' => '+1234567890',
            'wsms_phone_verified' => '',
        ];

        $status = $this->manager->getVerificationStatus(201);

        // Only phone should be pending, not email.
        $types = array_column($status['pending_verifications'], 'type');
        $this->assertContains('phone', $types);
        $this->assertNotContains('email', $types);
    }

    public function testVerifyEmailClearsPlaceholderFlag(): void
    {
        $verification = $this->makeVerification(202, VerificationType::EmailVerify->value);
        $this->stubWpdbLookup($verification);

        $GLOBALS['_test_user_meta'][202] = ['wsms_email_placeholder' => '1'];

        $result = $this->manager->verifyEmail('test-token-abc');

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('wsms_email_placeholder', $GLOBALS['_test_user_meta'][202] ?? []);
    }

    // --- Registration status meta ---

    public function testRegisterUserSetsActiveStatusWhenNoVerificationRequired(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 70;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
        ];

        $this->manager->registerUser([
            'email'    => 'active@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertSame('active', $GLOBALS['_test_user_meta'][70]['wsms_registration_status'] ?? '');
        $this->assertArrayNotHasKey('wsms_registration_created_at', $GLOBALS['_test_user_meta'][70] ?? []);
    }

    public function testRegisterUserSetsPendingStatusWhenEmailVerifyRequired(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 71;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'email' => ['verify_at_signup' => true],
        ];

        $this->manager->registerUser([
            'email'    => 'pending@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertSame('pending', $GLOBALS['_test_user_meta'][71]['wsms_registration_status'] ?? '');
        $this->assertNotEmpty($GLOBALS['_test_user_meta'][71]['wsms_registration_created_at'] ?? '');
    }

    public function testRegisterUserSetsPendingStatusWhenPhoneVerifyRequired(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 72;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'phone' => ['enabled' => true, 'required_at_signup' => true, 'verify_at_signup' => true],
        ];

        $this->manager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
            'phone'    => '+1234567890',
        ]);

        $this->assertSame('pending', $GLOBALS['_test_user_meta'][72]['wsms_registration_status'] ?? '');
    }

    public function testRegisterUserPhoneOnlySetsActiveWhenNoVerify(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 73;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email'    => ['required_at_signup' => false],
            'password' => ['required_at_signup' => false],
            'phone'    => ['required_at_signup' => true],
            'registration_fields' => ['phone'],
        ];

        $this->manager->registerUser([
            'phone' => '+1234567890',
        ]);

        $this->assertSame('active', $GLOBALS['_test_user_meta'][73]['wsms_registration_status'] ?? '');
    }

    public function testRegisterUserPhoneOnlySetsPendingWhenVerifyRequired(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 74;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email'    => ['required_at_signup' => false, 'verify_at_signup' => true],
            'password' => ['required_at_signup' => false],
            'phone'    => ['enabled' => true, 'required_at_signup' => true, 'verify_at_signup' => true],
            'registration_fields' => ['phone'],
        ];

        $this->manager->registerUser([
            'phone' => '+1234567890',
        ]);

        // Pending because phone verify_at_signup is true (email verify skipped for placeholder).
        $this->assertSame('pending', $GLOBALS['_test_user_meta'][74]['wsms_registration_status'] ?? '');
    }

    // --- maybeActivateUser ---

    public function testMaybeActivateUserActivatesWhenAllVerified(): void
    {
        $GLOBALS['_test_user_meta'][80] = [
            'wsms_registration_status'     => 'pending',
            'wsms_registration_created_at' => '2026-01-01 00:00:00',
            'wsms_email_verified'          => '1',
            'wsms_phone'                   => '+1234567890',
            'wsms_phone_verified'          => '1',
        ];

        $user = $this->makeUser(80);
        $user->user_email = 'test@example.com';
        $GLOBALS['_test_userdata'] = $user;

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email' => ['verify_at_signup' => true],
            'phone' => ['enabled' => true, 'verify_at_signup' => true],
        ];

        $this->manager->maybeActivateUser(80);

        $this->assertSame('active', $GLOBALS['_test_user_meta'][80]['wsms_registration_status']);
        $this->assertArrayNotHasKey('wsms_registration_created_at', $GLOBALS['_test_user_meta'][80]);
    }

    public function testMaybeActivateUserStaysPendingWhenEmailUnverified(): void
    {
        $GLOBALS['_test_user_meta'][81] = [
            'wsms_registration_status'     => 'pending',
            'wsms_registration_created_at' => '2026-01-01 00:00:00',
            'wsms_email_verified'          => '',
        ];

        $user = $this->makeUser(81);
        $user->user_email = 'test@example.com';
        $GLOBALS['_test_userdata'] = $user;

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email' => ['verify_at_signup' => true],
        ];

        $this->manager->maybeActivateUser(81);

        $this->assertSame('pending', $GLOBALS['_test_user_meta'][81]['wsms_registration_status']);
    }

    public function testMaybeActivateUserAutoActivatesWhenSettingsDisabled(): void
    {
        $GLOBALS['_test_user_meta'][82] = [
            'wsms_registration_status'     => 'pending',
            'wsms_registration_created_at' => '2026-01-01 00:00:00',
            'wsms_email_verified'          => '',
        ];

        $user = $this->makeUser(82);
        $user->user_email = 'test@example.com';
        $GLOBALS['_test_userdata'] = $user;

        // Admin disabled all verify_at_signup.
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email' => ['verify_at_signup' => false],
            'phone' => ['verify_at_signup' => false],
        ];

        $this->manager->maybeActivateUser(82);

        $this->assertSame('active', $GLOBALS['_test_user_meta'][82]['wsms_registration_status']);
    }

    public function testMaybeActivateUserSkipsAlreadyActiveUsers(): void
    {
        $GLOBALS['_test_user_meta'][83] = [
            'wsms_registration_status' => 'active',
        ];

        $this->manager->maybeActivateUser(83);

        // Should remain active, not call getSettings or anything else.
        $this->assertSame('active', $GLOBALS['_test_user_meta'][83]['wsms_registration_status']);
    }

    public function testMaybeActivateUserSkipsUsersWithNoStatus(): void
    {
        // Legacy user with no status meta — should be a no-op.
        $this->manager->maybeActivateUser(84);

        $this->assertArrayNotHasKey(84, $GLOBALS['_test_user_meta']);
    }

    // --- Re-registration with expired pending users ---

    public function testRegisterDeletesExpiredPendingUserByEmail(): void
    {
        $GLOBALS['_test_deleted_users'] = [];

        // Expired pending user exists.
        $existingUser = $this->makeUser(90);
        $existingUser->user_email = 'reuse@example.com';
        $GLOBALS['_test_get_user_by_result'] = $existingUser;
        $GLOBALS['_test_user_meta'][90] = [
            'wsms_registration_status'     => 'pending',
            'wsms_registration_created_at' => gmdate('Y-m-d H:i:s', time() - 90000), // 25 hours ago
        ];

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields'     => ['email', 'password'],
            'email'                   => ['verify_at_signup' => true],
            'pending_user_ttl_hours'  => 24,
        ];

        // After deletion, wp_insert_user should succeed.
        $GLOBALS['_test_wp_insert_user_result'] = 91;
        $this->stubWpdb();

        $result = $this->manager->registerUser([
            'email'    => 'reuse@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertContains(90, $GLOBALS['_test_deleted_users']);
        $this->assertTrue($result['success']);
    }

    public function testRegisterDoesNotDeleteNonExpiredPendingUser(): void
    {
        $GLOBALS['_test_deleted_users'] = [];

        $existingUser = $this->makeUser(92);
        $existingUser->user_email = 'recent@example.com';
        $GLOBALS['_test_get_user_by_result'] = $existingUser;
        $GLOBALS['_test_user_meta'][92] = [
            'wsms_registration_status'     => 'pending',
            'wsms_registration_created_at' => gmdate('Y-m-d H:i:s', time() - 3600), // 1 hour ago
        ];

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields'     => ['email', 'password'],
            'email'                   => ['verify_at_signup' => true],
            'pending_user_ttl_hours'  => 24,
        ];

        // wp_insert_user will fail because email is still taken.
        $GLOBALS['_test_wp_insert_user_result'] = new \WP_Error('existing_user_email', 'Email exists.');
        $this->stubWpdb();

        $result = $this->manager->registerUser([
            'email'    => 'recent@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertEmpty($GLOBALS['_test_deleted_users']);
        $this->assertFalse($result['success']);
    }

    public function testRegisterDeletesExpiredPendingUserByPhone(): void
    {
        $GLOBALS['_test_deleted_users'] = [];

        $existingUser = $this->makeUser(93);
        $GLOBALS['_test_get_users_result'] = [$existingUser];
        $GLOBALS['_test_user_meta'][93] = [
            'wsms_registration_status'     => 'pending',
            'wsms_registration_created_at' => gmdate('Y-m-d H:i:s', time() - 90000),
            'wsms_phone'                   => '+1234567890',
        ];
        // No email collision (get_user_by returns false for email check).
        $GLOBALS['_test_get_user_by_result'] = false;

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email'    => ['required_at_signup' => false],
            'password' => ['required_at_signup' => false],
            'phone'    => ['enabled' => true, 'required_at_signup' => true, 'verify_at_signup' => true],
            'registration_fields'    => ['phone'],
            'pending_user_ttl_hours' => 24,
        ];

        $GLOBALS['_test_wp_insert_user_result'] = 94;
        $this->stubWpdb();

        $result = $this->manager->registerUser([
            'phone' => '+1234567890',
        ]);

        $this->assertContains(93, $GLOBALS['_test_deleted_users']);
        $this->assertTrue($result['success']);
    }

    // --- Helpers ---

    private function makeUser(int $id): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_email = 'test@example.com';
        $user->user_login = 'testuser';
        $user->display_name = 'Test User';
        $user->roles = ['subscriber'];
        $user->user_pass = '';

        return $user;
    }

    private function makeVerification(int $userId, string $type, bool $expired = false, bool $used = false): object
    {
        $v = new \stdClass();
        $v->id = 1;
        $v->user_id = $userId;
        $v->type = $type;
        $v->identifier = 'test@example.com';
        $v->code = 'hashed-token-abc';
        $v->expires_at = $expired
            ? gmdate('Y-m-d H:i:s', time() - 3600)
            : gmdate('Y-m-d H:i:s', time() + 3600);
        $v->used_at = $used ? gmdate('Y-m-d H:i:s') : null;

        return $v;
    }

    private function stubWpdb(): void
    {
        $wpdb = $GLOBALS['wpdb'];

        // Make insert callable as method.
        if (!method_exists($wpdb, 'insert')) {
            $GLOBALS['wpdb'] = new class {
                public string $prefix = 'wp_';
                public int $insert_id = 1;

                public function insert($table, $data, $format = null): bool
                {
                    return true;
                }

                public function prepare(string $query, ...$args): string
                {
                    return $query;
                }

                public function get_row($query): ?object
                {
                    return null;
                }

                public function update($table, $data, $where, $format = null, $whereFormat = null): bool
                {
                    return true;
                }

                public function query($query)
                {
                    return 1;
                }
            };
        }
    }

    private function stubWpdbLookup(?object $result): void
    {
        $GLOBALS['wpdb'] = new class($result) {
            public string $prefix = 'wp_';
            private ?object $lookupResult;

            public function __construct(?object $lookupResult)
            {
                $this->lookupResult = $lookupResult;
            }

            public function insert($table, $data, $format = null): bool
            {
                return true;
            }

            public function prepare(string $query, ...$args): string
            {
                return $query;
            }

            public function get_row($query): ?object
            {
                return $this->lookupResult;
            }

            public function update($table, $data, $where, $format = null, $whereFormat = null): bool
            {
                return true;
            }
        };
    }
}
