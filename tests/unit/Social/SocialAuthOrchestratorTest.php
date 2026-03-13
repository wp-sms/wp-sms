<?php

namespace WSms\Tests\Unit\Social;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthSession;
use WSms\Auth\ValueObjects\AuthResult;
use WSms\Social\Contracts\SocialProviderInterface;
use WSms\Social\OAuthStateManager;
use WSms\Social\SocialAccountRepository;
use WSms\Social\SocialAuthManager;
use WSms\Social\SocialAuthOrchestrator;

class SocialAuthOrchestratorTest extends TestCase
{
    private SocialAuthOrchestrator $orchestrator;
    private MockObject&SocialAuthManager $socialManager;
    private MockObject&SocialAccountRepository $repository;
    private MockObject&OAuthStateManager $stateManager;
    private MockObject&AuthOrchestrator $authOrchestrator;
    private MockObject&AccountManager $accountManager;
    private MockObject&AuthSession $session;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&AccountLockout $lockout;

    protected function setUp(): void
    {
        $this->socialManager = $this->createMock(SocialAuthManager::class);
        $this->repository = $this->createMock(SocialAccountRepository::class);
        $this->stateManager = $this->createMock(OAuthStateManager::class);
        $this->authOrchestrator = $this->createMock(AuthOrchestrator::class);
        $this->accountManager = $this->createMock(AccountManager::class);
        $this->session = $this->createMock(AuthSession::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->lockout = $this->createMock(AccountLockout::class);

        $this->orchestrator = new SocialAuthOrchestrator(
            $this->socialManager,
            $this->repository,
            $this->stateManager,
            $this->authOrchestrator,
            $this->accountManager,
            $this->session,
            $this->auditLogger,
            $this->lockout,
        );

        unset(
            $GLOBALS['_test_options']['wsms_auth_settings'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_userdata'],
        );
    }

    public function testInitiateAuthorizeThrowsForUnknownProvider(): void
    {
        $this->socialManager->method('getProvider')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->orchestrator->initiateAuthorize('unknown');
    }

    public function testInitiateAuthorizeReturnsUrl(): void
    {
        $provider = $this->makeProvider();
        $provider->method('createAuthorizationURL')->willReturn([
            'url'           => 'https://accounts.google.com/o/oauth2/auth?...',
            'state'         => 'test-state',
            'code_verifier' => 'test-verifier',
        ]);

        $this->socialManager->method('getProvider')->willReturn($provider);
        $this->stateManager->method('create')->willReturn([
            'state'         => 'test-state',
            'code_verifier' => 'test-verifier',
        ]);

        $result = $this->orchestrator->initiateAuthorize('google');

        $this->assertArrayHasKey('authorize_url', $result);
        $this->assertStringContainsString('google.com', $result['authorize_url']);
    }

    public function testHandleCallbackInvalidProvider(): void
    {
        $this->socialManager->method('getProvider')->willReturn(null);

        $result = $this->orchestrator->handleCallback('unknown', 'code', 'state');

        $this->assertSame('invalid_provider', $result['result']->toArray()['error']);
    }

    public function testHandleCallbackInvalidState(): void
    {
        $this->socialManager->method('getProvider')->willReturn($this->makeProvider());
        $this->stateManager->method('consume')->willReturn(null);

        $result = $this->orchestrator->handleCallback('google', 'code', 'bad-state');

        $this->assertSame('invalid_state', $result['result']->toArray()['error']);
    }

    public function testHandleCallbackExistingLink(): void
    {
        $provider = $this->makeProvider();
        $provider->method('exchangeCode')->willReturn(['access_token' => 'token123']);
        $provider->method('getUserInfo')->willReturn([
            'id' => '12345', 'email' => 'user@gmail.com', 'name' => 'Test User',
            'email_verified' => true,
        ]);

        $this->socialManager->method('getProvider')->willReturn($provider);
        $this->stateManager->method('consume')->willReturn(['code_verifier' => 'verifier']);

        // Existing link found.
        $existingLink = (object) ['id' => 1, 'user_id' => 42, 'channel_id' => 'google', 'identifier' => '12345', 'meta' => '{}'];
        $this->repository->method('findByProviderAccount')->willReturn($existingLink);

        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);
        $this->authOrchestrator->method('resolveAuthFromSocial')->willReturn(
            AuthResult::authenticated(42, ['id' => 42, 'email' => 'user@gmail.com', 'username' => 'user', 'display_name' => 'Test User', 'first_name' => '', 'last_name' => '', 'roles' => ['subscriber']])
        );

        $GLOBALS['_test_options']['wsms_auth_settings'] = ['social_profile_sync' => 'registration_only'];

        $result = $this->orchestrator->handleCallback('google', 'auth-code', 'valid-state');

        $this->assertSame(42, $result['user_id']);
        $this->assertTrue($result['result']->toArray()['success']);
    }

    public function testHandleCallbackAutoLinksForTrustedProvider(): void
    {
        $provider = $this->makeProvider();
        $provider->method('exchangeCode')->willReturn(['access_token' => 'token']);
        $provider->method('getUserInfo')->willReturn([
            'id' => '99999', 'email' => 'existing@gmail.com', 'name' => 'Existing',
            'email_verified' => true,
        ]);
        $provider->method('isTrustedEmailProvider')->willReturn(true);

        $this->socialManager->method('getProvider')->willReturn($provider);
        $this->stateManager->method('consume')->willReturn(['code_verifier' => 'v']);
        $this->repository->method('findByProviderAccount')->willReturn(null);

        // Email match.
        $user = new \WP_User(7);
        $user->user_email = 'existing@gmail.com';
        $GLOBALS['_test_get_user_by_result'] = $user;

        $this->lockout->method('isLocked')->willReturn(['locked' => false, 'until' => null, 'attempts' => 0]);
        $this->authOrchestrator->method('resolveAuthFromSocial')->willReturn(
            AuthResult::authenticated(7, ['id' => 7, 'email' => 'existing@gmail.com', 'username' => 'existing', 'display_name' => 'Existing', 'first_name' => '', 'last_name' => '', 'roles' => ['subscriber']])
        );

        $result = $this->orchestrator->handleCallback('google', 'code', 'state');

        $this->assertSame(7, $result['user_id']);
    }

    public function testHandleCallbackUntrustedProviderEmailMatchRejects(): void
    {
        $provider = $this->createMock(SocialProviderInterface::class);
        $provider->method('getId')->willReturn('github');
        $provider->method('isTrustedEmailProvider')->willReturn(false);
        $provider->method('exchangeCode')->willReturn(['access_token' => 'token']);
        $provider->method('getUserInfo')->willReturn([
            'id' => '11111', 'email' => 'test@example.com', 'name' => 'Test',
            'email_verified' => true,
        ]);

        $this->socialManager->method('getProvider')->willReturn($provider);
        $this->stateManager->method('consume')->willReturn(['code_verifier' => 'v']);
        $this->repository->method('findByProviderAccount')->willReturn(null);

        $user = new \WP_User(5);
        $GLOBALS['_test_get_user_by_result'] = $user;

        $result = $this->orchestrator->handleCallback('github', 'code', 'state');

        $this->assertSame('email_exists_untrusted', $result['result']->toArray()['error']);
    }

    public function testHandleCallbackRegistrationDisabled(): void
    {
        $provider = $this->makeProvider();
        $provider->method('exchangeCode')->willReturn(['access_token' => 'token']);
        $provider->method('getUserInfo')->willReturn([
            'id' => '22222', 'email' => 'new@gmail.com', 'name' => 'New User',
            'email_verified' => true,
        ]);

        $this->socialManager->method('getProvider')->willReturn($provider);
        $this->stateManager->method('consume')->willReturn(['code_verifier' => 'v']);
        $this->repository->method('findByProviderAccount')->willReturn(null);
        $GLOBALS['_test_get_user_by_result'] = false;

        $GLOBALS['_test_options']['wsms_auth_settings'] = ['auto_create_users' => false];

        $result = $this->orchestrator->handleCallback('google', 'code', 'state');

        $this->assertSame('registration_disabled', $result['result']->toArray()['error']);
    }

    public function testHandleCallbackAutoCreatesUser(): void
    {
        $provider = $this->makeProvider();
        $provider->method('exchangeCode')->willReturn(['access_token' => 'token']);
        $provider->method('getUserInfo')->willReturn([
            'id' => '33333', 'email' => 'brand-new@gmail.com', 'name' => 'Brand New',
            'email_verified' => true, 'given_name' => 'Brand', 'family_name' => 'New',
        ]);

        $this->socialManager->method('getProvider')->willReturn($provider);
        $this->stateManager->method('consume')->willReturn(['code_verifier' => 'v']);
        $this->repository->method('findByProviderAccount')->willReturn(null);
        $GLOBALS['_test_get_user_by_result'] = false;

        $GLOBALS['_test_options']['wsms_auth_settings'] = ['auto_create_users' => true];

        $this->accountManager->method('registerUser')->willReturn([
            'success' => true,
            'user_id' => 99,
            'message' => 'Registration successful.',
        ]);

        $this->authOrchestrator->method('resolveAuthFromSocial')->willReturn(
            AuthResult::authenticated(99, ['id' => 99, 'email' => 'brand-new@gmail.com', 'username' => 'wsms_test', 'display_name' => 'Brand New', 'first_name' => 'Brand', 'last_name' => 'New', 'roles' => ['subscriber']])
        );

        $result = $this->orchestrator->handleCallback('google', 'code', 'state');

        $this->assertSame(99, $result['user_id']);
        $this->assertTrue($result['result']->toArray()['success']);
    }

    public function testUnlinkAccountSucceeds(): void
    {
        $link = (object) ['id' => 1, 'channel_id' => 'google'];
        $this->repository->method('findByUserAndProvider')->willReturn($link);
        $this->repository->method('findByUserId')->willReturn([
            $link,
            (object) ['id' => 2, 'channel_id' => 'apple'],
        ]);
        $this->repository->method('unlinkAccount')->willReturn(true);

        $user = new \WP_User(10);
        $user->user_login = 'regular_user';
        $GLOBALS['_test_userdata'] = $user;

        $result = $this->orchestrator->unlinkAccount(10, 'google');

        $this->assertTrue($result['success']);
    }

    public function testUnlinkAccountFailsWhenLastMethod(): void
    {
        $link = (object) ['id' => 1, 'channel_id' => 'google'];
        $this->repository->method('findByUserAndProvider')->willReturn($link);
        $this->repository->method('findByUserId')->willReturn([$link]);

        $user = new \WP_User(10);
        $user->user_login = 'wsms_abc123';
        $GLOBALS['_test_userdata'] = $user;

        $result = $this->orchestrator->unlinkAccount(10, 'google');

        $this->assertFalse($result['success']);
        $this->assertSame('last_auth_method', $result['error']);
    }

    public function testGetLinkedAccountsStripsTokens(): void
    {
        $this->repository->method('findByUserId')->willReturn([
            (object) [
                'channel_id' => 'google',
                'meta'       => json_encode(['email' => 'test@gmail.com', 'name' => 'Test', 'tokens' => 'encrypted-data']),
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        $accounts = $this->orchestrator->getLinkedAccounts(1);

        $this->assertCount(1, $accounts);
        $this->assertSame('google', $accounts[0]['provider']);
        $this->assertSame('test@gmail.com', $accounts[0]['email']);
        $this->assertArrayNotHasKey('tokens', $accounts[0]);
    }

    private function makeProvider(): MockObject&SocialProviderInterface
    {
        $provider = $this->createMock(SocialProviderInterface::class);
        $provider->method('getId')->willReturn('google');
        $provider->method('isTrustedEmailProvider')->willReturn(true);

        return $provider;
    }
}
