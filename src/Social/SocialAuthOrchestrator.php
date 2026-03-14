<?php

namespace WSms\Social;

use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthSession;
use WSms\Auth\AvatarManager;
use WSms\Auth\PolicyEngine;
use WSms\Auth\ValueObjects\AuthResult;
use WSms\Enums\EventType;
use WSms\Mfa\Channels\TelegramChannel;

defined('ABSPATH') || exit;

class SocialAuthOrchestrator
{
    public function __construct(
        private SocialAuthManager $socialManager,
        private SocialAccountRepository $repository,
        private OAuthStateManager $stateManager,
        private AuthOrchestrator $authOrchestrator,
        private AccountManager $accountManager,
        private AuthSession $session,
        private AuditLogger $auditLogger,
        private AccountLockout $lockout,
        private ?PolicyEngine $policyEngine = null,
        private ?TelegramChannel $telegramChannel = null,
        private ?AvatarManager $avatarManager = null,
    ) {
    }

    /**
     * Generate the OAuth authorization URL for a provider.
     *
     * @return array{authorize_url: string}
     */
    public function initiateAuthorize(string $providerId, ?int $linkUserId = null, string $intent = 'login'): array
    {
        $provider = $this->socialManager->getProvider($providerId);

        if (!$provider) {
            throw new \InvalidArgumentException("Unknown social provider: {$providerId}");
        }

        $stateData = $this->stateManager->create($linkUserId, $intent);
        $redirectUri = $this->getCallbackUrl($providerId);

        $authData = $provider->createAuthorizationURL(
            $redirectUri,
            $stateData['state'],
            $stateData['code_verifier'],
        );

        return ['authorize_url' => $authData['url']];
    }

    /**
     * Handle the OAuth callback after user authorizes.
     *
     * @return array{result: AuthResult, user_id?: int}
     */
    public function handleCallback(string $providerId, string $code, string $state): array
    {
        $provider = $this->socialManager->getProvider($providerId);

        if (!$provider) {
            return ['result' => AuthResult::failed('invalid_provider', 'Unknown social provider.')];
        }

        // Validate state (CSRF + PKCE).
        $stateData = $this->stateManager->consume($state);

        if ($stateData === null) {
            return ['result' => AuthResult::failed('invalid_state', 'Invalid or expired OAuth state.')];
        }

        // Exchange code for tokens.
        try {
            $tokens = $provider->exchangeCode(
                $code,
                $this->getCallbackUrl($providerId),
                $stateData['code_verifier'] ?? null,
            );
        } catch (\RuntimeException $e) {
            $this->auditLogger->log(EventType::SocialLoginFailure, 'failure', null, [
                'provider' => $providerId,
                'error'    => $e->getMessage(),
            ]);

            return ['result' => AuthResult::failed('token_exchange_failed', 'Could not authenticate with provider.')];
        }

        // Get user info from provider.
        try {
            $userInfo = $provider->getUserInfo($tokens['access_token']);
        } catch (\RuntimeException $e) {
            $this->auditLogger->log(EventType::SocialLoginFailure, 'failure', null, [
                'provider' => $providerId,
                'error'    => $e->getMessage(),
            ]);

            return ['result' => AuthResult::failed('userinfo_failed', 'Could not retrieve user information from provider.')];
        }

        // If this is an account linking flow (authenticated user linking).
        if (isset($stateData['link_user_id'])) {
            return $this->handleLinking($providerId, $stateData['link_user_id'], $userInfo, $tokens);
        }

        // Extract intent from state (login or register).
        $intent = $stateData['intent'] ?? 'login';

        // Resolve user from social account.
        $result = $this->resolveUser($providerId, $provider, $userInfo, $tokens, $intent === 'register');

        // Auto-enroll Telegram MFA if user logged in via Telegram with bot_access scope.
        if ($providerId === 'telegram' && !empty($result['user_id']) && !empty($userInfo['id'])) {
            $this->autoEnrollTelegramMfa($result['user_id'], $userInfo);
        }

        $result['intent'] = $intent;

        return $result;
    }

