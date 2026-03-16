<?php

namespace WSms\Service\Admin;

use WSms\Auth\AccountLockout;
use WSms\Auth\AccountSuspension;
use WSms\Mfa\MfaManager;

defined('ABSPATH') || exit;

class UserListManager
{
    public const SVG_CIRCLE_CHECK = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
    public const SVG_CIRCLE_X = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>';
    public const SVG_SHIELD_CHECK = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>';
    public const SVG_LOCK = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
    public const SVG_BAN = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>';

    public function __construct(
        private MfaManager $mfaManager,
        private AccountLockout $lockout,
        private ?AccountSuspension $suspension = null,
    ) {
        add_filter('manage_users_columns', [$this, 'addColumns']);
        add_filter('manage_users_custom_column', [$this, 'renderColumn'], 10, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addColumns(array $columns): array
    {
        $columns['wsms_auth'] = __('Auth', 'wp-sms');

        return $columns;
    }

    public function renderColumn(string $output, string $columnName, int $userId): string
    {
        if ($columnName !== 'wsms_auth') {
            return $output;
        }

        $pills = [];

        // Account suspension (more severe, shown first)
        if ($this->suspension) {
            $suspensionState = $this->suspension->isSuspended($userId);
            if ($suspensionState['suspended']) {
                $pills[] = '<span class="wsms-pill wsms-pill--suspended" title="' . esc_attr(__('Account suspended', 'wp-sms')) . '">'
                    . self::SVG_BAN . ' ' . esc_html__('Suspended', 'wp-sms')
                    . '</span>';
            }
        }

        // Account lockout
        $lockoutState = $this->lockout->isLocked($userId);
        if ($lockoutState['locked']) {
            $pills[] = '<span class="wsms-pill wsms-pill--locked" title="' . esc_attr(__('Account locked', 'wp-sms')) . '">'
                . self::SVG_LOCK . ' ' . esc_html__('Locked', 'wp-sms')
                . '</span>';
        }

        // Pending registration
        $regStatus = get_user_meta($userId, 'wsms_registration_status', true);
        if ($regStatus === 'pending') {
            $pills[] = '<span class="wsms-pill wsms-pill--pending" title="' . esc_attr(__('Registration pending activation', 'wp-sms')) . '">'
                . esc_html__('Pending', 'wp-sms')
                . '</span>';
        }

        // Email verification
        $emailVerified = (bool) get_user_meta($userId, 'wsms_email_verified', true);
        $pills[] = $this->renderPill(
            $emailVerified,
            __('Email', 'wp-sms'),
            $emailVerified ? __('Email: verified', 'wp-sms') : __('Email: not verified', 'wp-sms'),
        );

        // Phone verification (only shown if user has a phone)
        $phone = get_user_meta($userId, 'wsms_phone', true);
        if ($phone) {
            $phoneVerified = (bool) get_user_meta($userId, 'wsms_phone_verified', true);
            $pills[] = $this->renderPill(
                $phoneVerified,
                __('Phone', 'wp-sms'),
                $phoneVerified ? __('Phone: verified', 'wp-sms') : __('Phone: not verified', 'wp-sms'),
            );
        }

        // MFA factors
        $factors = $this->mfaManager->getActiveMfaFactors($userId);
        if (!empty($factors)) {
            $names = array_column($factors, 'name');
            $tooltip = __('MFA:', 'wp-sms') . ' ' . implode(', ', $names);
            $pills[] = '<span class="wsms-pill wsms-pill--mfa" title="' . esc_attr($tooltip) . '">'
                . self::SVG_SHIELD_CHECK . ' ' . esc_html__('MFA', 'wp-sms')
                . '</span>';
        }

        return '<span class="wsms-auth-cell">' . implode('', $pills) . '</span>';
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'users.php') {
            return;
        }

        wp_enqueue_style(
            'wsms-admin',
            WP_SMS_URL . 'public/css/admin.css',
            [],
            WP_SMS_VERSION,
        );
    }

    private function renderPill(bool $verified, string $label, string $tooltip): string
    {
        $class = $verified ? 'wsms-pill--verified' : 'wsms-pill--unverified';
        $icon = $verified ? self::SVG_CIRCLE_CHECK : self::SVG_CIRCLE_X;

        return '<span class="wsms-pill ' . $class . '" title="' . esc_attr($tooltip) . '">'
            . $icon . ' ' . esc_html($label)
            . '</span>';
    }

}
