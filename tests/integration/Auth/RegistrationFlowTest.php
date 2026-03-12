<?php

namespace WSms\Tests\Integration\Auth;

use WSms\Tests\Support\AuthScenarios;
use WSms\Tests\Support\IntegrationTestCase;

class RegistrationFlowTest extends IntegrationTestCase
{
    // ──────────────────────────────────────────────
    //  Basic registration
    // ──────────────────────────────────────────────

    public function testRegisterWithPasswordOnlyNoVerification(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $this->simulateUserCreation(42);

        $result = $this->accountManager->registerUser([
            'email'    => 'new@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['user_id']);
        $this->assertArrayNotHasKey('pending_verifications', $result);
        $this->assertArrayNotHasKey('registration_token', $result);
        $this->assertEmpty($this->wpdb->getVerificationsByType('email_verify'));
        $this->assertEmpty($this->wpdb->getVerificationsByType('phone_verify'));
    }

    public function testRegisterFailsWithMissingEmail(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());

        $result = $this->accountManager->registerUser([
            'password' => 'StrongPass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_email', $result['error']);
    }

    public function testRegisterFailsWithMissingPassword(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());

        $result = $this->accountManager->registerUser([
            'email' => 'test@example.com',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_password', $result['error']);
    }

    public function testRegisterFailsWhenPhoneRequiredButMissing(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::passwordOnly(), [
            'phone' => ['required_at_signup' => true],
        ]);
        $this->setSettings($settings);

        $result = $this->accountManager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_phone', $result['error']);
    }

    // ──────────────────────────────────────────────
    //  Registration with email verification
    // ──────────────────────────────────────────────

    /**
     * @dataProvider signupVerificationMatrix
     */
    public function testRegisterCreatesExpectedVerifications(array $settings, array $regData, string $expectedChannel): void
    {
        $this->setSettings($settings);
        $this->simulateUserCreation(50);
        $this->setOtpCodes('654321');

        $result = $this->accountManager->registerUser($regData);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['pending_verifications']);
        $this->assertArrayHasKey('registration_token', $result);

        $pendingTypes = array_column($result['pending_verifications'], 'type');

        if ($expectedChannel === 'both') {
            $this->assertContains('email', $pendingTypes);
            $this->assertContains('phone', $pendingTypes);
        } else {
            $this->assertContains($expectedChannel, $pendingTypes);
        }
    }

    public static function signupVerificationMatrix(): iterable
    {
        yield 'email-otp' => [
            AuthScenarios::verifyEmailAtSignup(),
            ['email' => 'verify@example.com', 'password' => 'Pass1!'],
            'email',
        ];

        yield 'email-magic-link' => [
            AuthScenarios::withOverrides(AuthScenarios::verifyEmailAtSignup(), [
                'email' => ['verification_methods' => ['magic_link']],
            ]),
            ['email' => 'verify@example.com', 'password' => 'Pass1!'],
            'email',
        ];

        yield 'phone-otp' => [
            AuthScenarios::verifyPhoneAtSignup(),
            ['email' => 'test@example.com', 'password' => 'Pass1!', 'phone' => '+1234567890'],
            'phone',
        ];

        yield 'both' => [
            AuthScenarios::verifyBothAtSignup(),
            ['email' => 'both@example.com', 'password' => 'Pass1!', 'phone' => '+1234567890'],
            'both',
        ];
    }

    public function testRegisterEmailOtpCreatesVerificationRecord(): void
    {
        $this->setSettings(AuthScenarios::verifyEmailAtSignup());
        $this->simulateUserCreation(60);
        $this->setOtpCodes('123456');

        $result = $this->accountManager->registerUser([
            'email'    => 'otp@example.com',
            'password' => 'Pass1!',
        ]);

        $this->assertTrue($result['success']);

        $emailVerifications = $this->wpdb->getVerificationsByType('email_verify');
        $this->assertCount(1, $emailVerifications);
        $this->assertSame(60, (int) $emailVerifications[0]->user_id);
        $this->assertSame('otp@example.com', $emailVerifications[0]->identifier);
        // Code is hashed.
        $this->assertSame($this->hashCode('123456'), $emailVerifications[0]->code);
    }

    public function testRegisterEmailMagicLinkCreatesVerificationRecord(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::verifyEmailAtSignup(), [
            'email' => ['verification_methods' => ['magic_link']],
        ]);
        $this->setSettings($settings);
        $this->simulateUserCreation(61);

        $result = $this->accountManager->registerUser([
            'email'    => 'ml@example.com',
            'password' => 'Pass1!',
        ]);

        $this->assertTrue($result['success']);

        $emailVerifications = $this->wpdb->getVerificationsByType('email_verify');
        $this->assertCount(1, $emailVerifications);
        $this->assertSame('ml@example.com', $emailVerifications[0]->identifier);
    }

