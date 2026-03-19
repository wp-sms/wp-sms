<?php

namespace WSms\Service\Admin;

use WP_User;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AccountSuspension;
use WSms\Auth\SettingsRepository;
use WSms\Components\View;
use WSms\Mfa\MfaManager;
use WSms\Social\SocialAccountRepository;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class UserProfileSection
{
    public function __construct(
        private MfaManager $mfaManager,
        private SocialAccountRepository $socialRepository,
        private AuditLogger $auditLogger,
        private AccountLockout $lockout,
        private SettingsRepository $settingsRepo,
        private ?AccountSuspension $suspension = null,
    ) {
        add_action('edit_user_profile', [$this, 'render']);
        add_action('show_user_profile', [$this, 'render']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function render(WP_User $user): void
    {
        $userId = $user->ID;

        $emailVerified = (bool) get_user_meta($userId, UserMeta::EMAIL_VERIFIED, true);
        $phoneVerified = (bool) get_user_meta($userId, UserMeta::PHONE_VERIFIED, true);
        $phone = get_user_meta($userId, UserMeta::PHONE, true);

        $mfaFactors = $this->mfaManager->getActiveMfaFactors($userId);

        $socialAccounts = array_map(function (object $account) {
            $meta = json_decode($account->meta ?: '{}', true) ?? [];
            return [
                'provider'  => $account->channel_id,
                'email'     => $meta['email'] ?? '',
                'linked_at' => $account->created_at,
            ];
        }, $this->socialRepository->findByUserId($userId));

        $logs = $this->auditLogger->getEvents(['user_id' => $userId], 1, 1);
        $logsUrl = admin_url('admin.php?page=wsms#logs');
        $canManage = current_user_can('manage_options');

        $lockout = $this->lockout->isLocked($userId);
        $hasPassword = AccountManager::hasUsablePassword($userId);
        $isPlaceholderEmail = AccountManager::isPlaceholderEmail($user->user_email);
        $registrationStatus = get_user_meta($userId, UserMeta::REGISTRATION_STATUS, true) ?: null;
        $registrationCreatedAt = get_user_meta($userId, UserMeta::REGISTRATION_CREATED_AT, true) ?: null;

        $phoneEnabled = !empty($this->settingsRepo->channel('phone')['enabled']);
        $emailEnabled = !empty($this->settingsRepo->channel('email')['enabled']);
        $suspension = $this->suspension ? $this->suspension->isSuspended($userId) : AccountSuspension::NOT_SUSPENDED;

        View::load('admin/user-profile-section', [
            'user_id'                => $userId,
            'email'                  => $user->user_email,
            'email_verified'         => $emailVerified,
            'phone'                  => $phone,
            'phone_verified'         => $phoneVerified,
            'mfa_factors'            => $mfaFactors,
            'social_accounts'        => $socialAccounts,
            'logs_url'               => $logsUrl,
            'activity_count'         => $logs['total'],
            'can_manage'             => $canManage,
            'lockout'                => $lockout,
            'has_password'           => $hasPassword,
            'is_placeholder_email'   => $isPlaceholderEmail,
            'registration_status'    => $registrationStatus,
            'registration_created'   => $registrationCreatedAt,
            'phone_enabled'          => $phoneEnabled,
            'email_enabled'          => $emailEnabled,
            'suspension'             => $suspension,
        ]);
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['user-edit.php', 'profile.php'], true)) {
            return;
        }

        wp_enqueue_style(
            'wsms-admin',
            WP_SMS_URL . 'public/css/admin.css',
            [],
            WP_SMS_VERSION,
        );

        wp_enqueue_script(
            'wsms-admin-user-profile',
            WP_SMS_URL . 'public/js/wsms-admin-user-profile.js',
            [],
            WP_SMS_VERSION,
            true,
        );

        wp_localize_script('wsms-admin-user-profile', 'wsmsUserProfile', [
            'restUrl' => rest_url('wsms/v1/auth/admin/users/'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }
}
