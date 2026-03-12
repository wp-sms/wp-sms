<?php

namespace WSms\Tests\Integration\Auth;

use WSms\Tests\Support\AuthScenarios;
use WSms\Tests\Support\IntegrationTestCase;
use WSms\Tests\Support\UserFactory;

class VerificationFlowTest extends IntegrationTestCase
{
    // ──────────────────────────────────────────────
    //  Email OTP verification
    // ──────────────────────────────────────────────

    public function testVerifyEmailOtpSucceeds(): void
    {
        $this->setSettings(AuthScenarios::verifyEmailAtSignup());
        $this->simulateUserCreation(10);
        $this->setOtpCodes('456789');

        // Register (creates email verification).
        $regResult = $this->accountManager->registerUser([
            'email'    => 'verify@example.com',
            'password' => 'Pass1!',
        ]);
        $this->assertTrue($regResult['success']);
        $userId = $regResult['user_id'];

        // Verify the email OTP.
        $verifyResult = $this->accountManager->verifyEmailOtp($userId, '456789');

        $this->assertTrue($verifyResult['success']);
        $this->assertSame('Email verified successfully.', $verifyResult['message']);
        $this->assertSame('1', $GLOBALS['_test_user_meta'][$userId]['wsms_email_verified']);
    }

    public function testVerifyEmailOtpFailsWithWrongCode(): void
    {
        $this->setSettings(AuthScenarios::verifyEmailAtSignup());
        $this->simulateUserCreation(11);
        $this->setOtpCodes('456789');

        $regResult = $this->accountManager->registerUser([
            'email'    => 'verify@example.com',
            'password' => 'Pass1!',
        ]);
        $userId = $regResult['user_id'];

        $verifyResult = $this->accountManager->verifyEmailOtp($userId, 'wrong');

        $this->assertFalse($verifyResult['success']);
        $this->assertSame('invalid_code', $verifyResult['error']);
    }

    public function testVerifyEmailOtpFailsWhenExpired(): void
    {
        $this->setSettings(AuthScenarios::verifyEmailAtSignup());
        $this->simulateUserCreation(12);
        $this->setOtpCodes('456789');

        $regResult = $this->accountManager->registerUser([
            'email'    => 'verify@example.com',
            'password' => 'Pass1!',
        ]);
        $userId = $regResult['user_id'];

        $this->wpdb->expireVerification(1);

        $verifyResult = $this->accountManager->verifyEmailOtp($userId, '456789');

        $this->assertFalse($verifyResult['success']);
        $this->assertSame('expired', $verifyResult['error']);
    }

    public function testVerifyEmailOtpFailsAfterMaxAttempts(): void
    {
        $this->setSettings(AuthScenarios::verifyEmailAtSignup());
        $this->simulateUserCreation(13);
        $this->setOtpCodes('456789');

        $regResult = $this->accountManager->registerUser([
            'email'    => 'verify@example.com',
            'password' => 'Pass1!',
        ]);
        $userId = $regResult['user_id'];

        $this->wpdb->exhaustVerificationAttempts(1);

        $verifyResult = $this->accountManager->verifyEmailOtp($userId, '456789');

        $this->assertFalse($verifyResult['success']);
        $this->assertSame('max_attempts', $verifyResult['error']);
    }

    // ──────────────────────────────────────────────
    //  Phone OTP verification
    // ──────────────────────────────────────────────

