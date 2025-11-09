<?php

namespace WP_SMS\Services\OTP\Helpers;

use WP_SMS\Option;
use WP_User;

/**
 * Password Reset Helper
 *
 * Manages password reset configuration and business logic
 */
class PasswordResetHelper
{
    /**
     * Check if password reset is enabled
     *
     * @return bool
     */
    public static function isPasswordResetEnabled(): bool
    {
        return (bool) Option::getOption('otp_password_reset_enabled', false, true);
    }

    /**
     * Check if password reset is available (enabled AND has recovery method)
     *
     * @return bool
     */
    public static function isPasswordResetAvailable(): bool
    {
        if (!self::isPasswordResetEnabled()) {
            return false;
        }

        // Check if at least one recovery identifier is enabled
        $allowedIdentifiers = self::getAllowedIdentifiers();
        
        return !empty($allowedIdentifiers);
    }

    /**
     * Get token expiry time in minutes
     *
     * @return int
     */
    public static function getTokenExpiry(): int
    {
        return (int) Option::getOption('otp_password_reset_token_expiry', false, 15);
    }

    /**
     * Get token expiry time in seconds
     *
     * @return int
     */
    public static function getTokenExpirySeconds(): int
    {
        return self::getTokenExpiry() * 60;
    }

    /**
     * Check if auto-login after reset is enabled
     *
     * @return bool
     */
    public static function isAutoLoginEnabled(): bool
    {
        return (bool) Option::getOption('otp_password_reset_auto_login', false, true);
    }

    /**
     * Get allowed recovery identifiers
     *
     * @return array
     */
    public static function getAllowedIdentifiers(): array
    {
        $identifiers = Option::getOption('otp_password_reset_allowed_identifiers', false, ['email', 'phone']);
        
        if (is_string($identifiers)) {
            $identifiers = json_decode($identifiers, true);
        }

        return is_array($identifiers) ? $identifiers : [];
    }

    /**
     * Check if identifier verification is required
     *
     * @return bool
     */
    public static function requiresVerification(): bool
    {
        return (bool) Option::getOption('otp_password_reset_require_verification', false, true);
    }

    /**
     * Get minimum password length
     *
     * @return int
     */
    public static function getMinPasswordLength(): int
    {
        return (int) Option::getOption('otp_password_reset_min_password_length', false, 8);
    }

    /**
     * Check if identifier is allowed for password reset
     *
     * @param string $identifierType
     * @return bool
     */
    public static function isIdentifierAllowed(string $identifierType): bool
    {
        $allowed = self::getAllowedIdentifiers();
        return in_array($identifierType, $allowed);
    }

    /**
     * Validate password meets requirements
     *
     * @param string $password
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];
        $minLength = self::getMinPasswordLength();

        // Check minimum length
        if (strlen($password) < $minLength) {
            $errors[] = sprintf(
                __('Password must be at least %d characters long', 'wp-sms'),
                $minLength
            );
        }

        // Optional: Add more password strength requirements
        // Uncomment as needed:
        
        // if (!preg_match('/[A-Z]/', $password)) {
        //     $errors[] = __('Password must contain at least one uppercase letter', 'wp-sms');
        // }
        
        // if (!preg_match('/[a-z]/', $password)) {
        //     $errors[] = __('Password must contain at least one lowercase letter', 'wp-sms');
        // }
        
        // if (!preg_match('/[0-9]/', $password)) {
        //     $errors[] = __('Password must contain at least one number', 'wp-sms');
        // }
        
        // if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        //     $errors[] = __('Password must contain at least one special character', 'wp-sms');
        // }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Find user by identifier
     *
     * @param string $identifier
     * @return WP_User|null
     */
    public static function findUserByIdentifier(string $identifier): ?WP_User
    {
        $identifierType = UserHelper::getIdentifierType($identifier);
        
        if ($identifierType === 'unknown') {
            return null;
        }

        // Check if this identifier type is allowed
        if (!self::isIdentifierAllowed($identifierType)) {
            return null;
        }

        // If verification is required, only allow verified identifiers
        if (self::requiresVerification()) {
            $users = UserHelper::findUsersByVerifiedIdentifier($identifier, $identifierType);
            return !empty($users) ? $users[0] : null;
        }

        // Otherwise, use standard WordPress lookup
        if ($identifierType === 'email') {
            $user = get_user_by('email', $identifier);
            return $user ? $user : null;
        }

        // For phone, look up in user meta or IdentifierModel
        $users = UserHelper::findUsersByVerifiedIdentifier($identifier, 'phone');
        return !empty($users) ? $users[0] : null;
    }

