<?php

namespace WSms\Tests\Integration\Auth;

use WSms\Enums\ChannelStatus;
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

    public function testMfaSkippedWhenNoActiveFactors(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        // No factors enrolled — getUserFactors returns empty.
        $this->mfaManager->method('getUserFactors')
            ->with($user->ID)
            ->willReturn([]);

        $result = $this->orchestrator->loginWithPassword($user->user_email, 'password');

        // Gracefully completes login when no MFA factors available.
        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    public function testMfaSendChallengeFailsWithWrongStage(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());

        // Create a session at wrong stage (challenge_pending instead of primary_verified).
        $token = $this->session->create(1, 'password', 'challenge_pending');

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
}
