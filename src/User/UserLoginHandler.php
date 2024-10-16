<?php

namespace WP_SMS\User;

use WP_SMS\Helper;

class UserLoginHandler
{
    private $user;
    private $mobileNumber;

    /**
     * @param \WP_User $user
     */
    public function __construct($user)
    {
        $this->user         = $user;
        $this->mobileNumber = Helper::getUserMobileNumberByUserId($user->ID);
    }

    /**
     * Clears auth cookies and logs the user in.
     *
     * @return void
     */
    public function login()
    {
        $this->changeOldUsername();

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

    /**
     * Changes user's username from the old format to the new hashed format.
     *
     * @return void
     */
    public function changeOldUsername()
    {
        // Check if the username starts with 'phone_' (the old format)
        if (substr($this->user->user_login, 0, 6) === 'phone_') {
            $newUsername = Helper::generateHashedUsername($this->mobileNumber);
            $newEmail    = Helper::generateHashedEmail($newUsername, $this->mobileNumber);

            global $wpdb;
            $wpdb->update(
                $wpdb->users, 
                [
                    'user_login' => $newUsername,
                    'user_email' => $newEmail,
                ],
                ['ID' => $this->user->ID]
            );

            // Clear cache for the user to ensure new data is loaded
            clean_user_cache($this->user->ID);

            // Update the user object with the new username for the current session
            $this->user = get_user_by('ID', $this->user->ID);
        }
    }
}
