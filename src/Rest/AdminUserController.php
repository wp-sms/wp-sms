<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\SettingsRepository;
use WSms\Enums\EventType;
use WSms\Mfa\MfaManager;
use WSms\Social\SocialAccountRepository;

defined('ABSPATH') || exit;

class AdminUserController
{
    private const NAMESPACE = 'wsms/v1';
    private const PHONE_PATTERN = '/^\+[1-9]\d{1,14}$/';
    private const VERIFICATION_CHANNELS = ['email', 'phone'];

    public function __construct(
        private AuditLogger $auditLogger,
        private MfaManager $mfaManager,
        private SocialAccountRepository $socialRepository,
        private AccountLockout $lockout,
        private AccountManager $accountManager,
        private SettingsRepository $settingsRepo,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/auth-summary', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetAuthSummary'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/mfa/(?P<channel>[a-z_]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleResetMfaChannel'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id'      => ['required' => true, 'type' => 'integer'],
                'channel' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/verification', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'handleSetVerification'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id'       => ['required' => true, 'type' => 'integer'],
                'channel'  => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'verified' => ['required' => true, 'type' => 'boolean'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/social/(?P<provider>[a-z]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleDisconnectSocial'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id'       => ['required' => true, 'type' => 'integer'],
                'provider' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/lockout', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleUnlockAccount'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/phone', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'handleSetPhone'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id'    => ['required' => true, 'type' => 'integer'],
                'phone' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/password-reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handlePasswordReset'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/activate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleActivateUser'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/send-verification', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSendVerification'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id'      => ['required' => true, 'type' => 'integer'],
                'channel' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function checkAdmin(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    public function handleGetAuthSummary(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        $emailVerified = (bool) get_user_meta($userId, 'wsms_email_verified', true);
        $phoneVerified = (bool) get_user_meta($userId, 'wsms_phone_verified', true);
        $phone = get_user_meta($userId, 'wsms_phone', true);

        $mfaFactors = $this->mfaManager->getActiveMfaFactors($userId);

        $socialAccounts = array_map(function (object $account) {
            $meta = json_decode($account->meta ?: '{}', true) ?? [];
            return [
                'provider'  => $account->channel_id,
                'email'     => $meta['email'] ?? null,
                'linked_at' => $account->created_at,
            ];
        }, $this->socialRepository->findByUserId($userId));

        $logs = $this->auditLogger->getEvents(['user_id' => $userId], 1, 10);
        $lockout = $this->lockout->isLocked($userId);
        $registrationStatus = get_user_meta($userId, 'wsms_registration_status', true) ?: null;
        $registrationCreatedAt = get_user_meta($userId, 'wsms_registration_created_at', true) ?: null;
        $hasPassword = AccountManager::hasUsablePassword($userId);
        $isPlaceholderEmail = AccountManager::isPlaceholderEmail($user->user_email);

        return new WP_REST_Response([
            'success' => true,
            'verification' => [
                'email' => [
                    'verified' => $emailVerified,
                    'address'  => $user->user_email,
                ],
                'phone' => [
                    'verified' => $phoneVerified,
                    'number'   => $phone ?: null,
                ],
            ],
            'mfa_factors'          => $mfaFactors,
            'social_accounts'      => $socialAccounts,
            'recent_activity'      => $logs['items'],
            'lockout'              => $lockout,
            'registration_status'  => $registrationStatus,
            'registration_created' => $registrationCreatedAt,
            'has_password'         => $hasPassword,
            'is_placeholder_email' => $isPlaceholderEmail,
        ]);
    }

    public function handleResetMfaChannel(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $channelId = $request->get_param('channel');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        $channel = $this->mfaManager->getChannel($channelId);
        if (!$channel) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'channel_not_found',
                'message' => "MFA channel '{$channelId}' not found.",
            ], 404);
        }

        $channel->unenroll($userId);

        if (!$this->mfaManager->hasActiveFactors($userId)) {
            update_user_meta($userId, 'wsms_mfa_enabled', '0');
        }

        $this->auditLogger->log(EventType::MfaAdminBypass, 'success', $userId, [
            'channel'  => $channelId,
            'admin_id' => get_current_user_id(),
        ], $channelId);

        return new WP_REST_Response([
            'success' => true,
            'message' => "MFA channel '{$channel->getName()}' has been reset for this user.",
        ]);
    }

    public function handleSetVerification(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $channel = $request->get_param('channel');
        $verified = (bool) $request->get_param('verified');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        if (!in_array($channel, self::VERIFICATION_CHANNELS, true)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_channel',
                'message' => "Channel must be 'email' or 'phone'.",
            ], 400);
        }

        $metaKey = $channel === 'email' ? 'wsms_email_verified' : 'wsms_phone_verified';
        update_user_meta($userId, $metaKey, $verified ? '1' : '0');

        $eventType = $channel === 'email' ? EventType::EmailVerified : EventType::PhoneVerified;
        $this->auditLogger->log($eventType, 'success', $userId, [
            'admin_override' => true,
            'admin_id'       => get_current_user_id(),
            'verified'       => $verified,
        ], $channel);

        return new WP_REST_Response([
            'success' => true,
            'message' => ucfirst($channel) . ' marked as ' . ($verified ? 'verified' : 'unverified') . '.',
        ]);
    }

