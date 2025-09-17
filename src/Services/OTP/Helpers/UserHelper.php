<?php

namespace WP_SMS\Services\OTP\Helpers;

/**
 * User Helper
 *
 * Manages pending users with identifiers for OTP registration flow.
 */
class UserHelper
{
    /**
     * Create a pending user with identifier
     */
    public static function createPendingUser(string $identifier, array $customMeta = [])
    {
        // Validate identifier
        if (empty($identifier)) {
            return false;
        }

        // Check if user already exists with this identifier
        $existingUser = self::getUserByIdentifier($identifier);
        if ($existingUser) {
            return $existingUser;
        }

        // Generate unique username
        $username = UsernameHelper::generate();
        
        // Determine user email based on identifier type
        $userEmail = self::getUserEmailFromIdentifier($identifier);
        
        // Default meta data
        $defaultMeta = [
            'pending_user' => true,
            'identifier' => $identifier,
            'identifier_type' => self::getIdentifierType($identifier),
            'created_at' => current_time('mysql'),
        ];

        // Merge custom meta with default meta (custom meta takes precedence)
        $metaInput = array_merge($defaultMeta, $customMeta);

         // Add wpsms_ prefix to meta keys
         $prefixedMeta = [];
         foreach ($metaInput as $key => $value) {
             $prefixedMeta['wpsms_' . $key] = $value;
         }
         $metaInput = $prefixedMeta;

        // Create user data
        $userData = [
            'user_login' => $username,
            'user_email' => $userEmail,
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber', // Default role for pending users
            'meta_input' => $metaInput
        ];

        // Create the user
        $userId = wp_insert_user($userData);
        
        if (is_wp_error($userId)) {
            return false;
        }

        // Get the created user
        $user = get_user_by('id', $userId);
        
        // Apply filters for customization
        do_action('wpsms_pending_user_created', $user, $identifier, $customMeta);
        
        return $user;
    }

    /**
     * Get user by identifier
     */
    public static function getUserByIdentifier(string $identifier)
    {
        $users = get_users([
            'meta_key' => 'wpsms_identifier',
            'meta_value' => $identifier,
            'number' => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Get user by flow_id
     */
    public static function getUserByFlowId(string $flowId): ?\WP_User
    {
        $users = get_users([
            'meta_key' => 'flow_id',
            'meta_value' => $flowId,
            'number' => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Update user identifier
     */
    public static function updateUserIdentifier(int $userId, string $newIdentifier)
    {
        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        // Update the identifier meta
        $updated = update_user_meta($userId, 'wpsms_identifier', $newIdentifier);
        
        if ($updated) {
            // Update identifier type
            update_user_meta($userId, 'wpsms_identifier_type', self::getIdentifierType($newIdentifier));
            
            // Apply filters
            do_action('wpsms_user_identifier_updated', $user, $newIdentifier);
        }

        return $updated !== false;
    }

    /**
     * Update custom user meta
     */
    public static function updateUserMeta(int $userId, array $metaData): bool
    {
        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        $success = true;
        foreach ($metaData as $key => $value) {
            $result = update_user_meta($userId, $key, $value);
            if ($result === false) {
                $success = false;
            }
        }

        if ($success) {
            // Apply filters
            do_action('wpsms_user_meta_updated', $user, $metaData);
        }

        return $success;
    }

    /**
     * Activate pending user (remove pending status)
     */
    public static function activateUser(int $userId)
    {
        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        // Remove pending status
        $updated = delete_user_meta($userId, 'wpsms_pending_user');
        
        if ($updated) {
            // Add activation timestamp
            update_user_meta($userId, 'wpsms_activated_at', current_time('mysql'));
            
            // Apply filters
            do_action('wpsms_user_activated', $user);
        }

        return $updated !== false;
    }

    /**
     * Delete pending user
     */
    public static function deletePendingUser(int $userId): bool
    {
        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        // Check if it's a pending user
        if (!get_user_meta($userId, 'wpsms_pending_user', true)) {
            return false;
        }

        // Apply filters before deletion
        do_action('wpsms_pending_user_deleted', $user);

        // Delete the user
        return wp_delete_user($userId);
    }

    /**
     * Check if user is pending
     */
    public static function isPendingUser(int $userId): bool
    {
        return (bool) get_user_meta($userId, 'wpsms_pending_user', true);
    }

    /**
     * Get all pending users
     */
    public static function getPendingUsers(array $args = []): array
    {
        $defaultArgs = [
            'meta_key' => 'wpsms_pending_user',
            'meta_value' => true,
            'number' => 20,
        ];

        $args = wp_parse_args($args, $defaultArgs);
        
        return get_users($args);
    }

    /**
     * Clean up expired pending users
     */
    public static function cleanupExpiredPendingUsers(int $expirationHours = 24): int
    {
        $expirationTime = date('Y-m-d H:i:s', strtotime("-{$expirationHours} hours"));
        
        $expiredUsers = get_users([
            'meta_key' => 'wpsms_pending_user',
            'meta_value' => true,
            'date_query' => [
                [
                    'before' => $expirationTime,
                    'inclusive' => true,
                ]
            ],
            'number' => -1, // Get all
        ]);

        $deletedCount = 0;
        foreach ($expiredUsers as $user) {
            if (self::deletePendingUser($user->ID)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Get user email from identifier
     */
    private static function getUserEmailFromIdentifier(string $identifier): string
    {
        // If it's an email, use it directly
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $identifier;
        }

        // For phone numbers, create a unique placeholder email
        $uniquePart = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        return 'pending-' . $uniquePart . '@wpsms.local';
    }

    /**
     * Get identifier type (email or phone)
     */
    private static function getIdentifierType(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        
        // Check if it's a valid phone number
        $cleanNumber = preg_replace('/[^\d+]/', '', $identifier);
        if (preg_match('/^(\+\d{7,15}|\d{7,15})$/', $cleanNumber)) {
            return 'phone';
        }
        
        return 'unknown';
    }
}
