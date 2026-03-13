<?php

namespace WSms\Auth;

use WSms\Audit\AuditLogger;
use WSms\Auth\ValueObjects\AuthResult;
use WSms\Auth\ValueObjects\IdentifyResult;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\Channels\EmailChannel;
use WSms\Mfa\Channels\PhoneChannel;
use WSms\Mfa\MfaManager;

defined('ABSPATH') || exit;

class AuthOrchestrator
{
    public function __construct(
        private PolicyEngine $policy,
        private MfaManager $mfaManager,
        private AuditLogger $auditLogger,
        private AuthSession $session,
        private AccountLockout $lockout,
        private AccountManager $accountManager,
    ) {
    }

    /**
     * Identify a user by any identifier (email, phone, or username).
     *
     * Returns available auth methods for the user, or registration info if not found.
     */
    public function identify(string $identifier): IdentifyResult
    {
        $identifierType = $this->detectIdentifierType($identifier);
        $user = $this->resolveUserByAnyIdentifier($identifier, $identifierType);

        if ($user) {
            $availableMethods = $this->policy->getAvailableMethodsForUser($user->ID);
            $defaultMethod = $this->policy->getDefaultMethod($identifierType, $availableMethods);

            return new IdentifyResult(
                identifierType: $identifierType,
                userFound: true,
                availableMethods: $availableMethods,
                defaultMethod: $defaultMethod,
                registrationAvailable: false,
                registrationFields: [],
                meta: [
                    'masked_identifier' => $this->maskIdentifier($identifier, $identifierType),
                ],
            );
        }

        // User not found — check if registration is available.
        $settings = get_option('wsms_auth_settings', []);
        $autoCreate = !empty($settings['auto_create_users']);
        $effectiveFields = $this->policy->getEffectiveRegistrationFields();

        return new IdentifyResult(
            identifierType: $identifierType,
            userFound: false,
            availableMethods: [],
            defaultMethod: null,
            registrationAvailable: $autoCreate,
            registrationFields: $autoCreate ? $effectiveFields : [],
            meta: [],
        );
    }

    /**
     * Standard username/password login.
     *
     * Accepts any identifier (email, phone, or username) and resolves to the user first.
     */
    public function loginWithPassword(string $username, string $password): AuthResult
    {
        $identifierType = $this->detectIdentifierType($username);
        $resolvedUser = $this->resolveUserByAnyIdentifier($username, $identifierType);

        if ($resolvedUser) {
            $lockStatus = $this->lockout->isLocked($resolvedUser->ID);

            if ($lockStatus['locked']) {
                return AuthResult::failed('account_locked', 'Account is temporarily locked.', [
                    'retry_after' => $lockStatus['until'],
                ]);
            }
        }

        // Use the resolved user's login name for wp_authenticate so email/phone identifiers work.
        $loginName = $resolvedUser ? $resolvedUser->user_login : $username;
        $user = wp_authenticate($loginName, $password);

        if (is_wp_error($user)) {
            if ($resolvedUser) {
                $this->lockout->recordFailure($resolvedUser->ID);
            }

            $this->auditLogger->log(EventType::LoginFailure, 'failure', null, [
                'method' => 'password',
                'reason' => $user->get_error_code(),
            ]);

            return AuthResult::failed('invalid_credentials', 'Invalid username or password.');
        }

        $this->lockout->reset($user->ID);

        return $this->resolvePostPrimary($user->ID, 'password');
    }

