<?php

namespace WSms\Tests\Integration\Auth;

use WSms\Tests\Support\AuthScenarios;
use WSms\Tests\Support\IntegrationTestCase;
use WSms\Tests\Support\UserFactory;

class LoginFlowTest extends IntegrationTestCase
{
    // ──────────────────────────────────────────────
    //  Password login
    // ──────────────────────────────────────────────

    /**
     * @dataProvider passwordLoginProvider
     */
    public function testPasswordLoginSucceeds(array $settings): void
    {
        $this->setSettings($settings);
        $user = UserFactory::create(['roles' => ['subscriber']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $result = $this->orchestrator->loginWithPassword('user1@example.com', 'password');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
        $this->assertSame($user->ID, $result->userId);
    }

    public static function passwordLoginProvider(): iterable
    {
        yield 'password only' => [AuthScenarios::passwordOnly()];
        yield 'password + email OTP' => [AuthScenarios::passwordAndEmailOtp()];
        yield 'password + phone OTP' => [AuthScenarios::passwordAndPhoneOtp()];
        yield 'all channels' => [AuthScenarios::allChannelsEnabled()];
    }

    public function testPasswordLoginFailsWithWrongCredentials(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create();
        UserFactory::install($user);
        $this->simulateAuthenticateFailure();

        $result = $this->orchestrator->loginWithPassword('user1@example.com', 'wrong');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_credentials', $result->error);
    }

    public function testPasswordLoginRecordsLockoutOnFailure(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create();
        UserFactory::install($user);
        $this->simulateAuthenticateFailure();

        $this->orchestrator->loginWithPassword('user1@example.com', 'wrong');

        $lockStatus = $this->lockout->isLocked($user->ID);
        $this->assertSame(1, $lockStatus['attempts']);
    }

    public function testPasswordLoginResetsLockoutOnSuccess(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create();
        UserFactory::install($user);

        // Record some failures first.
        $this->lockout->recordFailure($user->ID);
        $this->lockout->recordFailure($user->ID);
        $this->assertSame(2, $this->lockout->isLocked($user->ID)['attempts']);

        $this->simulateAuthenticate($user);
        $result = $this->orchestrator->loginWithPassword('user1@example.com', 'password');

        $this->assertTrue($result->success);
        $this->assertSame(0, $this->lockout->isLocked($user->ID)['attempts']);
    }

    public function testLockedAccountRejectsLogin(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create();
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        // Lock the account.
        $GLOBALS['_test_user_meta'][$user->ID]['wsms_lockout_until'] = time() + 600;

        $result = $this->orchestrator->loginWithPassword('user1@example.com', 'password');

        $this->assertFalse($result->success);
        $this->assertSame('account_locked', $result->error);
    }

    public function testLockedAccountAutoUnlocksAfterExpiry(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create();
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        // Set expired lockout.
        $GLOBALS['_test_user_meta'][$user->ID]['wsms_lockout_until'] = time() - 1;

        $result = $this->orchestrator->loginWithPassword('user1@example.com', 'password');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    // ──────────────────────────────────────────────
    //  Passwordless login
    // ──────────────────────────────────────────────

    /**
     * @dataProvider passwordlessLoginProvider
     */
    public function testPasswordlessLoginInitiatesChallenge(string $channel, string $method, array $settings): void
    {
        $this->setSettings($settings);

        $user = UserFactory::withPhone('+1234567890');
        UserFactory::install($user);

        $channelMock = $this->configureMfaChannel($channel);

        $identifier = $channel === 'phone' ? '+1234567890' : $user->user_email;
        $result = $this->orchestrator->loginPasswordless($channel, $identifier);

        $this->assertTrue($result->success);
        $this->assertSame('challenge_sent', $result->status);
        $this->assertNotEmpty($result->sessionToken);
    }

    public static function passwordlessLoginProvider(): iterable
    {
        yield 'email/otp' => ['email', 'otp', AuthScenarios::emailOtpOnly()];
        yield 'phone/otp' => ['phone', 'otp', AuthScenarios::phoneOtpOnly()];
    }

    public function testPasswordlessLoginFailsWhenChannelDisabled(): void
    {
        $this->setSettings(AuthScenarios::passwordOnly());
        $user = UserFactory::create();
        UserFactory::install($user);

        $result = $this->orchestrator->loginPasswordless('email', $user->user_email);

        $this->assertFalse($result->success);
        $this->assertSame('method_disabled', $result->error);
    }

    public function testPasswordlessLoginFailsWithUnknownUser(): void
    {
        $this->setSettings(AuthScenarios::emailOtpOnly());

        $this->configureMfaChannel('email');
        $GLOBALS['_test_get_user_by_result'] = false;

        $result = $this->orchestrator->loginPasswordless('email', 'nobody@example.com');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_credentials', $result->error);
    }

    public function testPasswordlessVerifyCompletesLogin(): void
    {
        $this->setSettings(AuthScenarios::emailOtpOnly());
        $user = UserFactory::create();
        UserFactory::install($user);

        $channel = $this->configureMfaChannel('email', enrolled: true, verifySuccess: true);

        $challengeResult = $this->orchestrator->loginPasswordless('email', $user->user_email);
        $this->assertSame('challenge_sent', $challengeResult->status);

        $verifyResult = $this->orchestrator->verifyPrimary($challengeResult->sessionToken, '123456');

        $this->assertTrue($verifyResult->success);
        $this->assertSame('authenticated', $verifyResult->status);
        $this->assertSame($user->ID, $verifyResult->userId);
    }

    public function testPasswordlessVerifyFailsWithBadCode(): void
    {
        $this->setSettings(AuthScenarios::emailOtpOnly());
        $user = UserFactory::create();
        UserFactory::install($user);

        $this->configureMfaChannel('email', enrolled: true, verifySuccess: false);

        $challengeResult = $this->orchestrator->loginPasswordless('email', $user->user_email);
        $verifyResult = $this->orchestrator->verifyPrimary($challengeResult->sessionToken, 'wrong');

        $this->assertFalse($verifyResult->success);
        $this->assertSame('invalid_code', $verifyResult->error);
    }

    // ──────────────────────────────────────────────
    //  Login with verify_at_login
    // ──────────────────────────────────────────────

    public function testLoginTriggersVerificationWhenVerifyAtLoginEnabled(): void
    {
        $settings = AuthScenarios::verifyAtLogin();
        $this->setSettings($settings);
        $this->setOtpCodes('654321');

        $user = UserFactory::create();
        UserFactory::install($user);
        $this->simulateAuthenticate($user);
        // Email not verified.
        $GLOBALS['_test_user_meta'][$user->ID]['wsms_email_verified'] = '';

        $result = $this->orchestrator->loginWithPassword('user1@example.com', 'password');

        $this->assertTrue($result->success);
        $this->assertSame('verification_required', $result->status);
        $this->assertNotEmpty($result->meta['pending_verifications']);
        $this->assertSame('email', $result->meta['pending_verifications'][0]['type']);
    }

    public function testLoginSkipsVerificationWhenAlreadyVerified(): void
    {
        $settings = AuthScenarios::verifyAtLogin();
        $this->setSettings($settings);

        $user = UserFactory::verified();
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $result = $this->orchestrator->loginWithPassword('user1@example.com', 'password');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    // ──────────────────────────────────────────────
    //  Identify
    // ──────────────────────────────────────────────

    public function testIdentifyReturnsAvailableMethods(): void
    {
        $this->setSettings(AuthScenarios::passwordAndEmailOtp());
        $user = UserFactory::create();
        UserFactory::install($user);

        $result = $this->orchestrator->identify($user->user_email);

        $this->assertTrue($result->userFound);
        $this->assertSame('email', $result->identifierType);
        $methods = array_column($result->availableMethods, 'method');
        $this->assertContains('password', $methods);
        $this->assertContains('email_otp', $methods);
        $this->assertSame('password', $result->defaultMethod);
    }

    public function testIdentifyUnknownUserReturnsRegistrationInfo(): void
    {
        $this->setSettings(AuthScenarios::withOverrides(AuthScenarios::passwordOnly(), [
            'auto_create_users' => true,
        ]));
        $GLOBALS['_test_get_user_by_result'] = false;

        $result = $this->orchestrator->identify('new@example.com');

        $this->assertFalse($result->userFound);
        $this->assertTrue($result->registrationAvailable);
    }
}
