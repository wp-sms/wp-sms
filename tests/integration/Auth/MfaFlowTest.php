<?php

namespace WSms\Tests\Integration\Auth;

use WSms\Enums\ChannelStatus;
use WSms\Enums\SessionStage;
use WSms\Tests\Support\AuthScenarios;
use WSms\Tests\Support\IntegrationTestCase;
use WSms\Tests\Support\UserFactory;

class MfaFlowTest extends IntegrationTestCase
{
    public function testPasswordLoginRequiresMfaForAdmin(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $channel = $this->configureMfaChannel('phone');
        $this->configureMfaFactors($user->ID, [
            ['channel_id' => 'phone', 'status' => ChannelStatus::Active],
        ]);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertTrue($result->success);
        $this->assertSame('mfa_required', $result->status);
        $this->assertNotEmpty($result->sessionToken);
        $this->assertNotEmpty($result->meta['available_factors']);
    }

    public function testPasswordLoginSkipsMfaForSubscriber(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $user = UserFactory::create(['roles' => ['subscriber']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    public function testMfaChallengeAndVerifyCompletesLogin(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $channel = $this->configureMfaChannel('phone', enrolled: true, verifySuccess: true);
        $this->configureMfaFactors($user->ID, [
            ['channel_id' => 'phone', 'status' => ChannelStatus::Active],
        ]);

        // Step 1: Password login → MFA required.
        $loginResult = $this->orchestrator->loginWithPassword($user->user_email, 'password');
        $this->assertSame('mfa_required', $loginResult->status);

        // Step 2: Send MFA challenge.
        $challengeResult = $this->orchestrator->sendMfaChallenge($loginResult->sessionToken, 'phone');
        $this->assertTrue($challengeResult->success);
        $this->assertSame('challenge_sent', $challengeResult->status);

        // Step 3: Verify MFA → authenticated.
        $verifyResult = $this->orchestrator->verifyMfa($challengeResult->sessionToken, '123456', 'phone');
        $this->assertTrue($verifyResult->success);
        $this->assertSame('authenticated', $verifyResult->status);
        $this->assertSame($user->ID, $verifyResult->userId);
    }

    public function testMfaVerifyFailsWithBadCode(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $this->configureMfaChannel('phone', enrolled: true, verifySuccess: false);
        $this->configureMfaFactors($user->ID, [
            ['channel_id' => 'phone', 'status' => ChannelStatus::Active],
        ]);

        $loginResult = $this->orchestrator->loginWithPassword($user->user_email, 'password');
        $challengeResult = $this->orchestrator->sendMfaChallenge($loginResult->sessionToken, 'phone');
        $verifyResult = $this->orchestrator->verifyMfa($challengeResult->sessionToken, 'wrong', 'phone');

        $this->assertFalse($verifyResult->success);
        $this->assertSame('invalid_code', $verifyResult->error);
    }

    public function testMfaRequiredForAllRolesWithEmailChannel(): void
    {
        $this->setSettings(AuthScenarios::mfaEmailForAll());
        $user = UserFactory::create(['roles' => ['subscriber']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $this->configureMfaChannel('email', enrolled: true, verifySuccess: true);
        $this->configureMfaFactors($user->ID, [
            ['channel_id' => 'email', 'status' => ChannelStatus::Active],
        ]);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertSame('mfa_required', $result->status);
    }

    public function testOnRegistrationNoFactorsReturnsEnrollmentRequired(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $this->configureMfaChannel('phone', enrolled: false);
        // No factors enrolled — getUserFactors returns empty.
        $this->mfaManager->method('getUserFactors')
            ->with($user->ID)
            ->willReturn([]);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        // Should gate for enrollment instead of granting full access.
        $this->assertTrue($result->success);
        $this->assertSame('mfa_enrollment_required', $result->status);
        $this->assertNotEmpty($result->meta['available_channels']);
        $this->assertSame('1', $GLOBALS['_test_user_meta'][$user->ID]['wsms_mfa_enrollment_pending'] ?? '');
    }

    public function testMfaSendChallengeFailsWithWrongStage(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());

        // Create a session at wrong stage (challenge_pending instead of primary_verified).
        $token = $this->session->create(1, 'password', SessionStage::ChallengePending);

        $this->configureMfaChannel('phone');

        $result = $this->orchestrator->sendMfaChallenge($token, 'phone');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_stage', $result->error);
    }

    public function testMfaVoluntarySkipsUnenrolledUser(): void
    {
        $this->setSettings(AuthScenarios::mfaVoluntary());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);
        // No wsms_mfa_enabled meta → voluntary enrollment means no MFA.

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    public function testMfaVoluntaryEnforcesForEnrolledUser(): void
    {
        $this->setSettings(AuthScenarios::mfaVoluntary());
        $user = UserFactory::withMfa('phone', ['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $this->configureMfaChannel('phone', enrolled: true);
        $this->configureMfaFactors($user->ID, [
            ['channel_id' => 'phone', 'status' => ChannelStatus::Active],
        ]);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertSame('mfa_required', $result->status);
    }

    public function testMfaGracePeriodSkipsWithinGrace(): void
    {
        $this->setSettings(AuthScenarios::mfaGracePeriod(30));
        // User registered recently (within grace period).
        $user = UserFactory::create([
            'roles'           => ['administrator'],
            'user_registered' => gmdate('Y-m-d H:i:s', time() - 86400), // 1 day ago
        ]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);
        // Not enrolled (no wsms_mfa_enabled meta).

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    public function testVoluntaryEnrollmentEnforcesMfaWithNoRequiredRoles(): void
    {
        // Only enable TOTP — no mfa_required_roles set.
        $this->setSettings([
            'password' => ['enabled' => true, 'required_at_signup' => true, 'allow_sign_in' => true],
            'totp'     => ['enabled' => true],
        ]);

        $user = UserFactory::withMfa('totp', ['roles' => ['subscriber']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $this->configureMfaChannel('totp', enrolled: true, verifySuccess: true);
        $this->configureMfaFactors($user->ID, [
            ['channel_id' => 'totp', 'status' => ChannelStatus::Active],
        ]);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertSame('mfa_required', $result->status);
    }

    public function testTotpMfaFlowCompletesLogin(): void
    {
        $this->setSettings(AuthScenarios::mfaTotpForAdmin());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $channel = $this->configureMfaChannel('totp', enrolled: true, verifySuccess: true);
        $this->configureMfaFactors($user->ID, [
            ['channel_id' => 'totp', 'status' => ChannelStatus::Active],
        ]);

        // Step 1: Password login → MFA required.
        $loginResult = $this->orchestrator->loginWithPassword($user->user_email, 'password');
        $this->assertSame('mfa_required', $loginResult->status);

        // Step 2: Send MFA challenge (no-op for TOTP).
        $challengeResult = $this->orchestrator->sendMfaChallenge($loginResult->sessionToken, 'totp');
        $this->assertTrue($challengeResult->success);
        $this->assertSame('challenge_sent', $challengeResult->status);

        // Step 3: Verify MFA → authenticated.
        $verifyResult = $this->orchestrator->verifyMfa($challengeResult->sessionToken, '123456', 'totp');
        $this->assertTrue($verifyResult->success);
        $this->assertSame('authenticated', $verifyResult->status);
        $this->assertSame($user->ID, $verifyResult->userId);
    }

    public function testMfaGracePeriodEnforcesAfterGrace(): void
    {
        $this->setSettings(AuthScenarios::mfaGracePeriod(7));
        // User registered 30 days ago (past grace period).
        $user = UserFactory::create([
            'roles'           => ['administrator'],
            'user_registered' => gmdate('Y-m-d H:i:s', time() - 86400 * 30),
        ]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $this->configureMfaChannel('phone', enrolled: true);
        $this->configureMfaFactors($user->ID, [
            ['channel_id' => 'phone', 'status' => ChannelStatus::Active],
        ]);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertSame('mfa_required', $result->status);
    }

    public function testGracePeriodExpiredNoFactorsReturnsEnrollmentRequired(): void
    {
        $this->setSettings(AuthScenarios::mfaGracePeriod(7));
        // User registered 30 days ago — past grace.
        $user = UserFactory::create([
            'roles'           => ['administrator'],
            'user_registered' => gmdate('Y-m-d H:i:s', time() - 86400 * 30),
        ]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $this->configureMfaChannel('phone', enrolled: false);
        $this->mfaManager->method('getUserFactors')
            ->with($user->ID)
            ->willReturn([]);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertTrue($result->success);
        $this->assertSame('mfa_enrollment_required', $result->status);
    }

    public function testGracePeriodInfoInAuthenticatedResponse(): void
    {
        $this->setSettings(AuthScenarios::mfaGracePeriod(30));
        // User registered 1 day ago — within grace period.
        $user = UserFactory::create([
            'roles'           => ['administrator'],
            'user_registered' => gmdate('Y-m-d H:i:s', time() - 86400),
        ]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);
        // Not enrolled, so isMfaRequired returns false (within grace).
        // Register the phone channel so getAvailableMfaFactors() is non-empty.
        $this->configureMfaChannel('phone', enrolled: false);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
        $this->assertArrayHasKey('grace_period', $result->meta);
        $this->assertGreaterThan(0, $result->meta['grace_period']['grace_period_remaining_days']);
    }

    public function testFullEnrollmentGateFlow(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        // Step 1: No factors enrolled → enrollment_required.
        $channel = $this->configureMfaChannel('phone', enrolled: false);
        $this->mfaManager->method('getUserFactors')
            ->with($user->ID)
            ->willReturn([]);

        $loginResult = $this->orchestrator->loginWithPassword($user->user_email, 'password');
        $this->assertSame('mfa_enrollment_required', $loginResult->status);
        $this->assertSame('1', $GLOBALS['_test_user_meta'][$user->ID]['wsms_mfa_enrollment_pending'] ?? '');
    }

    public function testSettingsMutationVoluntaryToOnRegistration(): void
    {
        // Start with voluntary — user should authenticate freely.
        $this->setSettings(AuthScenarios::mfaVoluntary());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $result1 = $this->orchestrator->loginWithPassword($user->user_email, 'password');
        $this->assertSame('authenticated', $result1->status);

        // Admin changes to on_registration — now MFA is enforced.
        // Need new orchestrator since settings changed.
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $settingsRepo = new \WSms\Auth\SettingsRepository();
        $policy = new \WSms\Auth\PolicyEngine($this->mfaManager, $settingsRepo);
        $orchestrator = new \WSms\Auth\AuthOrchestrator(
            $policy,
            $this->mfaManager,
            $this->auditLogger,
            $this->session,
            $this->lockout,
            $this->accountManager,
            $settingsRepo,
        );

        $this->configureMfaChannel('phone', enrolled: false);
        $this->mfaManager->method('getUserFactors')
            ->with($user->ID)
            ->willReturn([]);

        $result2 = $orchestrator->loginWithPassword($user->user_email, 'password');
        $this->assertSame('mfa_enrollment_required', $result2->status);
    }

    public function testEnrollmentGateSelfClearsOnSettingsChange(): void
    {
        // Gate a user.
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $this->configureMfaChannel('phone', enrolled: false);
        $this->mfaManager->method('getUserFactors')
            ->with($user->ID)
            ->willReturn([]);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');
        $this->assertSame('mfa_enrollment_required', $result->status);
        $this->assertSame('1', $GLOBALS['_test_user_meta'][$user->ID]['wsms_mfa_enrollment_pending'] ?? '');

        // Admin changes to voluntary — gate should self-clear.
        $this->setSettings(AuthScenarios::mfaVoluntary());
        $settingsRepo = new \WSms\Auth\SettingsRepository();
        $policy = new \WSms\Auth\PolicyEngine($this->mfaManager, $settingsRepo);

        // Simulate the REST gate re-validation.
        $GLOBALS['_test_current_user_id'] = $user->ID;

        $guard = new \WSms\Auth\LoginGuard(
            $policy,
            $this->session,
            $this->mfaManager,
            $settingsRepo,
        );

        $request = new \WP_REST_Request('GET', '/wsms/v1/auth/some-endpoint');
        $gateResult = $guard->enforceEnrollmentGate(null, null, $request);

        // Gate should have auto-cleared.
        $this->assertNull($gateResult);
        $this->assertEmpty($GLOBALS['_test_user_meta'][$user->ID]['wsms_mfa_enrollment_pending'] ?? '');
    }
}