    /**
     * Unlink a social account from the current user.
     */
    public function unlinkAccount(int $userId, string $providerId): array
    {
        $link = $this->repository->findByUserAndProvider($userId, $providerId);

        if (!$link) {
            return ['success' => false, 'error' => 'not_linked', 'message' => 'This provider is not linked to your account.'];
        }

        // Ensure user has another auth method before unlinking.
        $user = get_userdata($userId);
        $hasPassword = $user && !AccountManager::isPlaceholderUsername($user->user_login);
        $otherLinks = $this->repository->findByUserId($userId);
        $otherLinkCount = count(array_filter($otherLinks, fn($l) => $l->channel_id !== $providerId));

        if (!$hasPassword && $otherLinkCount === 0) {
            return ['success' => false, 'error' => 'last_auth_method', 'message' => 'Cannot unlink your only authentication method. Set a password first.'];
        }

        $this->repository->unlinkAccount($userId, $providerId);

        $this->auditLogger->log(EventType::SocialAccountUnlinked, 'success', $userId, [
            'provider' => $providerId,
        ]);

        return ['success' => true, 'message' => 'Account unlinked successfully.'];
    }

    /**
     * Get linked social accounts for a user.
     */
    public function getLinkedAccounts(int $userId): array
    {
        $links = $this->repository->findByUserId($userId);

        return array_map(function ($link) {
            $meta = json_decode($link->meta, true) ?: [];
            unset($meta['tokens']); // Never expose tokens.

            return [
                'provider'   => $link->channel_id,
                'email'      => $meta['email'] ?? '',
                'name'       => $meta['name'] ?? '',
                'linked_at'  => $link->created_at,
            ];
        }, $links);
    }

