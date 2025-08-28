<?php

namespace WP_SMS\Services\OTP\Hooks;

/**
 * Authentication Hooks
 *
 * Provides action and filter hooks for authentication events.
 */
class AuthHooks
{
    /**
     * Initialize hooks
     */
    public function __construct()
    {
        // Hooks are called by other components, this class provides the interface
    }

    /**
     * Fire authentication success action
     */
    public static function fireAuthSuccess(int $user_id, array $context = []): void
    {
        do_action('wpsms_auth_success', $user_id, $context);
    }

    /**
     * Fire authentication failure action
     */
    public static function fireAuthFailure(array $error, array $context = []): void
    {
        do_action('wpsms_auth_failure', $error, $context);
    }

    /**
     * Fire before OTP send action
     */
    public static function fireBeforeSendOtp(array $payload, array $context = []): void
    {
        do_action('wpsms_before_send_otp', $payload, $context);
    }

    /**
     * Fire after OTP verify action
     */
    public static function fireAfterVerifyOtp(array $result, array $context = []): void
    {
        do_action('wpsms_after_verify_otp', $result, $context);
    }

    /**
     * Get allowed authentication methods
     */
    public static function getAllowedMethods(array $context = []): array
    {
        $default_methods = ['password', 'otp', 'magic'];
        
        return apply_filters('wpsms_auth_allowed_methods', $default_methods, $context);
    }

    /**
     * Get allowed authentication fields
     */
    public static function getAllowedFields(array $context = []): array
    {
        $default_fields = [
            'username' => true,
            'email' => true,
            'phone' => true,
            'password' => true,
        ];
        
        return apply_filters('wpsms_auth_allowed_fields', $default_fields, $context);
    }
}
