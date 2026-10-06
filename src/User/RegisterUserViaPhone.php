<?php

namespace WP_SMS\User;

use WP_SMS\Helper;

if (!defined('ABSPATH')) exit;

class RegisterUserViaPhone
{
    private $mobileNumber;
    private $hashedUsername;
    private $userId;

    public function __construct($mobileNumber)
    {
        // Sanitize and prepare mobile number
        $this->mobileNumber = Helper::sanitizeMobileNumber($mobileNumber);
    }

    /**
     * Register user with phone number
     */
    public function register()
    {
        $result = $this->registerUser();

        // Store user meta data
        if (!is_wp_error($result)) {
            $this->saveMetas();
        }

        return $result;
    }

    private function registerUser()
    {
        if (!empty(Helper::getUserByPhoneNumber($this->mobileNumber))) {
            return new \WP_Error('number_exists', __('Another user with this phone number already exists.', 'wp-sms'));
        }

        add_filter('wp_send_new_user_notification_to_user', '__return_false');

        // The number is already verified by the SMS code. WP Armour's honeypot checks $_POST for its hidden field, which this
        // REST request never carries, so it would reject every new user with "Spamming or your Javascript is disabled".
        $honeypotPriority = has_filter('registration_errors', 'wpa_wpregistration_extra_validation');
        if ($honeypotPriority !== false) {
            remove_filter('registration_errors', 'wpa_wpregistration_extra_validation', $honeypotPriority);
        }

        try {
            $this->userId = register_new_user(
                $this->generateUniqueUsername(),
                $this->generateUniqueEmail()
            );
        } finally {
            remove_filter('wp_send_new_user_notification_to_user', '__return_false');

            if ($honeypotPriority !== false) {
                add_filter('registration_errors', 'wpa_wpregistration_extra_validation', $honeypotPriority, 3);
            }
        }

        return $this->userId;
    }

    private function saveMetas()
    {
        update_user_meta($this->userId, Helper::getUserMobileFieldName(), $this->mobileNumber);
    }

    /**
     * Generate a unique username
     *
     * @return string
     */
    public function generateUniqueUsername()
    {
        $this->hashedUsername = UserHelper::generateHashedUsername($this->mobileNumber);
        return $this->hashedUsername;
    }

    /**
     * Generate a unique email address
     */
    public function generateUniqueEmail()
    {
        if (empty($this->hashedUsername)) {
            $this->generateUniqueUsername();
        }

        return UserHelper::generateHashedEmail($this->hashedUsername, $this->mobileNumber);
    }
}