    /**
     * Resolve a user from social login: existing link, email match, or new registration.
     */
    private function resolveUser(string $providerId, Contracts\SocialProviderInterface $provider, array $userInfo, array $tokens, bool $allowAutoCreate = false): array
    {
        // Case 1: Existing social link.
        $existingLink = $this->repository->findByProviderAccount($providerId, $userInfo['id']);

        if ($existingLink) {
            $userId = (int) $existingLink->user_id;

            if ($locked = $this->checkLockout($userId)) {
                return $locked;
            }

            $this->repository->updateTokens((int) $existingLink->id, $tokens);
            $this->syncProfileData($userId, $userInfo);

            $this->auditLogger->log(EventType::SocialLoginSuccess, 'success', $userId, [
                'provider' => $providerId,
            ]);

            return $this->authenticateUser($userId, $providerId);
        }

        // Case 2 & 3: Email match.
        if (!empty($userInfo['email'])) {
            $existingUser = get_user_by('email', $userInfo['email']);

            if ($existingUser) {
                if (!$provider->isTrustedEmailProvider() || empty($userInfo['email_verified'])) {
                    return ['result' => AuthResult::failed(
                        'email_exists_untrusted',
                        'An account with this email already exists. Please sign in with your existing method and link this provider from your profile.',
                    )];
                }

                $userId = $existingUser->ID;

                if ($locked = $this->checkLockout($userId)) {
                    return $locked;
                }

                $this->linkAndLog($userId, $providerId, $userInfo, $tokens, ['auto_link' => true]);

                return $this->authenticateUser($userId, $providerId);
            }
        }

        // Case 2b: Phone number match (for providers like Telegram with phone but no email).
        if (empty($userInfo['email']) && !empty($userInfo['phone_number'])) {
            $phoneUsers = get_users([
                'meta_key'   => 'wsms_phone',
                'meta_value' => $userInfo['phone_number'],
                'number'     => 1,
            ]);

            if ($phoneUsers) {
                $userId = $phoneUsers[0]->ID;

                if ($locked = $this->checkLockout($userId)) {
                    return $locked;
                }

                $this->linkAndLog($userId, $providerId, $userInfo, $tokens, ['auto_link' => true, 'match' => 'phone']);

                return $this->authenticateUser($userId, $providerId);
            }
        }

        // Case 4 & 5: No match — create new user or reject.
        if (!$allowAutoCreate && !$this->policyEngine?->getSetting('auto_create_users', false)) {
            return ['result' => AuthResult::failed(
                'registration_disabled',
                'Automatic account creation is disabled. Please contact an administrator.',
            )];
        }

        // Register new user via AccountManager.
        $regData = [
            'email'        => $userInfo['email'] ?? '',
            'phone'        => $userInfo['phone_number'] ?? '',
            'display_name' => $userInfo['name'] ?? '',
            'first_name'   => $userInfo['given_name'] ?? '',
            'last_name'    => $userInfo['family_name'] ?? '',
        ];

        // When the OIDC layer fell back given_name to the full name (no separate first/last),
        // split on the first space so we get a proper first_name / last_name pair.
        [$regData['first_name'], $regData['last_name']] = self::splitFullNameIfNeeded(
            $regData['first_name'],
            $regData['last_name'],
            $userInfo['name'] ?? null,
        );

        $regResult = $this->accountManager->registerUser($regData, socialLogin: true);

        if (!$regResult['success']) {
            return ['result' => AuthResult::failed($regResult['error'] ?? 'registration_failed', $regResult['message'])];
        }

        $userId = $regResult['user_id'];

        // Mark email as verified since provider verified it.
        if (!empty($userInfo['email']) && !empty($userInfo['email_verified'])) {
            update_user_meta($userId, 'wsms_email_verified', '1');
            update_user_meta($userId, 'wsms_registration_status', 'active');
        }

        // Store phone number from provider (e.g. Telegram).
        if (!empty($userInfo['phone_number'])) {
            update_user_meta($userId, 'wsms_phone', $userInfo['phone_number']);
            update_user_meta($userId, 'wsms_phone_verified', '1');
        }

        // Save social avatar for new user.
        if (!empty($userInfo['picture']) && $this->avatarManager) {
            $this->avatarManager->saveSocialAvatar($userId, $userInfo['picture']);
        }

        $this->linkAccount($userId, $providerId, $userInfo, $tokens);

        $this->auditLogger->log(EventType::SocialRegistration, 'success', $userId, [
            'provider' => $providerId,
        ]);

        return $this->authenticateUser($userId, $providerId);
    }

    /**
     * Handle account linking for an authenticated user.
     */
    private function handleLinking(string $providerId, int $userId, array $userInfo, array $tokens): array
    {
        // Check if already linked.
        $existing = $this->repository->findByUserAndProvider($userId, $providerId);

        if ($existing) {
            return ['result' => AuthResult::failed('already_linked', 'This provider is already linked to your account.')];
        }

        // Check if this social account is linked to someone else.
        $otherLink = $this->repository->findByProviderAccount($providerId, $userInfo['id']);

        if ($otherLink) {
            return ['result' => AuthResult::failed('provider_taken', 'This social account is already linked to another user.')];
        }

        $this->linkAccount($userId, $providerId, $userInfo, $tokens);

        $this->auditLogger->log(EventType::SocialAccountLinked, 'success', $userId, [
            'provider' => $providerId,
        ]);

        return [
            'result'  => AuthResult::authenticated($userId, $this->getUserData($userId)),
            'user_id' => $userId,
        ];
    }

    /**
     * Check lockout and reset if not locked. Returns failure array if locked, null otherwise.
     */
    private function checkLockout(int $userId): ?array
    {
        $lockStatus = $this->lockout->isLocked($userId);

        if ($lockStatus['locked']) {
            return ['result' => AuthResult::failed('account_locked', 'Account is temporarily locked.', [
                'retry_after' => $lockStatus['until'],
            ])];
        }

        $this->lockout->reset($userId);

        return null;
    }

