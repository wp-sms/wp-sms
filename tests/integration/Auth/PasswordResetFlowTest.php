<?php

namespace WSms\Tests\Integration\Auth;

use WSms\Enums\VerificationType;
use WSms\Tests\Support\AuthScenarios;
use WSms\Tests\Support\IntegrationTestCase;
use WSms\Tests\Support\UserFactory;

class PasswordResetFlowTest extends IntegrationTestCase
{
    public function testForgotPasswordCreatesVerificationAndSendsEmail(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create(['ID' => 5]);
        UserFactory::install($user);

        $this->accountManager->initiatePasswordReset($user->user_email);

        $verifications = $this->wpdb->getVerificationsByType(VerificationType::PasswordReset->value);
        $this->assertCount(1, $verifications);
        $this->assertSame(5, (int) $verifications[0]->user_id);
    }

    public function testForgotPasswordSilentlyIgnoresUnknownEmail(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $GLOBALS['_test_get_user_by_result'] = false;

        $this->accountManager->initiatePasswordReset('nobody@example.com');

        $verifications = $this->wpdb->getVerificationsByType(VerificationType::PasswordReset->value);
        $this->assertEmpty($verifications);
    }

    public function testCompletePasswordResetSucceeds(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create(['ID' => 6]);
        UserFactory::install($user);

        $this->accountManager->initiatePasswordReset($user->user_email);

        $result = $this->accountManager->completePasswordReset($this->getGeneratedToken(), 'NewSecurePass1!');

        $this->assertTrue($result['success']);
        $this->assertSame('Password has been reset successfully.', $result['message']);

        $verifications = $this->wpdb->getVerificationsByType(VerificationType::PasswordReset->value);
        $this->assertNotNull($verifications[0]->used_at);
    }

    public function testCompletePasswordResetFailsWithInvalidToken(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());

        $result = $this->accountManager->completePasswordReset('bad-token', 'NewPass1!');

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_token', $result['error']);
    }

    public function testCompletePasswordResetFailsWithExpiredToken(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create(['ID' => 7]);
        UserFactory::install($user);

        $this->accountManager->initiatePasswordReset($user->user_email);

        $this->wpdb->expireVerification(1);

        $result = $this->accountManager->completePasswordReset($this->getGeneratedToken(), 'NewPass1!');

        $this->assertFalse($result['success']);
        $this->assertSame('expired_token', $result['error']);
    }

    public function testCompletePasswordResetFailsWithUsedToken(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create(['ID' => 8]);
        UserFactory::install($user);

        $this->accountManager->initiatePasswordReset($user->user_email);

        $this->wpdb->markVerificationUsed(1);

        $result = $this->accountManager->completePasswordReset($this->getGeneratedToken(), 'NewPass1!');

        $this->assertFalse($result['success']);
        $this->assertSame('used_token', $result['error']);
    }

    public function testResetTokenCannotBeReused(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create(['ID' => 9]);
        UserFactory::install($user);

        $this->accountManager->initiatePasswordReset($user->user_email);
        $token = $this->getGeneratedToken();

        // First use succeeds.
        $result1 = $this->accountManager->completePasswordReset($token, 'NewPass1!');
        $this->assertTrue($result1['success']);

        // Second use fails (token already used).
        $result2 = $this->accountManager->completePasswordReset($token, 'AnotherPass1!');
        $this->assertFalse($result2['success']);
        $this->assertSame('used_token', $result2['error']);
    }

    /**
     * @dataProvider passwordResetSettingsProvider
     */
    public function testPasswordResetWorksWithAnyAuthConfig(array $settings): void
    {
        $this->setSettings($settings);
        $user = UserFactory::create();
        UserFactory::install($user);

        $this->accountManager->initiatePasswordReset($user->user_email);

        $verifications = $this->wpdb->getVerificationsByType(VerificationType::PasswordReset->value);
        $this->assertCount(1, $verifications);
    }

    public static function passwordResetSettingsProvider(): iterable
    {
        yield 'password only' => [AuthScenarios::passwordOnly()];
        yield 'email OTP only' => [AuthScenarios::emailOtpOnly()];
        yield 'all channels' => [AuthScenarios::allChannelsEnabled()];
        yield 'MFA enabled' => [AuthScenarios::mfaPhoneForAdmin()];
    }
}