    /**
     * Initiate passwordless login via a channel (phone or email).
     *
     * Channel names are now first-class IDs: 'phone', 'email'.
     */
    public function loginPasswordless(string $channel, string $identifier): AuthResult
    {
        $availableMethods = $this->policy->getAvailablePrimaryMethods();

        if (!in_array($channel, $availableMethods, true)) {
            return AuthResult::failed('method_disabled', 'This authentication method is not enabled.');
        }

        // Resolve user by identifier.
        $user = $this->resolveUserByIdentifier($channel, $identifier);

        if (!$user) {
            return AuthResult::failed('invalid_credentials', 'Invalid credentials.');
        }

        $channelObj = $this->mfaManager->getChannel($channel);

        if (!$channelObj) {
            return AuthResult::failed('channel_unavailable', 'Authentication channel is not available.');
        }

        if (!$channelObj->isEnrolled($user->ID)) {
            // Auto-enroll for email channel.
            if ($channel === 'email') {
                $channelObj->enroll($user->ID, []);
            } else {
                return AuthResult::failed('not_enrolled', 'You are not enrolled in this authentication method.');
            }
        }

        $challengeResult = $channelObj->sendChallenge($user->ID);

        if (!$challengeResult->success) {
            return AuthResult::failed('challenge_failed', $challengeResult->message);
        }

        $token = $this->session->create($user->ID, $channel, 'challenge_pending', [
            'channel_id' => $channel,
        ]);

        return AuthResult::challengeSent($token, array_merge(
            ['method' => $channel],
            $challengeResult->meta,
        ));
    }

    /**
     * Verify the primary challenge response (OTP code).
     */
    public function verifyPrimary(string $sessionToken, string $code): AuthResult
    {
        $sessionData = $this->session->validate($sessionToken);

        if ($sessionData === null) {
            return AuthResult::invalidToken();
        }

        if ($sessionData['stage'] !== 'challenge_pending') {
            return AuthResult::failed('invalid_stage', 'Invalid session stage.');
        }

        $channelId = $sessionData['channel_id'] ?? null;
        $channel = $channelId ? $this->mfaManager->getChannel($channelId) : null;

        if (!$channel) {
            return AuthResult::failed('channel_unavailable', 'Authentication channel is not available.');
        }

        $verified = $channel->verify($sessionData['user_id'], $code);

        if (!$verified) {
            return AuthResult::failed('invalid_code', 'The code you entered is incorrect.');
        }

        return $this->resolvePostPrimary(
            $sessionData['user_id'],
            $sessionData['method'],
            $sessionData['session_key'],
        );
    }

    /**
     * Verify a magic link token (comes directly from URL, no session token).
     * Looks up the token via email channel's magic link delegate.
     */
    public function verifyMagicLink(string $token): AuthResult
    {
        // Try email channel first, then phone channel.
        $emailChannel = $this->mfaManager->getChannel('email');

        if ($emailChannel && $emailChannel instanceof EmailChannel) {
            $userId = $emailChannel->verifyTokenAndResolveUser($token);

            if ($userId !== null) {
                return $this->resolvePostPrimary($userId, 'email');
            }
        }

        $phoneChannel = $this->mfaManager->getChannel('phone');

        if ($phoneChannel && $phoneChannel instanceof PhoneChannel) {
            $userId = $phoneChannel->verifyTokenAndResolveUser($token);

            if ($userId !== null) {
                return $this->resolvePostPrimary($userId, 'phone');
            }
        }

        return AuthResult::failed('invalid_token', 'This link is invalid or has expired.');
    }

    /**
     * Send an MFA challenge after primary auth is verified.
     */
    public function sendMfaChallenge(string $sessionToken, string $channelId): AuthResult
    {
        $sessionData = $this->session->validate($sessionToken);

        if ($sessionData === null) {
            return AuthResult::invalidToken();
        }

        if ($sessionData['stage'] !== 'primary_verified') {
            return AuthResult::failed('invalid_stage', 'Primary authentication has not been verified.');
        }

        $channel = $this->mfaManager->getChannel($channelId);

        if (!$channel || !$channel->supportsMfa()) {
            return AuthResult::failed('invalid_channel', 'Invalid MFA channel.');
        }

        // No conflict validation needed — usage is mutually exclusive per channel.

        if (!$channel->isEnrolled($sessionData['user_id'])) {
            return AuthResult::failed('not_enrolled', 'You are not enrolled in this MFA method.');
        }

        $challengeResult = $channel->sendChallenge($sessionData['user_id']);

        if (!$challengeResult->success) {
            return AuthResult::failed('challenge_failed', $challengeResult->message);
        }

        $this->session->update($sessionData['session_key'], [
            'stage'          => 'mfa_pending',
            'mfa_channel_id' => $channelId,
        ]);

        return AuthResult::challengeSent($sessionToken, $challengeResult->meta);
    }

