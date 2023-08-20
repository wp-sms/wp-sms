<?php

namespace WP_SMS\User;

use WP_SMS\Helper;

class RegisterUserViaPhone
{
    private $mobileNumber;

    public function __construct($mobileNumber)
    {
        // Sanitize and prepare mobile number
        $this->mobileNumber = str_replace('+', '', Helper::sanitizeMobileNumber($mobileNumber));
    }

    /**
     * Register user with phone number
     */
    public function register()
    {
        return register_new_user(
            $this->generateUniqueUsername(),
            $this->generateUniqueEmail()
        );
    }

    /**
     * Generate a unique username
     *
     * @return string
     */
    public function generateUniqueUsername()
    {
        $username = 'phone_' . $this->mobileNumber;

        /**
         * Allow to modify the username with filter
         */
        return apply_filters('wp_sms_registration_username', $username, $this->mobileNumber);
    }

    /**
     * Generate a unique email address
     */
    public function generateUniqueEmail()
    {
        $siteUrl    = get_bloginfo('url');
        $siteDomain = parse_url($siteUrl)['host'];

        if (strpos($siteDomain, '.') == false) {
            $siteDomain = $siteDomain . '.' . $siteDomain;
        }

        $emailAddress = $this->mobileNumber . '@' . $siteDomain;

        /**
         * Allow to modify the email address with filter
         */
        return apply_filters('wp_sms_registration_email', $emailAddress, $this->mobileNumber);
    }
}
