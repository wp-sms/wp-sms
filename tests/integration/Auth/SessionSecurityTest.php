<?php

namespace WSms\Tests\Integration\Auth;

use WSms\Enums\ChannelStatus;
use WSms\Tests\Support\AuthScenarios;
use WSms\Tests\Support\IntegrationTestCase;
use WSms\Tests\Support\UserFactory;

class SessionSecurityTest extends IntegrationTestCase
{
    public function testInvalidSessionTokenRejected(): void
    {
        $this->setSettings(AuthScenarios::emailOtpOnly());

        $result = $this->orchestrator->verifyPrimary('totally-invalid-token', '123456');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_token', $result->error);
    }

    public function testTamperedSessionTokenRejected(): void
    {
        $this->setSettings(AuthScenarios::emailOtpOnly());
        $user = UserFactory::create();

        // Create a valid session, then tamper with the base64 payload.
        $token = $this->session->create($user->ID, 'email', 'challenge_pending', [
            'channel_id' => 'email',
        ]);

        // Tamper: flip a character in the token.
        $tampered = substr($token, 0, -2) . 'XX';

        $result = $this->orchestrator->verifyPrimary($tampered, '123456');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_token', $result->error);
    }

    public function testExpiredSessionTokenRejected(): void
    {
        $this->setSettings(AuthScenarios::emailOtpOnly());
        $user = UserFactory::create();

        // Create a session token.
        $token = $this->session->create($user->ID, 'email', 'challenge_pending');

        // Decode the token and modify the expiry in the base64 payload.
        $decoded = base64_decode($token, true);
        $parts = explode('|', $decoded);
        // parts: userId|sessionKey|expiry|signature

        // Set expiry to the past and re-encode (signature will be wrong too).
        $parts[2] = (string) (time() - 3600);
        $tamperedPayload = implode('|', $parts);
        $expiredToken = base64_encode($tamperedPayload);

        $result = $this->orchestrator->verifyPrimary($expiredToken, '123456');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_token', $result->error);
    }

    public function testWrongStageRejected(): void
    {
        $this->setSettings(AuthScenarios::emailOtpOnly());
        $user = UserFactory::create();

        $this->configureMfaChannel('email');

        // Create session at 'primary_verified' stage.
        $token = $this->session->create($user->ID, 'email', 'primary_verified');

        // Try to verify primary (expects 'challenge_pending').
        $result = $this->orchestrator->verifyPrimary($token, '123456');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_stage', $result->error);
    }

    public function testMfaVerifyInvalidStageRejected(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $this->configureMfaChannel('phone');

        // Create session at 'challenge_pending' stage (not 'primary_verified' or 'mfa_pending').
        $token = $this->session->create(1, 'password', 'challenge_pending');

        $result = $this->orchestrator->verifyMfa($token, '123456', 'phone');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_stage', $result->error);
    }

    public function testSessionDestroyedAfterMfaVerify(): void
    {
        $this->setSettings(AuthScenarios::mfaPhoneForAdmin());
        $user = UserFactory::create(['roles' => ['administrator']]);
        UserFactory::install($user);
        $this->simulateAuthenticate($user);

        $channel = $this->configureMfaChannel('phone', enrolled: true, verifySuccess: true);
        $this->configureMfaFactors($user->ID, [
            ['channel_id' => 'phone', 'status' => ChannelStatus::Active],
        ]);

        $loginResult = $this->orchestrator->loginWithPassword($user->user_email, 'password');
        $challengeResult = $this->orchestrator->sendMfaChallenge($loginResult->sessionToken, 'phone');
        $verifyResult = $this->orchestrator->verifyMfa($challengeResult->sessionToken, '123456', 'phone');
        $this->assertTrue($verifyResult->success);

        // Trying to reuse the session token should fail.
        $replayResult = $this->orchestrator->verifyMfa($challengeResult->sessionToken, '123456', 'phone');
        $this->assertFalse($replayResult->success);
        $this->assertSame('invalid_token', $replayResult->error);
    }

    public function testResendChallengeWorksWithValidSession(): void
    {
        $this->setSettings(AuthScenarios::emailOtpOnly());
        $user = UserFactory::create();
        UserFactory::install($user);

        $channel = $this->configureMfaChannel('email', enrolled: true);

        $challengeResult = $this->orchestrator->loginPasswordless('email', $user->user_email);
        $this->assertSame('challenge_sent', $challengeResult->status);

        $resendResult = $this->orchestrator->resendChallenge($challengeResult->sessionToken);

        $this->assertTrue($resendResult->success);
        $this->assertSame('challenge_sent', $resendResult->status);
    }

    public function testResendChallengeFailsWithInvalidToken(): void
    {
        $result = $this->orchestrator->resendChallenge('bad-token');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_token', $result->error);
    }
}