    public function testRegisterPhoneOtpCreatesVerificationRecord(): void
    {
        $this->setSettings(AuthScenarios::verifyPhoneAtSignup());
        $this->simulateUserCreation(62);
        $this->setOtpCodes('789012');

        $result = $this->accountManager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'Pass1!',
            'phone'    => '+1234567890',
        ]);

        $this->assertTrue($result['success']);

        $phoneVerifications = $this->wpdb->getVerificationsByType('phone_verify');
        $this->assertCount(1, $phoneVerifications);
        $this->assertSame('+1234567890', $phoneVerifications[0]->identifier);
        $this->assertSame($this->hashCode('789012'), $phoneVerifications[0]->code);
    }

    public function testRegisterBothCreatesEmailAndPhoneVerifications(): void
    {
        $this->setSettings(AuthScenarios::verifyBothAtSignup());
        $this->simulateUserCreation(63);
        $this->setOtpCodes('111111', '222222');

        $result = $this->accountManager->registerUser([
            'email'    => 'both@example.com',
            'password' => 'Pass1!',
            'phone'    => '+9876543210',
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['pending_verifications']);

        $phoneVerifications = $this->wpdb->getVerificationsByType('phone_verify');
        $emailVerifications = $this->wpdb->getVerificationsByType('email_verify');
        $this->assertCount(1, $phoneVerifications);
        $this->assertCount(1, $emailVerifications);
    }

    // ──────────────────────────────────────────────
    //  Registration with MFA
    // ──────────────────────────────────────────────

    public function testRegisterWithMfaOnRegistrationFlagsMfaRequired(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::passwordOnly(), [
            'enrollment_timing' => 'on_registration',
        ]);
        $this->setSettings($settings);
        $this->simulateUserCreation(70);

        $result = $this->accountManager->registerUser([
            'email'    => 'mfa@example.com',
            'password' => 'Pass1!',
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['mfa_required'] ?? false);
    }

    public function testRegisterWithVoluntaryEnrollmentDoesNotFlagMfa(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::passwordOnly(), [
            'enrollment_timing' => 'voluntary',
        ]);
        $this->setSettings($settings);
        $this->simulateUserCreation(71);

        $result = $this->accountManager->registerUser([
            'email'    => 'vol@example.com',
            'password' => 'Pass1!',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('mfa_required', $result);
    }

    // ──────────────────────────────────────────────
    //  Registration field validation
    // ──────────────────────────────────────────────

    public function testRegisterFailsWhenFirstNameRequired(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::passwordOnly(), [
            'registration_fields' => ['email', 'password', 'first_name'],
        ]);
        $this->setSettings($settings);

        $result = $this->accountManager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'Pass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_first_name', $result['error']);
    }

    public function testRegisterFailsWhenLastNameRequired(): void
    {
        $settings = AuthScenarios::withOverrides(AuthScenarios::passwordOnly(), [
            'registration_fields' => ['email', 'password', 'last_name'],
        ]);
        $this->setSettings($settings);

        $result = $this->accountManager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'Pass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_last_name', $result['error']);
    }

    public function testRegisterWithInvalidEmailFails(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $this->simulateUserCreation(80);

        $result = $this->accountManager->registerUser([
            'email'    => 'not-an-email',
            'password' => 'Pass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_email', $result['error']);
    }

    public function testRegisterWithDuplicateEmailFails(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $GLOBALS['_test_wp_insert_user_result'] = new \WP_Error('existing_user_email', 'Email already exists.');

        $result = $this->accountManager->registerUser([
            'email'    => 'taken@example.com',
            'password' => 'Pass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('existing_user_email', $result['error']);
    }
}
