<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthSession;
use WSms\Auth\PolicyEngine;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Channels\SmsOtpChannel;
use WSms\Mfa\MfaManager;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\UserFactor;

class AuthOrchestratorTest extends TestCase
{
    private AuthOrchestrator $orchestrator;
    private MockObject&PolicyEngine $policy;
    private MockObject&MfaManager $mfaManager;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&AuthSession $session;

    protected function setUp(): void
    {
        $this->policy = $this->createMock(PolicyEngine::class);
        $this->mfaManager = $this->createMock(MfaManager::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->session = $this->createMock(AuthSession::class);

        $this->orchestrator = new AuthOrchestrator(
            $this->policy,
            $this->mfaManager,
            $this->auditLogger,
            $this->session,
        );

        unset(
            $GLOBALS['_test_wp_authenticate_result'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_users_result'],
            $GLOBALS['_test_get_user_by_result'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_authenticate_result'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_users_result'],
            $GLOBALS['_test_get_user_by_result'],
        );
    }

    // --- loginWithPassword ---

    public function testLoginWithPasswordSucceedsWithoutMfa(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_wp_authenticate_result'] = $user;
        $GLOBALS['_test_userdata'] = $user;

        $this->policy->method('isMfaRequired')->willReturn(false);

        $result = $this->orchestrator->loginWithPassword('admin', 'pass');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
        $this->assertSame(1, $result->userId);
    }

    public function testLoginWithPasswordFailsWithBadCredentials(): void
    {
        $GLOBALS['_test_wp_authenticate_result'] = new \WP_Error('invalid_username', 'Bad');

        $result = $this->orchestrator->loginWithPassword('wrong', 'creds');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_credentials', $result->error);
    }

    public function testLoginWithPasswordReturnsMfaRequired(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_wp_authenticate_result'] = $user;
        $GLOBALS['_test_userdata'] = $user;

        $this->policy->method('isMfaRequired')->willReturn(true);
        $this->policy->method('validatePolicyConflicts')->willReturn(true);

        $smsChannel = $this->createMock(SmsOtpChannel::class);
        $smsChannel->method('supportsMfa')->willReturn(true);
        $smsChannel->method('getName')->willReturn('SMS');

        $factor = new UserFactor(1, 1, 'sms', ChannelStatus::Active, [], '', '');

        $this->mfaManager->method('getUserFactors')->willReturn([$factor]);
        $this->mfaManager->method('getChannel')->willReturn($smsChannel);

        $this->session->method('create')->willReturn('session-token-abc');

        $result = $this->orchestrator->loginWithPassword('admin', 'pass');

        $this->assertTrue($result->success);
        $this->assertSame('mfa_required', $result->status);
        $this->assertSame('session-token-abc', $result->sessionToken);
    }

    // --- loginPasswordless ---

    public function testLoginPasswordlessFailsForInvalidMethod(): void
    {
        $result = $this->orchestrator->loginPasswordless('carrier_pigeon', 'user@test.com');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_method', $result->error);
    }

    public function testLoginPasswordlessFailsWhenMethodDisabled(): void
    {
        $this->policy->method('getAvailablePrimaryMethods')->willReturn(['password']);

        $result = $this->orchestrator->loginPasswordless('phone_otp', '+1234567890');

        $this->assertFalse($result->success);
        $this->assertSame('method_disabled', $result->error);
    }

    public function testLoginPasswordlessSendsChallenge(): void
    {
        $this->policy->method('getAvailablePrimaryMethods')->willReturn(['phone_otp']);

        $user = $this->makeUser(5);
        $GLOBALS['_test_get_users_result'] = [$user];

        $smsChannel = $this->createMock(SmsOtpChannel::class);
        $smsChannel->method('isEnrolled')->willReturn(true);
        $smsChannel->method('sendChallenge')->willReturn(new ChallengeResult(true, 'Sent', ['masked_to' => '+1*****90']));

        $this->mfaManager->method('getChannel')->with('sms')->willReturn($smsChannel);

        $this->session->method('create')->willReturn('challenge-token-xyz');

        $result = $this->orchestrator->loginPasswordless('phone_otp', '+1234567890');

        $this->assertTrue($result->success);
        $this->assertSame('challenge_sent', $result->status);
        $this->assertSame('challenge-token-xyz', $result->sessionToken);
    }

    // --- verifyPrimary ---

    public function testVerifyPrimaryReturnsInvalidTokenForBadSession(): void
    {
        $this->session->method('validate')->willReturn(null);

        $result = $this->orchestrator->verifyPrimary('bad-token', '123456');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_token', $result->status);
    }

    public function testVerifyPrimarySucceeds(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_userdata'] = $user;

        $this->session->method('validate')->willReturn([
            'user_id'     => 1,
            'method'      => 'phone_otp',
            'stage'       => 'challenge_pending',
            'channel_id'  => 'sms',
            'session_key' => 'sk123',
        ]);

        $smsChannel = $this->createMock(SmsOtpChannel::class);
        $smsChannel->method('verify')->willReturn(true);

        $this->mfaManager->method('getChannel')->with('sms')->willReturn($smsChannel);
        $this->policy->method('isMfaRequired')->willReturn(false);

        $result = $this->orchestrator->verifyPrimary('token', '123456');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    public function testVerifyPrimaryFailsWithWrongCode(): void
    {
        $this->session->method('validate')->willReturn([
            'user_id'     => 1,
            'method'      => 'phone_otp',
            'stage'       => 'challenge_pending',
            'channel_id'  => 'sms',
            'session_key' => 'sk123',
        ]);

        $smsChannel = $this->createMock(SmsOtpChannel::class);
        $smsChannel->method('verify')->willReturn(false);

        $this->mfaManager->method('getChannel')->with('sms')->willReturn($smsChannel);

        $result = $this->orchestrator->verifyPrimary('token', 'wrong');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_code', $result->error);
    }

    // --- sendMfaChallenge ---

    public function testSendMfaChallengeSucceeds(): void
    {
        $this->session->method('validate')->willReturn([
            'user_id'     => 1,
            'method'      => 'password',
            'stage'       => 'primary_verified',
            'session_key' => 'sk456',
        ]);

        $smsChannel = $this->createMock(SmsOtpChannel::class);
        $smsChannel->method('supportsMfa')->willReturn(true);
        $smsChannel->method('isEnrolled')->willReturn(true);
        $smsChannel->method('sendChallenge')->willReturn(new ChallengeResult(true, 'Sent', []));

        $this->mfaManager->method('getChannel')->with('sms')->willReturn($smsChannel);
        $this->policy->method('validatePolicyConflicts')->willReturn(true);

        $result = $this->orchestrator->sendMfaChallenge('token', 'sms');

        $this->assertTrue($result->success);
        $this->assertSame('challenge_sent', $result->status);
    }

    public function testSendMfaChallengeRejectsPolicyConflict(): void
    {
        $this->session->method('validate')->willReturn([
            'user_id'     => 1,
            'method'      => 'phone_otp',
            'stage'       => 'primary_verified',
            'session_key' => 'sk789',
        ]);

        $smsChannel = $this->createMock(SmsOtpChannel::class);
        $smsChannel->method('supportsMfa')->willReturn(true);

        $this->mfaManager->method('getChannel')->with('sms')->willReturn($smsChannel);
        $this->policy->method('validatePolicyConflicts')->willReturn(false);

        $result = $this->orchestrator->sendMfaChallenge('token', 'sms');

        $this->assertFalse($result->success);
        $this->assertSame('policy_conflict', $result->error);
    }

    // --- verifyMfa ---

    public function testVerifyMfaCompletesLogin(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_userdata'] = $user;

        $this->session->method('validate')->willReturn([
            'user_id'     => 1,
            'method'      => 'password',
            'stage'       => 'mfa_pending',
            'session_key' => 'sk_mfa',
        ]);

        $smsChannel = $this->createMock(SmsOtpChannel::class);
        $smsChannel->method('supportsMfa')->willReturn(true);
        $smsChannel->method('verify')->willReturn(true);

        $this->mfaManager->method('getChannel')->with('sms')->willReturn($smsChannel);

        $result = $this->orchestrator->verifyMfa('token', '123456', 'sms');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
        $this->assertSame(1, $result->userId);
    }

    public function testVerifyMfaFailsWithWrongCode(): void
    {
        $this->session->method('validate')->willReturn([
            'user_id'     => 1,
            'method'      => 'password',
            'stage'       => 'mfa_pending',
            'session_key' => 'sk_mfa2',
        ]);

        $smsChannel = $this->createMock(SmsOtpChannel::class);
        $smsChannel->method('supportsMfa')->willReturn(true);
        $smsChannel->method('verify')->willReturn(false);

        $this->mfaManager->method('getChannel')->with('sms')->willReturn($smsChannel);

        $result = $this->orchestrator->verifyMfa('token', 'wrong', 'sms');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_code', $result->error);
    }

    // --- resendChallenge ---

    public function testResendChallengeSucceeds(): void
    {
        $this->session->method('validate')->willReturn([
            'user_id'        => 1,
            'method'         => 'password',
            'stage'          => 'mfa_pending',
            'mfa_channel_id' => 'sms',
            'session_key'    => 'sk_resend',
        ]);

        $smsChannel = $this->createMock(SmsOtpChannel::class);
        $smsChannel->method('sendChallenge')->willReturn(new ChallengeResult(true, 'Sent', []));

        $this->mfaManager->method('getChannel')->with('sms')->willReturn($smsChannel);

        $result = $this->orchestrator->resendChallenge('token');

        $this->assertTrue($result->success);
        $this->assertSame('challenge_sent', $result->status);
    }

    // --- Helper ---

    private function makeUser(int $id): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_email = 'test@example.com';
        $user->user_login = 'testuser';
        $user->display_name = 'Test User';
        $user->roles = ['subscriber'];

        return $user;
    }
}
