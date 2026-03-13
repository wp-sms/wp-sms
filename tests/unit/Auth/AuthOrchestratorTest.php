<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthSession;
use WSms\Auth\PolicyEngine;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Channels\PhoneChannel;
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
    private MockObject&AccountLockout $lockout;
    private MockObject&AccountManager $accountManager;

    protected function setUp(): void
    {
        $this->policy = $this->createMock(PolicyEngine::class);
        $this->mfaManager = $this->createMock(MfaManager::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->session = $this->createMock(AuthSession::class);
        $this->lockout = $this->createMock(AccountLockout::class);
        $this->accountManager = $this->createMock(AccountManager::class);

        $this->orchestrator = new AuthOrchestrator(
            $this->policy,
            $this->mfaManager,
            $this->auditLogger,
            $this->session,
            $this->lockout,
            $this->accountManager,
        );

        // Default: no pending verifications (tests can override via specific expectations).
        $this->policy->method('getPendingVerifications')->willReturn([]);

        unset(
            $GLOBALS['_test_wp_authenticate_result'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_users_result'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_options']['wsms_auth_settings'],
        );
    }

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

        $phoneChannel = $this->createMock(PhoneChannel::class);
        $phoneChannel->method('supportsMfa')->willReturn(true);
        $phoneChannel->method('getName')->willReturn('Phone');

        $factor = new UserFactor(1, 1, 'phone', ChannelStatus::Active, [], '', '');

        $this->mfaManager->method('getUserFactors')->willReturn([$factor]);
        $this->mfaManager->method('getChannel')->willReturn($phoneChannel);

        $this->session->method('create')->willReturn('session-token-abc');

        $result = $this->orchestrator->loginWithPassword('admin', 'pass');

        $this->assertTrue($result->success);
        $this->assertSame('mfa_required', $result->status);
        $this->assertSame('session-token-abc', $result->sessionToken);
    }

    public function testLoginPasswordlessFailsWhenMethodDisabled(): void
    {
        $this->policy->method('getAvailablePrimaryMethods')->willReturn(['password']);

        $result = $this->orchestrator->loginPasswordless('phone', '+1234567890');

        $this->assertFalse($result->success);
        $this->assertSame('method_disabled', $result->error);
    }

    public function testLoginPasswordlessSendsChallenge(): void
    {
        $this->policy->method('getAvailablePrimaryMethods')->willReturn(['phone']);

        $user = $this->makeUser(5);
        $GLOBALS['_test_get_users_result'] = [$user];

        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);

        $phoneChannel = $this->createMock(PhoneChannel::class);
        $phoneChannel->method('isEnrolled')->willReturn(true);
        $phoneChannel->method('sendChallenge')->willReturn(new ChallengeResult(true, 'Sent', ['masked_to' => '+1*****90']));

        $this->mfaManager->method('getChannel')->with('phone')->willReturn($phoneChannel);

        $this->session->method('create')->willReturn('challenge-token-xyz');

        $result = $this->orchestrator->loginPasswordless('phone', '+1234567890');

        $this->assertTrue($result->success);
        $this->assertSame('challenge_sent', $result->status);
        $this->assertSame('challenge-token-xyz', $result->sessionToken);
    }

    public function testLoginPasswordlessRejectsLockedAccount(): void
    {
        $this->policy->method('getAvailablePrimaryMethods')->willReturn(['phone']);

        $user = $this->makeUser(5);
        $GLOBALS['_test_get_users_result'] = [$user];

        $this->lockout->method('isLocked')->willReturn([
            'locked'   => true,
            'until'    => '2030-01-01T00:00:00Z',
            'attempts' => 3,
        ]);

        $result = $this->orchestrator->loginPasswordless('phone', '+1234567890');

        $this->assertFalse($result->success);
        $this->assertSame('account_locked', $result->error);
        $this->assertSame('2030-01-01T00:00:00Z', $result->meta['retry_after']);
    }

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
            'method'      => 'phone',
            'stage'       => 'challenge_pending',
            'channel_id'  => 'phone',
            'session_key' => 'sk123',
        ]);

        $phoneChannel = $this->createMock(PhoneChannel::class);
        $phoneChannel->method('verify')->willReturn(true);

        $this->mfaManager->method('getChannel')->with('phone')->willReturn($phoneChannel);
        $this->policy->method('isMfaRequired')->willReturn(false);

        $result = $this->orchestrator->verifyPrimary('token', '123456');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    public function testVerifyPrimaryFailsWithWrongCode(): void
    {
        $this->session->method('validate')->willReturn([
            'user_id'     => 1,
            'method'      => 'phone',
            'stage'       => 'challenge_pending',
            'channel_id'  => 'phone',
            'session_key' => 'sk123',
        ]);

        $phoneChannel = $this->createMock(PhoneChannel::class);
        $phoneChannel->method('verify')->willReturn(false);

        $this->mfaManager->method('getChannel')->with('phone')->willReturn($phoneChannel);

        $result = $this->orchestrator->verifyPrimary('token', 'wrong');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_code', $result->error);
    }

    public function testSendMfaChallengeSucceeds(): void
    {
        $this->session->method('validate')->willReturn([
            'user_id'     => 1,
            'method'      => 'password',
            'stage'       => 'primary_verified',
            'session_key' => 'sk456',
        ]);

        $phoneChannel = $this->createMock(PhoneChannel::class);
        $phoneChannel->method('supportsMfa')->willReturn(true);
        $phoneChannel->method('isEnrolled')->willReturn(true);
        $phoneChannel->method('sendChallenge')->willReturn(new ChallengeResult(true, 'Sent', []));

        $this->mfaManager->method('getChannel')->with('phone')->willReturn($phoneChannel);

        $result = $this->orchestrator->sendMfaChallenge('token', 'phone');

        $this->assertTrue($result->success);
        $this->assertSame('challenge_sent', $result->status);
    }

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

        $phoneChannel = $this->createMock(PhoneChannel::class);
        $phoneChannel->method('supportsMfa')->willReturn(true);
        $phoneChannel->method('verify')->willReturn(true);

        $this->mfaManager->method('getChannel')->with('phone')->willReturn($phoneChannel);

        $result = $this->orchestrator->verifyMfa('token', '123456', 'phone');

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

        $phoneChannel = $this->createMock(PhoneChannel::class);
        $phoneChannel->method('supportsMfa')->willReturn(true);
        $phoneChannel->method('verify')->willReturn(false);

        $this->mfaManager->method('getChannel')->with('phone')->willReturn($phoneChannel);

        $result = $this->orchestrator->verifyMfa('token', 'wrong', 'phone');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_code', $result->error);
    }

    public function testResendChallengeSucceeds(): void
    {
        $this->session->method('validate')->willReturn([
            'user_id'        => 1,
            'method'         => 'password',
            'stage'          => 'mfa_pending',
            'mfa_channel_id' => 'phone',
            'session_key'    => 'sk_resend',
        ]);

        $phoneChannel = $this->createMock(PhoneChannel::class);
        $phoneChannel->method('sendChallenge')->willReturn(new ChallengeResult(true, 'Sent', []));

        $this->mfaManager->method('getChannel')->with('phone')->willReturn($phoneChannel);

        $result = $this->orchestrator->resendChallenge('token');

        $this->assertTrue($result->success);
        $this->assertSame('challenge_sent', $result->status);
    }

    public function testLoginWithPasswordRejectsLockedAccount(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_get_user_by_result'] = $user;

        $this->lockout->method('isLocked')->willReturn([
            'locked'   => true,
            'until'    => '2030-01-01T00:00:00Z',
            'attempts' => 5,
        ]);

        $result = $this->orchestrator->loginWithPassword('admin', 'pass');

        $this->assertFalse($result->success);
        $this->assertSame('account_locked', $result->error);
        $this->assertSame('2030-01-01T00:00:00Z', $result->meta['retry_after']);
    }

    public function testLoginWithPasswordRecordsFailureOnWrongPassword(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_get_user_by_result'] = $user;
        $GLOBALS['_test_wp_authenticate_result'] = new \WP_Error('incorrect_password', 'Wrong');

        $this->lockout->method('isLocked')->willReturn([
            'locked'   => false,
            'until'    => null,
            'attempts' => 0,
        ]);

        $this->lockout->expects($this->once())->method('recordFailure')->with(1);

        $this->orchestrator->loginWithPassword('admin', 'wrong');
    }

    public function testLoginWithPasswordResetsLockoutOnSuccess(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_get_user_by_result'] = $user;
        $GLOBALS['_test_wp_authenticate_result'] = $user;
        $GLOBALS['_test_userdata'] = $user;

        $this->lockout->method('isLocked')->willReturn([
            'locked'   => false,
            'until'    => null,
            'attempts' => 3,
        ]);

        $this->policy->method('isMfaRequired')->willReturn(false);

        $this->lockout->expects($this->once())->method('reset')->with(1);

        $result = $this->orchestrator->loginWithPassword('admin', 'pass');

        $this->assertTrue($result->success);
    }

    /**
     * @dataProvider identifierTypeProvider
     */
    public function testIdentifyDetectsType(string $identifier, string $expectedType): void
    {
        $result = $this->orchestrator->identify($identifier);
        $this->assertSame($expectedType, $result->identifierType);
    }

    public static function identifierTypeProvider(): iterable
    {
        yield 'email' => ['user@example.com', 'email'];
        yield 'email with plus tag' => ['user+tag@example.com', 'email'];
        yield 'phone with plus' => ['+12345678901', 'phone'];
        yield 'phone digits only' => ['1234567890', 'phone'];
        yield 'phone min length (7 digits)' => ['1234567', 'phone'];
        yield 'username' => ['admin', 'username'];
        yield 'short digits as username (<7)' => ['12345', 'username'];
        yield 'long digits as username (>15)' => ['1234567890123456', 'username'];
    }

    public function testIdentifyUserFoundReturnsMethodsAndDefault(): void
    {
        $methods = [
            ['method' => 'password', 'type' => 'password', 'channel' => 'password'],
            ['method' => 'email_otp', 'type' => 'otp', 'channel' => 'email'],
        ];

        $this->setupIdentifyUserFound('user@example.com', $methods, 'password');

        $result = $this->orchestrator->identify('user@example.com');

        $this->assertTrue($result->userFound);
        $this->assertSame($methods, $result->availableMethods);
        $this->assertSame('password', $result->defaultMethod);
        $this->assertFalse($result->registrationAvailable);
    }

    public function testIdentifyUserFoundReturnsMaskedEmail(): void
    {
        $this->setupIdentifyUserFound('john@example.com');

        $result = $this->orchestrator->identify('john@example.com');

        $this->assertSame('j***@example.com', $result->meta['masked_identifier']);
    }

    public function testIdentifyUserFoundReturnsMaskedPhone(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_get_users_result'] = [$user];

        $this->policy->method('getAvailableMethodsForUser')->willReturn([]);
        $this->policy->method('getDefaultMethod')->willReturn(null);

        $result = $this->orchestrator->identify('+1234567890');

        $this->assertMatchesRegularExpression('/^\+1\*+890$/', $result->meta['masked_identifier']);
    }

    public function testIdentifyUserFoundReturnsMaskedUsername(): void
    {
        $this->setupIdentifyUserFound('admin');

        $result = $this->orchestrator->identify('admin');

        $this->assertSame('a***n', $result->meta['masked_identifier']);
    }

    public function testIdentifyUserFoundRegistrationNotAvailable(): void
    {
        $this->setupIdentifyUserFound('user@example.com');

        $result = $this->orchestrator->identify('user@example.com');

        $this->assertFalse($result->registrationAvailable);
    }

    public function testIdentifyNotFoundAutoCreateOn(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'auto_create_users'    => true,
            'registration_fields'  => ['email', 'phone', 'password'],
        ];

        $this->policy->method('getEffectiveRegistrationFields')
            ->willReturn(['email', 'password', 'phone']);

        $result = $this->orchestrator->identify('nobody@example.com');

        $this->assertFalse($result->userFound);
        $this->assertTrue($result->registrationAvailable);
        $this->assertSame(['email', 'password', 'phone'], $result->registrationFields);
    }

    public function testIdentifyNotFoundAutoCreateOff(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'auto_create_users' => false,
        ];

        $result = $this->orchestrator->identify('nobody@example.com');

        $this->assertFalse($result->userFound);
        $this->assertFalse($result->registrationAvailable);
        $this->assertSame([], $result->registrationFields);
    }

    public function testIdentifyNotFoundNoSettings(): void
    {
        $result = $this->orchestrator->identify('nobody@example.com');

        $this->assertFalse($result->userFound);
        $this->assertFalse($result->registrationAvailable);
    }

    public function testIdentifyNotFoundEmptyMethods(): void
    {
        $result = $this->orchestrator->identify('nobody@example.com');

        $this->assertSame([], $result->availableMethods);
        $this->assertNull($result->defaultMethod);
    }

    public function testLoginViaEmail(): void
    {
        $user = $this->makeUser(1);
        $user->user_login = 'john';
        $this->setupSuccessfulLogin($user, '_test_get_user_by_result');

        $result = $this->orchestrator->loginWithPassword('john@example.com', 'secret');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    public function testLoginViaPhone(): void
    {
        $user = $this->makeUser(1);
        $user->user_login = 'phoneuser';
        $GLOBALS['_test_get_users_result'] = [$user];
        $GLOBALS['_test_wp_authenticate_result'] = $user;
        $GLOBALS['_test_userdata'] = $user;

        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);
        $this->policy->method('isMfaRequired')->willReturn(false);

        $result = $this->orchestrator->loginWithPassword('+1234567890', 'secret');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    public function testLoginViaUsername(): void
    {
        $user = $this->makeUser(1);
        $this->setupSuccessfulLogin($user, '_test_get_user_by_result');

        $result = $this->orchestrator->loginWithPassword('testuser', 'secret');

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
    }

    public function testLoginUnknownIdentifierFails(): void
    {
        $GLOBALS['_test_wp_authenticate_result'] = new \WP_Error('invalid_username', 'Unknown user');

        $result = $this->orchestrator->loginWithPassword('nonexistent', 'pass');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_credentials', $result->error);
    }

    public function testLoginViaEmailLockedAccount(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_get_user_by_result'] = $user;

        $this->lockout->method('isLocked')->willReturn([
            'locked'   => true,
            'until'    => '2030-06-01T00:00:00Z',
            'attempts' => 5,
        ]);

        $result = $this->orchestrator->loginWithPassword('user@example.com', 'pass');

        $this->assertFalse($result->success);
        $this->assertSame('account_locked', $result->error);
    }

    public function testLoginViaPhoneRecordsFailure(): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_get_users_result'] = [$user];
        $GLOBALS['_test_wp_authenticate_result'] = new \WP_Error('incorrect_password', 'Wrong');

        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);
        $this->lockout->expects($this->once())->method('recordFailure')->with(1);

        $result = $this->orchestrator->loginWithPassword('+1234567890', 'wrong');

        $this->assertFalse($result->success);
    }

    private function setupIdentifyUserFound(string $identifier, array $methods = [], ?string $default = null): void
    {
        $user = $this->makeUser(1);
        $GLOBALS['_test_get_user_by_result'] = $user;

        $this->policy->method('getAvailableMethodsForUser')->willReturn($methods);
        $this->policy->method('getDefaultMethod')->willReturn($default);
    }

    private function setupSuccessfulLogin(object $user, string $resolutionGlobal): void
    {
        $GLOBALS[$resolutionGlobal] = $user;
        $GLOBALS['_test_wp_authenticate_result'] = $user;
        $GLOBALS['_test_userdata'] = $user;

        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);
        $this->policy->method('isMfaRequired')->willReturn(false);
    }

    private function makeUser(int $id): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_email = 'test@example.com';
        $user->user_login = 'testuser';
        $user->display_name = 'Test User';
        $user->first_name = 'Test';
        $user->last_name = 'User';
        $user->roles = ['subscriber'];

        return $user;
    }
}
