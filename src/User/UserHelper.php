<?php

namespace WP_SMS\User;

class UserHelper
{
    /**
     * Generates a username using the hashed mobile number.
     *
     * @param string $mobileNumber
     *
     * @return string
     */
    public static function generateHashedUsername($mobileNumber)
    {
        $hashedMobile = substr(wp_hash(str_replace('+', '', $mobileNumber)), 0, 8);
        $username     = 'wpsms_' . $hashedMobile;

        return apply_filters('wp_sms_registration_username', $username, $mobileNumber);
    }

    /**
     * Generates an e-mail using the hashed username.
     *
     * @param string $hashedUsername
     * @param string $mobileNumber
     *
     * @return string
     */
    public static function generateHashedEmail($hashedUsername, $mobileNumber)
    {
        $siteUrl    = get_bloginfo('url');
        $siteDomain = parse_url($siteUrl)['host'];

        if (strpos($siteDomain, '.') == false) {
            $siteDomain = $siteDomain . '.' . $siteDomain;
        }

        $emailAddress = "$hashedUsername@$siteDomain";

        return apply_filters('wp_sms_registration_email', $emailAddress, $mobileNumber);
    }
}
