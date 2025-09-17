<?php

namespace WP_SMS\Services\OTP\Helpers;

/**
 * Username Helper
 *
 * Generates unique usernames for new user registrations.
 */
class UsernameHelper
{
    /**
     * Generate a unique username
     */
    public static function generate(): string
    {
        $base = 'wp-sms';
        $hash = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $username = $base . '-' . $hash;
        
        // Filter the generated username
        $username = apply_filters('wpsms_generated_username', $username);
        
        // Ensure uniqueness
        $counter = 1;
        $original_username = $username;
        
        while (username_exists($username)) {
            $username = $original_username . '-' . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * Check if username is available
     */
    public static function isAvailable(string $username): bool
    {
        return !username_exists($username);
    }

    /**
     * Sanitize username for WordPress
     */
    public static function sanitize(string $username): string
    {
        return sanitize_user($username);
    }
}