    /**
     * Verify MFA and complete login.
     */
    public function verifyMfa(string $sessionToken, string $code, string $channelId): AuthResult
    {
        $sessionData = $this->session->validate($sessionToken);

        if ($sessionData === null) {
            return AuthResult::invalidToken();
        }

        if (!in_array($sessionData['stage'], ['primary_verified', 'mfa_pending'], true)) {
            return AuthResult::failed('invalid_stage', 'Invalid session stage.');
        }

        $channel = $this->mfaManager->getChannel($channelId);

        if (!$channel || !$channel->supportsMfa()) {
            return AuthResult::failed('invalid_channel', 'Invalid MFA channel.');
        }

        $verified = $channel->verify($sessionData['user_id'], $code);

        if (!$verified) {
            return AuthResult::failed('invalid_code', 'The code you entered is incorrect.');
        }

        $this->session->destroy($sessionData['session_key']);

        return $this->resolveLogin($sessionData['user_id'], $sessionData['method'], $channelId);
    }

    /**
     * Resend challenge for the current session's channel.
     */
    public function resendChallenge(string $sessionToken): AuthResult
    {
        $sessionData = $this->session->validate($sessionToken);

        if ($sessionData === null) {
            return AuthResult::invalidToken();
        }

        $channelId = $sessionData['mfa_channel_id'] ?? $sessionData['channel_id'] ?? null;
        $channel = $channelId ? $this->mfaManager->getChannel($channelId) : null;

        if (!$channel) {
            return AuthResult::failed('channel_unavailable', 'No channel found for this session.');
        }

        $challengeResult = $channel->sendChallenge($sessionData['user_id']);

        if (!$challengeResult->success) {
            return AuthResult::failed('challenge_failed', $challengeResult->message);
        }

        return AuthResult::challengeSent($sessionToken, $challengeResult->meta);
    }

    /**
     * Complete verification during login and finish login if all verifications are done.
     */
    public function completeVerification(string $sessionToken): AuthResult
    {
        $sessionData = $this->session->validate($sessionToken);

        if ($sessionData === null) {
            return AuthResult::invalidToken();
        }

        if ($sessionData['stage'] !== 'verification_pending') {
            return AuthResult::failed('invalid_stage', 'Invalid session stage.');
        }

        $pending = $this->policy->getPendingVerifications($sessionData['user_id']);

        if (!empty($pending)) {
            return AuthResult::verificationRequired($sessionToken, $pending);
        }

        $this->session->destroy($sessionData['session_key']);
        $userData = $this->completeLogin($sessionData['user_id'], $sessionData['method']);

        return AuthResult::authenticated($sessionData['user_id'], $userData);
    }

    private function resolvePostPrimary(int $userId, string $method, ?string $existingSessionKey = null): AuthResult
    {
        if (!$this->policy->isMfaRequired($userId)) {
            return $this->resolveLogin($userId, $method);
        }

        // MFA is required — find available factors.
        $factors = $this->mfaManager->getUserFactors($userId);
        $availableFactors = [];

        foreach ($factors as $factor) {
            if ($factor->status !== ChannelStatus::Active) {
                continue;
            }

            $channel = $this->mfaManager->getChannel($factor->channelId);

            if (!$channel || !$channel->supportsMfa()) {
                continue;
            }

            $availableFactors[] = [
                'channel_id' => $factor->channelId,
                'name'       => $channel->getName(),
            ];
        }

        // If no valid MFA factors available, complete login without MFA (graceful).
        if (empty($availableFactors)) {
            return $this->resolveLogin($userId, $method);
        }

        // Destroy old session if transitioning from challenge_pending.
        if ($existingSessionKey) {
            $this->session->destroy($existingSessionKey);
        }

        $token = $this->session->create($userId, $method, 'primary_verified');

        return AuthResult::mfaRequired($token, $availableFactors);
    }