    public function handleDisconnectSocial(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $provider = $request->get_param('provider');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        if (!in_array($provider, SocialAccountRepository::SOCIAL_PROVIDERS, true)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_provider',
                'message' => "Unknown social provider '{$provider}'.",
            ], 400);
        }

        $this->socialRepository->unlinkAccount($userId, $provider);

        $this->auditLogger->log(EventType::SocialAccountUnlinked, 'success', $userId, [
            'provider' => $provider,
            'admin_id' => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => ucfirst($provider) . ' account disconnected.',
        ]);
    }

    public function handleUnlockAccount(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        $this->lockout->reset($userId);

        $this->auditLogger->log(EventType::AccountUnlocked, 'success', $userId, [
            'admin_id' => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Account unlocked.',
        ]);
    }

    public function handleSetPhone(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $phone = (string) $request->get_param('phone');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        if ($phone === '') {
            delete_user_meta($userId, 'wsms_phone');
            delete_user_meta($userId, 'wsms_phone_verified');

            $this->auditLogger->log(EventType::EmailChange, 'success', $userId, [
                'admin_override' => true,
                'action'         => 'phone_removed',
                'admin_id'       => get_current_user_id(),
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Phone number removed.',
            ]);
        }

        if (!preg_match(self::PHONE_PATTERN, $phone)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_phone',
                'message' => 'Phone number must be in E.164 format (e.g. +1234567890).',
            ], 400);
        }

        if (AccountManager::isPhoneTaken($phone, $userId)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'phone_taken',
                'message' => 'This phone number is already in use by another account.',
            ], 409);
        }

        update_user_meta($userId, 'wsms_phone', $phone);
        update_user_meta($userId, 'wsms_phone_verified', '0');

        $this->auditLogger->log(EventType::EmailChange, 'success', $userId, [
            'admin_override' => true,
            'action'         => 'phone_set',
            'phone'          => $phone,
            'admin_id'       => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Phone number updated.',
        ]);
    }

    public function handlePasswordReset(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        if (AccountManager::isPlaceholderEmail($user->user_email)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'no_email',
                'message' => 'User has no real email address.',
            ], 400);
        }

        $this->accountManager->initiatePasswordReset($user->user_email);

        $this->auditLogger->log(EventType::PasswordResetRequest, 'success', $userId, [
            'admin_id'        => get_current_user_id(),
            'admin_initiated' => true,
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Password reset email sent.',
        ]);
    }

    public function handleActivateUser(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        $status = get_user_meta($userId, 'wsms_registration_status', true);
        if ($status !== 'pending') {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'not_pending',
                'message' => 'User is already active.',
            ], 400);
        }

        update_user_meta($userId, 'wsms_registration_status', 'active');
        delete_user_meta($userId, 'wsms_registration_created_at');

        $this->auditLogger->log(EventType::Register, 'success', $userId, [
            'admin_override' => true,
            'admin_id'       => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'User activated.',
        ]);
    }

    public function handleSendVerification(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $channel = (string) $request->get_param('channel');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        if (!in_array($channel, self::VERIFICATION_CHANNELS, true)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_channel',
                'message' => "Channel must be 'email' or 'phone'.",
            ], 400);
        }

        $channelSettings = $this->settingsRepo->channel($channel);
        if (empty($channelSettings['enabled'])) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'channel_disabled',
                'message' => "The {$channel} channel is disabled in settings.",
            ], 400);
        }

        if ($channel === 'email' && AccountManager::isPlaceholderEmail($user->user_email)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'no_email',
                'message' => 'No email on file.',
            ], 400);
        }

        if ($channel === 'phone' && empty(get_user_meta($userId, 'wsms_phone', true))) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'no_phone',
                'message' => 'No phone on file.',
            ], 400);
        }

        $result = $this->accountManager->resendVerification($userId, $channel);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Resolve a user by ID or return a 404 response.
     */
    private function resolveUser(int $userId): object
    {
        $user = get_userdata($userId);

        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'user_not_found',
                'message' => 'User not found.',
            ], 404);
        }

        return $user;
    }
}
