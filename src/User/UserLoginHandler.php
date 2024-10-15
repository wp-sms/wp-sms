<?php

namespace WP_SMS\User;

class UserLoginHandler
{
    private $user;

    /**
     * @param \WP_User $user
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    public function login()
    {
        wp_clear_auth_cookie();
        wp_set_current_user($this->user->ID);
        wp_set_auth_cookie($this->user->ID, false, is_ssl());

        do_action('wp_login', $this->user->user_login, $this->user);
    }

    /**
     * Returns the URL to redirect the user after login/register.
     *
     * @param \WP_User $user
     * @param string $redirectUrl URL to redirect to if `redirect_to` was not set.
     * @param bool $isNewUser Is this user registered just now?
     *
     * @return string
     */
    public function getRedirectUrl($user, $redirectUrl, $isNewUser = false)
    {
        $redirectUrl = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : $redirectUrl;

        if ($isNewUser) {
            // User has registered just now

            $redirectUrl = apply_filters('registration_redirect', $redirectUrl, null);
        } else {
            // User was registered before and is logging in again

            $redirectUrl = apply_filters('login_redirect', $redirectUrl, $redirectUrl, $user);
        }

        return wp_validate_redirect(wp_sanitize_redirect(wp_unslash($redirectUrl)));
    }
}