    /**
     * Check verification requirements and either complete login or require verification.
     */
    private function resolveLogin(int $userId, string $method, ?string $mfaChannel = null): AuthResult
    {
        $pending = $this->policy->getPendingVerifications($userId);

        if (!empty($pending)) {
            $this->sendLoginVerifications($userId, $pending);

            $token = $this->session->create($userId, $method, 'verification_pending');

            return AuthResult::verificationRequired($token, $pending);
        }

        $userData = $this->completeLogin($userId, $method, $mfaChannel);

        return AuthResult::authenticated($userId, $userData);
    }

    private function sendLoginVerifications(int $userId, array $pending): void
    {
        foreach ($pending as $verification) {
            $this->accountManager->sendVerificationChallenge($userId, $verification['type']);
        }
    }

    /**
     * Finalize the WordPress session.
     */
    private function completeLogin(int $userId, string $method, ?string $mfaChannel = null): array
    {
        $this->accountManager->maybeActivateUser($userId);

        wp_set_auth_cookie($userId, true);
        wp_set_current_user($userId);

        $this->auditLogger->log(EventType::LoginSuccess, 'success', $userId, [
            'method'      => $method,
            'mfa_channel' => $mfaChannel,
        ]);

        do_action('wsms_login_success', $userId, $method, $mfaChannel);

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
     * Resolve a user by their identifier based on the channel.
     */
    private function resolveUserByIdentifier(string $channel, string $identifier): ?object
    {
        if ($channel === 'phone') {
            $users = get_users([
                'meta_key'   => 'wsms_phone',
                'meta_value' => $identifier,
                'number'     => 1,
            ]);

            return !empty($users) ? $users[0] : null;
        }

        // email channel uses email.
        $user = get_user_by('email', $identifier);

        return $user ?: null;
    }

    /**
     * Resolve a user by any identifier type (email, phone, or username).
     */
    private function resolveUserByAnyIdentifier(string $identifier, string $type): ?object
    {
        if ($type === 'email') {
            $user = get_user_by('email', $identifier);
            return $user ?: null;
        }

        if ($type === 'phone') {
            $users = get_users([
                'meta_key'   => 'wsms_phone',
                'meta_value' => $identifier,
                'number'     => 1,
            ]);
            return !empty($users) ? $users[0] : null;
        }

        // Username.
        $user = get_user_by('login', $identifier);
        return $user ?: null;
    }

    /**
     * Detect identifier type: email, phone, or username.
     */
    private function detectIdentifierType(string $identifier): string
    {
        if (str_contains($identifier, '@')) {
            return 'email';
        }

        if (preg_match('/^\+?[0-9]{7,15}$/', $identifier)) {
            return 'phone';
        }

        return 'username';
    }

    /**
     * Mask an identifier for display (e.g., "j***@example.com", "+1***789").
     */
    private function maskIdentifier(string $identifier, string $type): string
    {
        if ($type === 'email') {
            [$local, $domain] = explode('@', $identifier, 2);
            $masked = $local[0] . str_repeat('*', max(strlen($local) - 1, 2));
            return $masked . '@' . $domain;
        }

        if ($type === 'phone') {
            $len = strlen($identifier);
            if ($len <= 4) {
                return $identifier;
            }
            return substr($identifier, 0, 2) . str_repeat('*', $len - 5) . substr($identifier, -3);
        }

        // Username — show first and last character.
        $len = strlen($identifier);
        if ($len <= 2) {
            return $identifier;
        }
        return $identifier[0] . str_repeat('*', $len - 2) . $identifier[$len - 1];
    }

}
