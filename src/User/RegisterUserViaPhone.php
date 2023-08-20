<?php

namespace WP_SMS\User;

use WP_SMS\Helper;

class RegisterUserViaPhone
{
    public $mobileNumber;

    public function __construct($mobileNumber)
    {

        // Sanitize and set mobile_number
        $mobileNumber       = Helper::sanitizeMobileNumber($mobileNumber);
        $mobileNumber       = str_replace('+', '', $mobileNumber);
        $this->mobileNumber = $mobileNumber;
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
        return apply_filters('wp_sms_pro_username_registration', $username, $this->mobileNumber);
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
        return apply_filters('wp_sms_pro_email_registration', $emailAddress, $this->mobileNumber);
    }
}
