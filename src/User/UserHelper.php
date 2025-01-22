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

    /**
     * Check if the user is logged in.
     *
     * @return bool
     */
    public static function isLogin()
    {
        return is_user_logged_in();
    }

    /**
     * Get the current user ID.
     *
     * @return int
     */
    public static function getUserId()
    {
        $userId = self::isLogin() ? get_current_user_id() : 0;
        return apply_filters('wp_sms_user_id', $userId);
    }

    /**
     * Get user data by ID.
     *
     * @param int|false $userId
     * @return array
     */
    public static function getUser($userId = false)
    {
        $userId   = $userId ?: get_current_user_id();
        $userData = get_userdata($userId);
        $userInfo = get_object_vars($userData->data);

        $userInfo['role'] = $userData->roles;
        $userInfo['cap']  = $userData->caps;
        $userInfo['meta'] = array_map(function ($meta) {
            return $meta[0];
        }, get_user_meta($userId));

        return $userInfo;
    }

    /**
     * Get user meta data.
     *
     * @param string $metaKey
     * @param bool $single
     * @param int|false $userId
     * @return mixed
     */
    public static function getMeta($metaKey, $single = false, $userId = false)
    {
        $userId = $userId ?: get_current_user_id();
        return get_user_meta($userId, $metaKey, $single);
    }

    /**
     * Save user meta data.
     *
     * @param string $metaKey
     * @param mixed $metaValue
     * @param int|false $userId
     * @return bool
     */
    public static function saveMeta($metaKey, $metaValue, $userId = false)
    {
        $userId = $userId ?: get_current_user_id();
        return update_user_meta($userId, $metaKey, $metaValue);
    }

    /**
     * Get the full name of the user.
     *
     * @param int $userId
     * @return string
     */
    public static function getFullName($userId)
    {
        $userInfo = self::getUser($userId);

        if (!empty($userInfo['display_name'])) {
            return $userInfo['display_name'];
        }

        if (!empty($userInfo['meta']['first_name'])) {
            return "{$userInfo['meta']['first_name']} {$userInfo['meta']['last_name']}";
        }

        return $userInfo['user_login'];
    }

    /**
     * Check if a user exists by ID.
     *
     * @param int $userId
     * @return bool
     */
    public static function exists($userId)
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->users WHERE `ID` = %d", $userId));
        return $count > 0;
    }

    /**
     * Check if the current user is an admin.
     *
     * @return bool
     */
    public static function isAdmin()
    {
        return is_user_logged_in() && (is_multisite() ? is_super_admin() : current_user_can('manage_options'));
    }

    /**
     * Get the last login time of a user.
     *
     * @param int|false $userId
     * @return string|false
     */
    public static function getLastLogin($userId = false)
    {
        $userId    = $userId ?: get_current_user_id();
        $sessions  = get_user_meta($userId, 'session_tokens', true);

        if (!empty($sessions)) {
            $sessions = array_values($sessions);
            return $sessions[0]['login'] ?? false;
        }

        return false;
    }

    /**
     * Check if the current user has a specific capability.
     *
     * @param string $capability
     * @return bool|null
     */
    public static function hasCapability($capability)
    {
        if (!self::isLogin() || empty($capability)) {
            return false;
        }

        if (is_multisite()) {
            return current_user_can_for_site(get_current_blog_id(), $capability);
        }

        return current_user_can($capability);
    }

    /**
     * Validate a capability.
     *
     * @param string $capability
     * @return string
     */
    public static function validateCapability($capability)
    {
        global $wp_roles;

        if (!is_object($wp_roles) || !is_array($wp_roles->roles)) {
            return 'manage_options';
        }

        foreach ($wp_roles->roles as $role) {
            foreach ($role['capabilities'] as $key => $cap) {
                if ($capability === $key) {
                    return $capability;
                }
            }
        }

        return 'manage_options';
    }
}
