<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

class LoginGuard
{
    public function registerHooks(): void
    {
        add_filter('authenticate', [$this, 'blockPendingUsers'], 99, 3);
    }

    /**
     * Block users with pending registration status from logging in via wp-login.php.
     *
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function blockPendingUsers($user, $username, $password)
    {
        if (!($user instanceof \WP_User)) {
            return $user;
        }

        $status = get_user_meta($user->ID, 'wsms_registration_status', true);
        if ($status === 'pending') {
            return new \WP_Error(
                'account_pending_verification',
                __('Your account is pending verification. Please use the login page to complete verification.', 'wp-sms')
            );
        }

        return $user;
    }
}
