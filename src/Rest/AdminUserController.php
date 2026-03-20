<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AccountSuspension;
use WSms\Auth\SettingsRepository;
use WSms\Enums\EventType;
use WSms\Enums\TemplateType;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Template\TemplateManager;
use WSms\Mfa\MfaManager;
use WSms\Social\SocialAccountRepository;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class AdminUserController extends Controller
{
    private const PHONE_PATTERN = '/^\+[1-9]\d{1,14}$/';
    private const VERIFICATION_CHANNELS = ['email', 'phone'];

    public function __construct(
        private AuditLogger $auditLogger,
        private MfaManager $mfaManager,
        private SocialAccountRepository $socialRepository,
        private AccountLockout $lockout,
        private AccountManager $accountManager,
        private SettingsRepository $settingsRepo,
        private TemplateManager $templateManager,
        private MessageDispatcher $messageDispatcher,
        private ?AccountSuspension $suspension = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/auth-summary', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetAuthSummary'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/mfa/(?P<channel>[a-z_]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleResetMfaChannel'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id'      => ['required' => true, 'type' => 'integer'],
                'channel' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/verification', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'handleSetVerification'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id'       => ['required' => true, 'type' => 'integer'],
                'channel'  => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'verified' => ['required' => true, 'type' => 'boolean'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/social/(?P<provider>[a-z]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleDisconnectSocial'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id'       => ['required' => true, 'type' => 'integer'],
                'provider' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/lockout', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleUnlockAccount'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/phone', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'handleSetPhone'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id'    => ['required' => true, 'type' => 'integer'],
                'phone' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/password-reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handlePasswordReset'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/activate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleActivateUser'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/send-verification', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSendVerification'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id'      => ['required' => true, 'type' => 'integer'],
                'channel' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/suspend', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSuspendUser'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/suspension', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleUnsuspendUser'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    public function handleGetAuthSummary(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        $emailVerified = (bool) get_user_meta($userId, UserMeta::EMAIL_VERIFIED, true);
        $phoneVerified = (bool) get_user_meta($userId, UserMeta::PHONE_VERIFIED, true);
        $phone = get_user_meta($userId, UserMeta::PHONE, true);

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
        $registrationStatus = get_user_meta($userId, UserMeta::REGISTRATION_STATUS, true) ?: null;
        $registrationCreatedAt = get_user_meta($userId, UserMeta::REGISTRATION_CREATED_AT, true) ?: null;
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
            'suspension'           => $this->suspension ? $this->suspension->isSuspended($userId) : AccountSuspension::NOT_SUSPENDED,
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
                'message' => sprintf(__('MFA channel \'%s\' not found.', 'wp-sms'), $channelId),
            ], 404);
        }

        $channel->unenroll($userId);

        if (!$this->mfaManager->hasActiveFactors($userId)) {
            update_user_meta($userId, UserMeta::MFA_ENABLED, '0');
        }

        $this->auditLogger->log(EventType::MfaAdminBypass, 'success', $userId, [
            'channel'  => $channelId,
            'admin_id' => get_current_user_id(),
        ], $channelId);

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf(__('MFA channel \'%s\' has been reset for this user.', 'wp-sms'), $channel->getName()),
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
                'message' => __("Channel must be 'email' or 'phone'.", 'wp-sms'),
            ], 400);
        }

        $metaKey = $channel === 'email' ? UserMeta::EMAIL_VERIFIED : UserMeta::PHONE_VERIFIED;
        update_user_meta($userId, $metaKey, $verified ? '1' : '0');

        $eventType = $channel === 'email' ? EventType::EmailVerified : EventType::PhoneVerified;
        $this->auditLogger->log($eventType, 'success', $userId, [
            'admin_override' => true,
            'admin_id'       => get_current_user_id(),
            'verified'       => $verified,
        ], $channel);

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf(__('%s marked as %s.', 'wp-sms'), ucfirst($channel), $verified ? __('verified', 'wp-sms') : __('unverified', 'wp-sms')),
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
                'message' => sprintf(__('Unknown social provider \'%s\'.', 'wp-sms'), $provider),
            ], 400);
        }

        $this->socialRepository->unlinkAccount($userId, $provider);

        $this->auditLogger->log(EventType::SocialAccountUnlinked, 'success', $userId, [
            'provider' => $provider,
            'admin_id' => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf(__('%s account disconnected.', 'wp-sms'), ucfirst($provider)),
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
            'message' => __('Account unlocked.', 'wp-sms'),
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
            delete_user_meta($userId, UserMeta::PHONE);
            delete_user_meta($userId, UserMeta::PHONE_VERIFIED);

            $this->auditLogger->log(EventType::PhoneChange, 'success', $userId, [
                'admin_override' => true,
                'action'         => 'phone_removed',
                'admin_id'       => get_current_user_id(),
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Phone number removed.', 'wp-sms'),
            ]);
        }

        if (!preg_match(self::PHONE_PATTERN, $phone)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_phone',
                'message' => __('Phone number must be in E.164 format (e.g. +1234567890).', 'wp-sms'),
            ], 400);
        }

        if (AccountManager::isPhoneTaken($phone, $userId)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'phone_taken',
                'message' => __('This phone number is already associated with another account.', 'wp-sms'),
            ], 409);
        }

        update_user_meta($userId, UserMeta::PHONE, $phone);
        update_user_meta($userId, UserMeta::PHONE_VERIFIED, '0');

        $this->auditLogger->log(EventType::PhoneChange, 'success', $userId, [
            'admin_override' => true,
            'action'         => 'phone_set',
            'phone'          => $phone,
            'admin_id'       => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Phone number updated.', 'wp-sms'),
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
                'message' => __('User has no real email address.', 'wp-sms'),
            ], 400);
        }

        $this->accountManager->initiatePasswordReset($user->user_email);

        $this->auditLogger->log(EventType::PasswordResetRequest, 'success', $userId, [
            'admin_id'        => get_current_user_id(),
            'admin_initiated' => true,
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Password reset email sent.', 'wp-sms'),
        ]);
    }

    public function handleActivateUser(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        $status = get_user_meta($userId, UserMeta::REGISTRATION_STATUS, true);
        if ($status !== 'pending') {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'not_pending',
                'message' => __('User is already active.', 'wp-sms'),
            ], 400);
        }

        update_user_meta($userId, UserMeta::REGISTRATION_STATUS, 'active');
        delete_user_meta($userId, UserMeta::REGISTRATION_CREATED_AT);

        $this->auditLogger->log(EventType::Register, 'success', $userId, [
            'admin_override' => true,
            'admin_id'       => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('User activated.', 'wp-sms'),
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
                'message' => __("Channel must be 'email' or 'phone'.", 'wp-sms'),
            ], 400);
        }

        $channelSettings = $this->settingsRepo->channel($channel);
        if (empty($channelSettings['enabled'])) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'channel_disabled',
                'message' => sprintf(__('The %s channel is disabled in settings.', 'wp-sms'), $channel),
            ], 400);
        }

        if ($channel === 'email' && AccountManager::isPlaceholderEmail($user->user_email)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'no_email',
                'message' => __('No email on file.', 'wp-sms'),
            ], 400);
        }

        if ($channel === 'phone' && empty(get_user_meta($userId, UserMeta::PHONE, true))) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'no_phone',
                'message' => __('No phone on file.', 'wp-sms'),
            ], 400);
        }

        $result = $this->accountManager->resendVerification($userId, $channel);

        return new WP_REST_Response($result->toArray(), $result->success ? 200 : 400);
    }

    public function handleSuspendUser(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        if ($userId === get_current_user_id()) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'self_suspension',
                'message' => __('You cannot suspend your own account.', 'wp-sms'),
            ], 400);
        }

        if (!$this->suspension) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'not_available',
                'message' => __('Suspension feature is not available.', 'wp-sms'),
            ], 400);
        }

        $status = $this->suspension->isSuspended($userId);
        if ($status['suspended']) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'already_suspended',
                'message' => __('User is already suspended.', 'wp-sms'),
            ], 400);
        }

        $adminId = get_current_user_id();
        $this->suspension->suspend($userId, $adminId);

        $this->auditLogger->log(EventType::AccountSuspended, 'success', $userId, [
            'admin_id' => $adminId,
        ]);

        $this->sendSuspensionNotification($user);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('User suspended.', 'wp-sms'),
        ]);
    }

    public function handleUnsuspendUser(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');

        $user = $this->resolveUser($userId);
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        if (!$this->suspension) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'not_available',
                'message' => __('Suspension feature is not available.', 'wp-sms'),
            ], 400);
        }

        $status = $this->suspension->isSuspended($userId);
        if (!$status['suspended']) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'not_suspended',
                'message' => __('User is not suspended.', 'wp-sms'),
            ], 400);
        }

        $this->suspension->unsuspend($userId);

        $this->auditLogger->log(EventType::AccountUnsuspended, 'success', $userId, [
            'admin_id' => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('User unsuspended.', 'wp-sms'),
        ]);
    }

    private function sendSuspensionNotification(object $user): void
    {
        if (!$this->templateManager->isEnabled(TemplateType::AccountSuspended->value)) {
            return;
        }

        if (AccountManager::isPlaceholderEmail($user->user_email)) {
            return;
        }

        try {
            $message = $this->templateManager->renderToMessage(
                TemplateType::AccountSuspended->value,
                'email',
                $user->user_email,
                ['user_name' => $user->display_name],
            );
            $this->messageDispatcher->sendImmediate($message);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WP-SMS] Failed to send suspension notification: ' . $e->getMessage());
            }
        }
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
                'message' => __('User not found.', 'wp-sms'),
            ], 404);
        }

        return $user;
    }
}
