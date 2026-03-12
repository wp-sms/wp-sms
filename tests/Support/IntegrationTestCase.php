<?php

namespace WSms\Tests\Support;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthSession;
use WSms\Auth\PolicyEngine;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\MfaManager;
use WSms\Mfa\OtpGenerator;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Mfa\ValueObjects\UserFactor;

/**
 * Base class for integration tests.
 *
 * Wires real PolicyEngine + AuthOrchestrator + AccountManager + AuthSession + AccountLockout.
 * Mocks I/O boundaries: MfaManager, AuditLogger, OtpGenerator (controlled codes).
 */
abstract class IntegrationTestCase extends TestCase
{
    protected PolicyEngine $policy;
    protected AuthOrchestrator $orchestrator;
    protected AccountManager $accountManager;
    protected AuthSession $session;
    protected AccountLockout $lockout;

    protected MockObject&MfaManager $mfaManager;
    protected MockObject&AuditLogger $auditLogger;
    protected MockObject&OtpGenerator $otpGenerator;

    protected WpdbFake $wpdb;

    /** @var string[] OTP codes generated in order */
    private array $otpCodes = [];
    private int $otpCodeIndex = 0;

    /** @var string[] Tokens generated in order */
    private int $tokenCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        UserFactory::reset();

        // Reset globals.
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_transients'] = [];
        unset(
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_get_users_result'],
            $GLOBALS['_test_wp_authenticate_result'],
            $GLOBALS['_test_wp_insert_user_result'],
            $GLOBALS['_test_wp_check_password_result'],
            $GLOBALS['_test_current_user_id'],
        );

        // WpdbFake.
        $this->wpdb = new WpdbFake();
        $GLOBALS['wpdb'] = $this->wpdb;

        // Real classes.
        $this->policy = new PolicyEngine();
        $this->lockout = new AccountLockout();

        // Controlled OTP generator: predictable codes but real hash/verify.
        $this->otpGenerator = $this->createPartialMock(OtpGenerator::class, ['generate', 'generateToken']);
        $this->otpGenerator->method('generate')->willReturnCallback(function (int $length = 6): string {
            $code = $this->otpCodes[$this->otpCodeIndex] ?? str_pad('1', $length, '2');
            $this->otpCodeIndex++;

            return substr($code, 0, $length);
        });
        $this->otpGenerator->method('generateToken')->willReturnCallback(function (): string {
            $this->tokenCounter++;

            return 'test-token-' . str_pad((string) $this->tokenCounter, 32, '0', STR_PAD_LEFT);
        });

        // Real AuthSession (uses transient stubs from bootstrap).
        $this->session = new AuthSession($this->otpGenerator);

        // Mocked I/O.
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->mfaManager = $this->createMock(MfaManager::class);

        // Real AccountManager.
        $this->accountManager = new AccountManager(
            $this->auditLogger,
            $this->otpGenerator,
            $this->mfaManager,
            $this->session,
        );

        // Real AuthOrchestrator with full dependency graph.
        $this->orchestrator = new AuthOrchestrator(
            $this->policy,
            $this->mfaManager,
            $this->auditLogger,
            $this->session,
            $this->lockout,
            $this->accountManager,
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wpdb'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_get_users_result'],
            $GLOBALS['_test_wp_authenticate_result'],
            $GLOBALS['_test_wp_insert_user_result'],
            $GLOBALS['_test_wp_check_password_result'],
            $GLOBALS['_test_current_user_id'],
        );
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_transients'] = [];

        parent::tearDown();
    }

    // ──────────────────────────────────────────────
    //  Settings helpers
    // ──────────────────────────────────────────────

    /**
     * Set the auth settings for this test.
     */
    protected function setSettings(array $settings): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = $settings;
    }

    // ──────────────────────────────────────────────
    //  Authentication helpers
    // ──────────────────────────────────────────────

    /**
     * Make wp_authenticate() return the given user.
     */
    protected function simulateAuthenticate(object $user): void
    {
        $GLOBALS['_test_wp_authenticate_result'] = $user;
    }

    /**
     * Make wp_authenticate() return an error.
     */
    protected function simulateAuthenticateFailure(string $code = 'incorrect_password', string $message = 'Invalid'): void
    {
        $GLOBALS['_test_wp_authenticate_result'] = new \WP_Error($code, $message);
    }

    /**
     * Make wp_insert_user() return a user ID.
     */
    protected function simulateUserCreation(int $userId): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = $userId;
    }

    // ──────────────────────────────────────────────
    //  OTP helpers
    // ──────────────────────────────────────────────

    /**
     * Queue OTP codes that the generator will return (in order).
     */
    protected function setOtpCodes(string ...$codes): void
    {
        $this->otpCodes = $codes;
        $this->otpCodeIndex = 0;
    }

    /**
     * Get the hash of a known OTP code (for verifying against stored values).
     * Delegates to the real OtpGenerator to stay in sync with production hashing.
     */
    protected function hashCode(string $code): string
    {
        return (new OtpGenerator())->hash($code);
    }

    /**
     * Get the predictable token generated at a given index (1-based).
     * Encapsulates the mock's token format so tests don't hardcode it.
     */
    protected function getGeneratedToken(int $index = 1): string
    {
        return 'test-token-' . str_pad((string) $index, 32, '0', STR_PAD_LEFT);
    }

    // ──────────────────────────────────────────────
    //  MFA channel helpers
    // ──────────────────────────────────────────────

    /**
     * Configure a mock MFA channel on the MfaManager.
     */
    protected function configureMfaChannel(
        string $channelId,
        bool $enrolled = true,
        bool $challengeSuccess = true,
        bool $verifySuccess = true,
        bool $supportsMfa = true,
    ): MockObject&ChannelInterface {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getId')->willReturn($channelId);
        $channel->method('getName')->willReturn(ucfirst($channelId));
        $channel->method('supportsMfa')->willReturn($supportsMfa);
        $channel->method('isEnrolled')->willReturn($enrolled);

        $channel->method('sendChallenge')->willReturn(
            new ChallengeResult($challengeSuccess, $challengeSuccess ? 'Challenge sent.' : 'Failed.', [
                'code_length' => 6,
                'channel'     => $channelId,
            ]),
        );

        $channel->method('verify')->willReturn($verifySuccess);

        $channel->method('enroll')->willReturn(
            new EnrollmentResult(true, 'Enrolled.'),
        );

        $this->mfaManager->method('getChannel')
            ->willReturnMap([
                [$channelId, $channel],
            ]);

        return $channel;
    }

    /**
     * Configure MFA factors that getUserFactors() returns for a user.
     */
    protected function configureMfaFactors(int $userId, array $factors): void
    {
        $userFactors = [];

        foreach ($factors as $factor) {
            $userFactors[] = new UserFactor(
                id: $factor['id'] ?? 1,
                userId: $userId,
                channelId: $factor['channel_id'],
                status: $factor['status'] ?? ChannelStatus::Active,
                meta: [],
                createdAt: '2024-01-01 00:00:00',
                updatedAt: '2024-01-01 00:00:00',
            );
        }

        $this->mfaManager->method('getUserFactors')
            ->with($userId)
            ->willReturn($userFactors);
    }
}