    /**
     * Link a social account and store standard meta.
     */
    private function linkAccount(int $userId, string $providerId, array $userInfo, array $tokens): void
    {
        $this->repository->linkAccount($userId, $providerId, $userInfo['id'], [
            'email'  => $userInfo['email'] ?? '',
            'name'   => $userInfo['name'] ?? '',
            'tokens' => $tokens,
        ]);
    }

    /**
     * Link, log the link event, and log login success.
     */
    private function linkAndLog(int $userId, string $providerId, array $userInfo, array $tokens, array $extraContext = []): void
    {
        $this->linkAccount($userId, $providerId, $userInfo, $tokens);

        $this->auditLogger->log(EventType::SocialAccountLinked, 'success', $userId, array_merge(
            ['provider' => $providerId],
            $extraContext,
        ));

        $this->auditLogger->log(EventType::SocialLoginSuccess, 'success', $userId, [
            'provider' => $providerId,
        ]);
    }

    /**
     * Authenticate user via social login and return result array.
     */
    private function authenticateUser(int $userId, string $providerId): array
    {
        $result = $this->authOrchestrator->resolveAuthFromSocial($userId, 'social:' . $providerId);

        return ['result' => $result, 'user_id' => $userId];
    }

    /**
     * Sync profile data from provider to WordPress user.
     */
    private function syncProfileData(int $userId, array $userInfo): void
    {
        $syncMode = $this->policyEngine?->getSetting('social_profile_sync', 'registration_only') ?? 'registration_only';

        if ($syncMode !== 'every_login') {
            return;
        }

        $update = ['ID' => $userId];

        if (!empty($userInfo['name'])) {
            $update['display_name'] = sanitize_text_field($userInfo['name']);
        }

        [$firstName, $lastName] = self::splitFullNameIfNeeded(
            $userInfo['given_name'] ?? '',
            $userInfo['family_name'] ?? '',
            $userInfo['name'] ?? null,
        );

        if (!empty($firstName)) {
            $update['first_name'] = sanitize_text_field($firstName);
        }

        if (!empty($lastName)) {
            $update['last_name'] = sanitize_text_field($lastName);
        }

        if (count($update) > 1) {
            wp_update_user($update);
        }

        // Save social avatar.
        if (!empty($userInfo['picture']) && $this->avatarManager) {
            $this->avatarManager->saveSocialAvatar($userId, $userInfo['picture']);
        }
    }

    private function getUserData(int $userId): array
    {
        $user = get_userdata($userId);

        return [
            'id'           => $userId,
            'email'        => $user->user_email,
            'username'     => $user->user_login,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'roles'        => $user->roles,
        ];
    }

    /**
     * Split a full name into first/last when the OIDC layer fell back given_name to the raw name.
     *
     * @return array{0: string, 1: string} [firstName, lastName]
     */
    private static function splitFullNameIfNeeded(string $firstName, string $lastName, ?string $rawName): array
    {
        if (
            !empty($firstName)
            && empty($lastName)
            && $rawName !== null
            && $firstName === $rawName
        ) {
            $parts = explode(' ', trim($firstName), 2);

            return [$parts[0], $parts[1] ?? ''];
        }

        return [$firstName, $lastName];
    }

    private function getCallbackUrl(string $providerId): string
    {
        return rest_url('wsms/v1/auth/social/callback/' . $providerId);
    }

    /**
     * Auto-enroll Telegram MFA factor when user logs in via Telegram social login.
     */
    private function autoEnrollTelegramMfa(int $userId, array $userInfo): void
    {
        if (!$this->telegramChannel) {
            return;
        }

        try {
            if (!$this->telegramChannel->isEnrolled($userId)) {
                $this->telegramChannel->autoEnroll(
                    $userId,
                    (int) $userInfo['id'],
                    $userInfo['preferred_username'] ?? null,
                );
            }
        } catch (\Throwable $e) {
            // Non-critical — log but don't block login.
            $this->auditLogger->log(EventType::MfaEnrolled, 'failure', $userId, [
                'channel' => 'telegram',
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
