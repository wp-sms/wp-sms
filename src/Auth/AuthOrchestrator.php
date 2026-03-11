<?php

namespace WSms\Auth;

use WSms\Audit\AuditLogger;
use WSms\Auth\ValueObjects\AuthResult;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\Channels\MagicLinkChannel;
use WSms\Mfa\MfaManager;

defined('ABSPATH') || exit;

class AuthOrchestrator
{
    /** Maps passwordless method names to channel IDs. */
    private const METHOD_CHANNEL_MAP = [
        'phone_otp'  => 'sms',
        'email_otp'  => 'email_otp',
        'magic_link' => 'magic_link',
    ];

    public function __construct(
        private PolicyEngine $policy,
        private MfaManager $mfaManager,
        private AuditLogger $auditLogger,
        private AuthSession $session,
        private AccountLockout $lockout,
    ) {
    }

    /**
     * Standard username/password login.
     */
    public function loginWithPassword(string $username, string $password): AuthResult
    {
        $resolvedUser = get_user_by('login', $username);

        if ($resolvedUser) {
            $lockStatus = $this->lockout->isLocked($resolvedUser->ID);

            if ($lockStatus['locked']) {
                return AuthResult::failed('account_locked', 'Account is temporarily locked.', [
                    'retry_after' => $lockStatus['until'],
                ]);
            }
        }

        $user = wp_authenticate($username, $password);

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
     * Initiate passwordless login (phone_otp, email_otp, magic_link).
     */
    public function loginPasswordless(string $method, string $identifier): AuthResult
    {
        $channelId = self::METHOD_CHANNEL_MAP[$method] ?? null;

        if ($channelId === null) {
            return AuthResult::failed('invalid_method', 'Unsupported authentication method.');
        }

        $availableMethods = $this->policy->getAvailablePrimaryMethods();

        if (!in_array($method, $availableMethods, true)) {
            return AuthResult::failed('method_disabled', 'This authentication method is not enabled.');
        }

        // Resolve user by identifier.
        $user = $this->resolveUserByIdentifier($method, $identifier);

        if (!$user) {
            // Generic message to prevent user enumeration.
            return AuthResult::failed('invalid_credentials', 'Invalid credentials.');
        }

        $channel = $this->mfaManager->getChannel($channelId);

        if (!$channel) {
            return AuthResult::failed('channel_unavailable', 'Authentication channel is not available.');
        }

        if (!$channel->isEnrolled($user->ID)) {
            // Auto-enroll for email-based channels.
            if (in_array($method, ['email_otp', 'magic_link'], true)) {
                $channel->enroll($user->ID, []);
            } else {
                return AuthResult::failed('not_enrolled', 'You are not enrolled in this authentication method.');
            }
        }

        $challengeResult = $channel->sendChallenge($user->ID);

        if (!$challengeResult->success) {
            return AuthResult::failed('challenge_failed', $challengeResult->message);
        }

        $token = $this->session->create($user->ID, $method, 'challenge_pending', [
            'channel_id' => $channelId,
        ]);

        return AuthResult::challengeSent($token, array_merge(
            ['method' => $method],
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
     */
    public function verifyMagicLink(string $token): AuthResult
    {
        $channel = $this->mfaManager->getChannel('magic_link');

        if (!$channel || !($channel instanceof MagicLinkChannel)) {
            return AuthResult::failed('channel_unavailable', 'Magic link authentication is not available.');
        }

        $userId = $channel->verifyTokenAndResolveUser($token);

        if ($userId === null) {
            return AuthResult::failed('invalid_token', 'This link is invalid or has expired.');
        }

        return $this->resolvePostPrimary($userId, 'magic_link');
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

        if (!$this->policy->validatePolicyConflicts($sessionData['method'], $channelId)) {
            return AuthResult::failed('policy_conflict', 'This MFA method conflicts with your login method.');
        }

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

        $userData = $this->completeLogin($sessionData['user_id'], $sessionData['method'], $channelId);
        $this->session->destroy($sessionData['session_key']);

        return AuthResult::authenticated($sessionData['user_id'], $userData);
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
     * After primary auth succeeds, decide: complete login or require MFA.
     */
    private function resolvePostPrimary(int $userId, string $method, ?string $existingSessionKey = null): AuthResult
    {
        if (!$this->policy->isMfaRequired($userId)) {
            $userData = $this->completeLogin($userId, $method);

            return AuthResult::authenticated($userId, $userData);
        }

        // MFA is required — find available factors.
        $factors = $this->mfaManager->getUserFactors($userId);
        $availableFactors = [];

        foreach ($factors as $factor) {
            if ($factor->status !== ChannelStatus::Active) {
                continue;
            }

            if (!$this->policy->validatePolicyConflicts($method, $factor->channelId)) {
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
            $userData = $this->completeLogin($userId, $method);

            return AuthResult::authenticated($userId, $userData);
        }

        // Destroy old session if transitioning from challenge_pending.
        if ($existingSessionKey) {
            $this->session->destroy($existingSessionKey);
        }

        $token = $this->session->create($userId, $method, 'primary_verified');

        return AuthResult::mfaRequired($token, $availableFactors);
    }

    /**
     * Finalize the WordPress session.
     */
    private function completeLogin(int $userId, string $method, ?string $mfaChannel = null): array
    {
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
            'roles'        => $user->roles,
        ];
    }

    /**
     * Resolve a user by their identifier based on the auth method.
     */
    private function resolveUserByIdentifier(string $method, string $identifier): ?object
    {
        if ($method === 'phone_otp') {
            $users = get_users([
                'meta_key'   => 'wsms_phone',
                'meta_value' => $identifier,
                'number'     => 1,
            ]);

            return !empty($users) ? $users[0] : null;
        }

        // email_otp and magic_link use email.
        $user = get_user_by('email', $identifier);

        return $user ?: null;
    }

}