    /**
     * Check if user has any recovery method available
     *
     * @param int $userId
     * @return bool
     */
    public static function hasRecoveryMethod(int $userId): bool
    {
        $allowedIdentifiers = self::getAllowedIdentifiers();
        
        if (empty($allowedIdentifiers)) {
            return false;
        }

        $verifiedIdentifiers = UserHelper::getVerifiedIdentifiers($userId);
        
        foreach ($allowedIdentifiers as $type) {
            if (isset($verifiedIdentifiers[$type]) && !empty($verifiedIdentifiers[$type])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available recovery methods for a user
     *
     * @param int $userId
     * @return array
     */
    public static function getAvailableRecoveryMethods(int $userId): array
    {
        $allowedIdentifiers = self::getAllowedIdentifiers();
        $verifiedIdentifiers = UserHelper::getVerifiedIdentifiers($userId);
        $methods = [];

        foreach ($allowedIdentifiers as $type) {
            if (isset($verifiedIdentifiers[$type]) && !empty($verifiedIdentifiers[$type])) {
                $methods[] = [
                    'type' => $type,
                    'identifier' => $verifiedIdentifiers[$type],
                    'masked' => self::maskIdentifier($verifiedIdentifiers[$type], $type),
                ];
            }
        }

        return $methods;
    }

    /**
     * Mask identifier for display
     *
     * @param string $identifier
     * @param string $type
     * @return string
     */
    private static function maskIdentifier(string $identifier, string $type): string
    {
        if ($type === 'email') {
            $parts = explode('@', $identifier);
            if (count($parts) !== 2) return $identifier;
            
            $username = $parts[0];
            $domain = $parts[1];
            $maskedUsername = strlen($username) <= 2 
                ? $username 
                : substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
            
            return $maskedUsername . '@' . $domain;
        }
        
        if ($type === 'phone') {
            $len = strlen($identifier);
            if ($len <= 4) return str_repeat('*', $len);
            
            return substr($identifier, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($identifier, -3);
        }
        
        return $identifier;
    }

    /**
     * Get password reset configuration summary
     *
     * @return array
     */
    public static function getConfiguration(): array
    {
        return [
            'enabled'                => self::isPasswordResetEnabled(),
            'available'              => self::isPasswordResetAvailable(),
            'token_expiry_minutes'   => self::getTokenExpiry(),
            'auto_login'             => self::isAutoLoginEnabled(),
            'allowed_identifiers'    => self::getAllowedIdentifiers(),
            'require_verification'   => self::requiresVerification(),
            'min_password_length'    => self::getMinPasswordLength(),
        ];
    }

    /**
     * Check configuration health and return warnings
     *
     * @return array Array of warning messages
     */
    public static function getConfigurationWarnings(): array
    {
        $warnings = [];

        if (!self::isPasswordResetEnabled()) {
            return $warnings;
        }

        // Check if no recovery method is available
        if (empty(self::getAllowedIdentifiers())) {
            $warnings[] = __('Password reset is enabled but no recovery identifiers are configured. Users will not be able to reset their passwords.', 'wp-sms');
        }

        // Check if passwords are actually being used
        $channels = ChannelSettingsHelper::getChannelsData();
        $passwordEnabled = false;
        
        foreach ($channels as $channel => $data) {
            if (isset($data['auth_methods']) && in_array('password', $data['auth_methods'])) {
                $passwordEnabled = true;
                break;
            }
        }

        if (!$passwordEnabled) {
            $warnings[] = __('Password reset is enabled but password authentication is not configured in any channel. This feature will not be accessible.', 'wp-sms');
        }

        return $warnings;
    }
}