    public function testVerifyPhoneOtpSucceeds(): void
    {
        $this->setSettings(AuthScenarios::verifyPhoneAtSignup());
        $this->simulateUserCreation(20);
        $this->setOtpCodes('112233');

        $regResult = $this->accountManager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'Pass1!',
            'phone'    => '+1234567890',
        ]);
        $this->assertTrue($regResult['success']);
        $userId = $regResult['user_id'];

        $verifyResult = $this->accountManager->verifyPhone($userId, '112233');

        $this->assertTrue($verifyResult['success']);
        $this->assertSame('Phone verified successfully.', $verifyResult['message']);
        $this->assertSame('1', $GLOBALS['_test_user_meta'][$userId]['wsms_phone_verified']);
    }

    public function testVerifyPhoneOtpFailsWithWrongCode(): void
    {
        $this->setSettings(AuthScenarios::verifyPhoneAtSignup());
        $this->simulateUserCreation(21);
        $this->setOtpCodes('112233');

        $regResult = $this->accountManager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'Pass1!',
            'phone'    => '+1234567890',
        ]);

        $verifyResult = $this->accountManager->verifyPhone($regResult['user_id'], 'wrong');

        $this->assertFalse($verifyResult['success']);
        $this->assertSame('invalid_code', $verifyResult['error']);
    }

    public function testVerifyPhoneFailsWhenExpired(): void
    {
        $this->setSettings(AuthScenarios::verifyPhoneAtSignup());
        $this->simulateUserCreation(22);
        $this->setOtpCodes('112233');

        $regResult = $this->accountManager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'Pass1!',
            'phone'    => '+1234567890',
        ]);

        $this->wpdb->expireVerification(1);

        $verifyResult = $this->accountManager->verifyPhone($regResult['user_id'], '112233');

        $this->assertFalse($verifyResult['success']);
        $this->assertSame('expired', $verifyResult['error']);
    }

    // ──────────────────────────────────────────────
    //  Email magic link verification
    // ──────────────────────────────────────────────

    public function testVerifyEmailMagicLinkSucceeds(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::verifyEmailAtSignup(), [
            'email' => ['verification_methods' => ['magic_link']],
        ]);
        $this->setSettings($settings);
        $this->simulateUserCreation(30);

        $regResult = $this->accountManager->registerUser([
            'email'    => 'ml@example.com',
            'password' => 'Pass1!',
        ]);
        $this->assertTrue($regResult['success']);
        $userId = $regResult['user_id'];

        $verifyResult = $this->accountManager->verifyEmail($this->getGeneratedToken());

        $this->assertTrue($verifyResult['success']);
        $this->assertSame('Email verified successfully.', $verifyResult['message']);
        $this->assertSame('1', $GLOBALS['_test_user_meta'][$userId]['wsms_email_verified']);
    }

    public function testVerifyEmailMagicLinkFailsWithBadToken(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::verifyEmailAtSignup(), [
            'email' => ['verification_methods' => ['magic_link']],
        ]);
        $this->setSettings($settings);
        $this->simulateUserCreation(31);

        $this->accountManager->registerUser([
            'email'    => 'ml@example.com',
            'password' => 'Pass1!',
        ]);

        $verifyResult = $this->accountManager->verifyEmail('invalid-token');

        $this->assertFalse($verifyResult['success']);
        $this->assertSame('invalid_token', $verifyResult['error']);
    }

    // ──────────────────────────────────────────────
    //  Resend verification
    // ──────────────────────────────────────────────

    public function testResendPhoneVerificationCreatesNewRecord(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::verifyPhoneAtSignup(), [
            'phone' => ['cooldown' => 0],
        ]);
        $this->setSettings($settings);
        $this->simulateUserCreation(40);
        $this->setOtpCodes('111111', '222222');

        $regResult = $this->accountManager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'Pass1!',
            'phone'    => '+1234567890',
        ]);
        $userId = $regResult['user_id'];

        // Resend should invalidate old and create new.
        $resendResult = $this->accountManager->resendPhoneVerification($userId);

        $this->assertTrue($resendResult['success']);
        // Should have 2 inserts for phone_verify (original + resend).
        $phoneVerifications = $this->wpdb->getVerificationsByType('phone_verify');
        $this->assertCount(2, $phoneVerifications);
    }

    public function testResendEmailVerificationCreatesNewRecord(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::verifyEmailAtSignup(), [
            'email' => ['cooldown' => 0],
        ]);
        $this->setSettings($settings);
        $this->simulateUserCreation(41);
        $this->setOtpCodes('111111', '222222');

        $user = UserFactory::create(['ID' => 41, 'user_email' => 'test@example.com']);
        UserFactory::install($user);

        $this->accountManager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'Pass1!',
        ]);

        $resendResult = $this->accountManager->resendEmailVerification(41);

        $this->assertTrue($resendResult['success']);
        $emailVerifications = $this->wpdb->getVerificationsByType('email_verify');
        $this->assertCount(2, $emailVerifications);
    }

    // ──────────────────────────────────────────────
    //  Profile verification
    // ──────────────────────────────────────────────

    public function testProfileEmailChangeTriggeresVerification(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());

        $result = $this->accountManager->updateProfile(1, ['email' => 'new@example.com']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['email_verification_required']);

        $emailVerifications = $this->wpdb->getVerificationsByType('email_verify');
        $this->assertCount(1, $emailVerifications);
    }

    public function testProfilePhoneChangeTriggersVerification(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());

        $result = $this->accountManager->updateProfile(1, ['phone' => '+9876543210']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['phone_verification_required']);
        $this->assertSame('0', $GLOBALS['_test_user_meta'][1]['wsms_phone_verified']);
    }
}
