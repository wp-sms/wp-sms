<?php

namespace WSms\Auth;

use WSms\Audit\AuditLogger;
use WSms\Auth\ValueObjects\AuthResult;
use WSms\Auth\ValueObjects\IdentifyResult;
use WSms\Enums\AuthErrorCode;
use WSms\Enums\EventType;
use WSms\Enums\SessionStage;
use WSms\Mfa\Contracts\SupportsTokenVerification;
use WSms\Mfa\MfaManager;
use WSms\Support\PhoneValidator;
use WSms\Support\UserMeta;

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
        private SettingsRepository $settingsRepo,
        private ?TrustedDeviceManager $trustedDevices = null,
        private ?AccountSuspension $suspension = null,
        private ?FreshnessManager $freshnessManager = null,
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
        $registrationEnabled = !empty($this->settingsRepo->get('enable_registration'));
        $autoCreate = $registrationEnabled && !empty($this->settingsRepo->get('auto_create_users'));

        return new IdentifyResult(
            identifierType: $identifierType,
            userFound: false,
            availableMethods: [],
            defaultMethod: null,
            registrationAvailable: $autoCreate,
            registrationFields: $autoCreate ? $this->policy->getEffectiveRegistrationFields() : [],
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
        if (!in_array('password', $this->policy->getAvailablePrimaryMethods(), true)) {
            return AuthResult::failed(AuthErrorCode::MethodDisabled, __('Password login is not enabled.', 'wp-sms'));
        }

        $identifierType = $this->detectIdentifierType($username);
        $resolvedUser = $this->resolveUserByAnyIdentifier($username, $identifierType);

        if ($resolvedUser) {
            $locked = $this->checkLockout($resolvedUser->ID);
            if ($locked) {
                return $locked;
            }

            $suspended = $this->checkSuspension($resolvedUser->ID);
            if ($suspended) {
                return $suspended;
            }
        }

        // Use the resolved user's login name for wp_authenticate so email/phone identifiers work.
        $loginName = $resolvedUser ? $resolvedUser->user_login : $username;
        $user = wp_authenticate($loginName, $password);

        if (is_wp_error($user)) {
            if ($resolvedUser) {
                $this->lockout->recordFailure($resolvedUser->ID);
            } else {
                wp_hash_password($password); // timing equalization when user not found
            }

            $errorCode = $user->get_error_code();

            $this->auditLogger->log(EventType::LoginFailure, 'failure', null, [
                'channel' => 'password',
                'method'  => 'password',
                'reason'  => $errorCode,
            ]);

            do_action('wp_login_failed', $username, $user);

            // Surface actionable errors; keep credential errors generic.
            if (in_array($errorCode, ['account_suspended', 'account_pending_verification'], true)) {
                $enum = AuthErrorCode::tryFrom($errorCode);
                return AuthResult::failed($enum ?? $errorCode, $user->get_error_message());
            }

            return AuthResult::failed(AuthErrorCode::InvalidCredentials, __('Invalid username or password.', 'wp-sms'));
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
            return AuthResult::failed(AuthErrorCode::MethodDisabled, __('This authentication method is not enabled.', 'wp-sms'));
        }

        // Resolve user by identifier.
        $user = $this->resolveUserByIdentifier($channel, $identifier);

        if (!$user) {
            return AuthResult::failed(AuthErrorCode::InvalidCredentials, __('Invalid credentials.', 'wp-sms'));
        }

        $locked = $this->checkLockout($user->ID);
        if ($locked) {
            return $locked;
        }

        $suspended = $this->checkSuspension($user->ID);
        if ($suspended) {
            return $suspended;
        }

        $channelObj = $this->mfaManager->getChannel($channel);

        if (!$channelObj) {
            return AuthResult::failed(AuthErrorCode::ChannelUnavailable, __('Authentication channel is not available.', 'wp-sms'));
        }

        if (!$channelObj->isEnrolled($user->ID)) {
            if ($channelObj->supportsAutoEnrollment()) {
                $channelObj->enroll($user->ID, []);
            } else {
                return AuthResult::failed(AuthErrorCode::NotEnrolled, __('You are not enrolled in this authentication method.', 'wp-sms'));
            }
        }

        $challengeResult = $channelObj->sendChallenge($user->ID);

        if (!$challengeResult->success) {
            return AuthResult::challengeFailed($challengeResult->message);
        }

        $token = $this->session->create($user->ID, $channel, SessionStage::ChallengePending, [
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
        $sessionData = $this->requireSession($sessionToken, SessionStage::ChallengePending);
        if ($sessionData instanceof AuthResult) {
            return $sessionData;
        }

        $channelId = $sessionData['channel_id'] ?? null;
        $channel = $channelId ? $this->mfaManager->getChannel($channelId) : null;

        if (!$channel) {
            return AuthResult::failed(AuthErrorCode::ChannelUnavailable, __('Authentication channel is not available.', 'wp-sms'));
        }

        $verified = $channel->verify($sessionData['user_id'], $code);

        if (!$verified) {
            return AuthResult::failed(AuthErrorCode::InvalidCode, __('The code you entered is incorrect.', 'wp-sms'));
        }

        return $this->resolvePostPrimary(
            $sessionData['user_id'],
            $sessionData['method'],
            $sessionData['session_key'],
        );
    }

    /**
     * Verify a magic link token (comes directly from URL, no session token).
     * Iterates all channels that support token verification.
     */
    public function verifyMagicLink(string $token): AuthResult
    {
        foreach ($this->mfaManager->getAvailableChannels() as $channel) {
            if (!$channel instanceof SupportsTokenVerification) {
                continue;
            }

            $userId = $channel->verifyTokenAndResolveUser($token);

            if ($userId !== null) {
                return $this->resolvePostPrimary($userId, $channel->getId());
            }
        }

        return AuthResult::failed(AuthErrorCode::InvalidToken, __('This link is invalid or has expired.', 'wp-sms'));
    }

    /**
     * Send an MFA challenge after primary auth is verified.
     */
    public function sendMfaChallenge(string $sessionToken, string $channelId): AuthResult
    {
        $sessionData = $this->requireSession($sessionToken, SessionStage::PrimaryVerified);
        if ($sessionData instanceof AuthResult) {
            return $sessionData;
        }

        $channel = $this->mfaManager->getChannel($channelId);

        if (!$channel || !$channel->supportsMfa()) {
            return AuthResult::failed(AuthErrorCode::InvalidChannel, __('Invalid MFA channel.', 'wp-sms'));
        }

        if (!in_array($channelId, $this->policy->getAvailableMfaFactors(), true)) {
            return AuthResult::failed(AuthErrorCode::MethodDisabled, __('This MFA method is not enabled.', 'wp-sms'));
        }

        if (!$channel->isEnrolled($sessionData['user_id'])) {
            return AuthResult::failed(AuthErrorCode::NotEnrolled, __('You are not enrolled in this MFA method.', 'wp-sms'));
        }

        $challengeResult = $channel->sendChallenge($sessionData['user_id']);

        if (!$challengeResult->success) {
            return AuthResult::challengeFailed($challengeResult->message);
        }

        $this->session->update($sessionData['session_key'], [
            'stage'          => SessionStage::MfaPending->value,
            'mfa_channel_id' => $channelId,
        ]);

        return AuthResult::challengeSent($sessionToken, $challengeResult->meta);
    }

    /**
     * Verify MFA and complete login.
     */
    public function verifyMfa(string $sessionToken, string $code, string $channelId): AuthResult
    {
        $sessionData = $this->requireSession($sessionToken, SessionStage::PrimaryVerified, SessionStage::MfaPending);
        if ($sessionData instanceof AuthResult) {
            return $sessionData;
        }

        $channel = $this->mfaManager->getChannel($channelId);

        if (!$channel || !$channel->supportsMfa()) {
            return AuthResult::failed(AuthErrorCode::InvalidChannel, __('Invalid MFA channel.', 'wp-sms'));
        }

        if (!in_array($channelId, $this->policy->getAvailableMfaFactors(), true)) {
            return AuthResult::failed(AuthErrorCode::MethodDisabled, __('This MFA method is not enabled.', 'wp-sms'));
        }

        $verified = $channel->verify($sessionData['user_id'], $code);

        if (!$verified) {
            return AuthResult::failed(AuthErrorCode::InvalidCode, __('The code you entered is incorrect.', 'wp-sms'));
        }

        $this->session->destroy($sessionData['session_key']);

        return $this->resolveLogin($sessionData['user_id'], $sessionData['method'], $channelId);
    }

    /**
     * Resend challenge for the current session's channel.
     */
    public function resendChallenge(string $sessionToken): AuthResult
    {
        $sessionData = $this->requireSession(
            $sessionToken,
            SessionStage::ChallengePending,
            SessionStage::MfaPending,
        );
        if ($sessionData instanceof AuthResult) {
            return $sessionData;
        }

        $channelId = $sessionData['mfa_channel_id'] ?? $sessionData['channel_id'] ?? null;
        $channel = $channelId ? $this->mfaManager->getChannel($channelId) : null;

        if (!$channel) {
            return AuthResult::failed(AuthErrorCode::ChannelUnavailable, __('No channel found for this session.', 'wp-sms'));
        }

        $challengeResult = $channel->sendChallenge($sessionData['user_id']);

        if (!$challengeResult->success) {
            return AuthResult::challengeFailed($challengeResult->message);
        }

        return AuthResult::challengeSent($sessionToken, $challengeResult->meta);
    }

    /**
     * Complete verification during login and finish login if all verifications are done.
     */
    public function completeVerification(string $sessionToken): AuthResult
    {
        $sessionData = $this->requireSession($sessionToken, SessionStage::VerificationPending);
        if ($sessionData instanceof AuthResult) {
            return $sessionData;
        }

        $suspended = $this->checkSuspension($sessionData['user_id']);
        if ($suspended) {
            return $suspended;
        }

        $pending = $this->policy->getPendingVerifications($sessionData['user_id']);

        if (!empty($pending)) {
            return AuthResult::verificationRequired($sessionToken, $pending);
        }

        $this->session->destroy($sessionData['session_key']);
        $userData = $this->completeLogin($sessionData['user_id'], $sessionData['method']);

        return $this->authenticatedWithGraceInfo($sessionData['user_id'], $userData);
    }

    /**
     * Entry point for social login into the post-primary auth pipeline (MFA/verification checks).
     */
    public function resolveAuthFromSocial(int $userId, string $method): AuthResult
    {
        return $this->resolvePostPrimary($userId, $method);
    }

    /**
     * Resolve a PrimaryVerified session into its available MFA factors.
     *
     * Used by redirect-based flows (wp-login.php, social login) where the
     * frontend receives a session token via URL but has no factor list.
     */
    public function getSessionMfaFactors(string $sessionToken): AuthResult
    {
        $sessionData = $this->requireSession($sessionToken, SessionStage::PrimaryVerified);
        if ($sessionData instanceof AuthResult) {
            return $sessionData;
        }

        $availableFactors = $this->getEnabledMfaFactorsForUser($sessionData['user_id']);

        return AuthResult::mfaRequired($sessionToken, $availableFactors);
    }

    private function resolvePostPrimary(int $userId, string $method, ?string $existingSessionKey = null): AuthResult
    {
        if (!$this->policy->isMfaRequired($userId)) {
            return $this->resolveLogin($userId, $method);
        }

        // MFA is required — find available factors, filtered by enabled channels.
        $availableFactors = $this->getEnabledMfaFactorsForUser($userId);

        // MFA required but no enrolled factors — gate the user for enrollment.
        if (empty($availableFactors)) {
            if ($existingSessionKey) {
                $this->session->destroy($existingSessionKey);
            }
            return $this->gateForEnrollment($userId, $method);
        }

        // Trusted device: skip MFA if the browser has a valid trust cookie.
        if ($this->trustedDevices && $this->trustedDevices->isTrusted($userId)) {
            return $this->resolveLogin($userId, $method);
        }

        // Destroy old session if transitioning from challenge_pending.
        if ($existingSessionKey) {
            $this->session->destroy($existingSessionKey);
        }

        $token = $this->session->create($userId, $method, SessionStage::PrimaryVerified);

        return AuthResult::mfaRequired($token, $availableFactors);
    }

    private function gateForEnrollment(int $userId, string $method): AuthResult
    {
        update_user_meta($userId, UserMeta::MFA_ENROLLMENT_PENDING, '1');
        wp_set_auth_cookie($userId, true);
        wp_set_current_user($userId);
        $this->freshnessManager?->markFresh($userId);

        $this->auditLogger->log(EventType::LoginSuccess, 'enrollment_gated', $userId, [
            'method' => $method,
            'reason' => 'mfa_enrollment_required',
        ]);

        return AuthResult::mfaEnrollmentRequired($this->policy->getAvailableMfaFactors());
    }

    /**
     * Check verification requirements and either complete login or require verification.
     */
    private function resolveLogin(int $userId, string $method, ?string $mfaChannel = null): AuthResult
    {
        $suspended = $this->checkSuspension($userId);
        if ($suspended) {
            return $suspended;
        }

        $pending = $this->policy->getPendingVerifications($userId);

        if (!empty($pending)) {
            $this->sendLoginVerifications($userId, $pending);

            $token = $this->session->create($userId, $method, SessionStage::VerificationPending);

            return AuthResult::verificationRequired($token, $pending);
        }

        $userData = $this->completeLogin($userId, $method, $mfaChannel);

        return $this->authenticatedWithGraceInfo($userId, $userData);
    }

    private function authenticatedWithGraceInfo(int $userId, array $userData): AuthResult
    {
        $meta = [];
        $graceInfo = $this->policy->getGracePeriodInfo($userId);
        if ($graceInfo) {
            $meta['grace_period'] = $graceInfo;
        }

        return AuthResult::authenticated($userId, $userData, $meta);
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
        $this->freshnessManager?->markFresh($userId);

        $this->auditLogger->log(EventType::LoginSuccess, 'success', $userId, [
            'channel'     => $method,
            'method'      => $method,
            'mfa_channel' => $mfaChannel,
        ]);

        $user = get_userdata($userId);

        if (!$user) {
            throw new \RuntimeException("User {$userId} not found");
        }

        do_action('wp_login', $user->user_login, $user);
        do_action('wsms_login_success', $userId, $method, $mfaChannel);

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
     * Validate a session token and ensure it's in one of the allowed stages.
     *
     * @return AuthResult|array Session data on success, AuthResult error on failure.
     */
    private function requireSession(string $token, SessionStage ...$allowedStages): AuthResult|array
    {
        $data = $this->session->validate($token);

        if ($data === null) {
            return AuthResult::invalidToken();
        }

        $stage = SessionStage::tryFrom($data['stage'] ?? '');

        if ($stage === null || !in_array($stage, $allowedStages, true)) {
            return AuthResult::failed(AuthErrorCode::InvalidStage, __('Invalid session stage.', 'wp-sms'));
        }

        return $data;
    }

    private function checkLockout(int $userId): ?AuthResult
    {
        $lockStatus = $this->lockout->isLocked($userId);

        if ($lockStatus['locked']) {
            return AuthResult::failed(AuthErrorCode::AccountLocked, __('Account is temporarily locked.', 'wp-sms'), [
                'retry_after' => $lockStatus['until'],
            ]);
        }

        return null;
    }

    private function checkSuspension(int $userId): ?AuthResult
    {
        if (!$this->suspension) {
            return null;
        }

        $status = $this->suspension->isSuspended($userId);

        if ($status['suspended']) {
            return AuthResult::failed(AuthErrorCode::AccountSuspended, AccountSuspension::errorMessage());
        }

        return null;
    }

    private function getEnabledMfaFactorsForUser(int $userId): array
    {
        $factors = $this->mfaManager->getActiveMfaFactors($userId);
        $enabledIds = $this->policy->getAvailableMfaFactors();

        return array_values(array_filter(
            $factors,
            fn($f) => in_array($f['channel_id'], $enabledIds, true),
        ));
    }

    /**
     * Resolve a user by their identifier based on the channel.
     */
    private function resolveUserByIdentifier(string $channel, string $identifier): ?object
    {
        if ($channel === 'phone') {
            return $this->fuzzyPhoneLookup($identifier);
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
            return $this->fuzzyPhoneLookup($identifier);
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

    /**
     * Look up a user by phone, trying E.164 first then fuzzy variants.
     *
     * Handles the common case where a user types `12025551234` instead of `+12025551234`.
     */
    private function fuzzyPhoneLookup(string $identifier): ?object
    {
        // Fast path: valid E.164 input → single query.
        $e164 = PhoneValidator::toE164($identifier);
        if ($e164 !== null) {
            $user = $this->findUserByPhone($e164);
            if ($user) {
                return $user;
            }
        }

        // Fallback: stripped input that couldn't be normalized to E.164.
        $cleaned = PhoneValidator::stripFormatting($identifier);
        if ($cleaned === $e164) {
            return null;
        }

        $user = $this->findUserByPhone($cleaned);
        if ($user) {
            return $user;
        }

        // Try with/without + prefix.
        if (str_starts_with($cleaned, '+')) {
            return $this->findUserByPhone(substr($cleaned, 1));
        } else {
            return $this->findUserByPhone('+' . $cleaned);
        }
    }

    private function findUserByPhone(string $phone): ?object
    {
        $users = get_users([
            'meta_key'   => UserMeta::PHONE,
            'meta_value' => $phone,
            'number'     => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

}
