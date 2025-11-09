<?php

namespace WP_SMS\Services\OTP\Helpers;

use WP_SMS\Option;
use WP_User;

/**
 * MFA Enforcement Helper
 *
 * Manages MFA enforcement policies for users and roles
 */
class MfaEnforcementHelper
{
    /**
     * Check if MFA enforcement is enabled
     *
     * @return bool
     */
    public static function isEnforcementEnabled(): bool
    {
        return (bool) Option::getOption('otp_mfa_enforcement_enabled', false, false);
    }

    /**
     * Check if a user is required to use MFA
     *
     * @param int|WP_User $user User ID or WP_User object
     * @return bool
     */
    public static function isUserRequired($user): bool
    {
        if (!self::isEnforcementEnabled()) {
            return false;
        }

        // Convert to user object if needed
        if (is_numeric($user)) {
            $user = get_user_by('id', $user);
        }

        if (!$user instanceof WP_User) {
            return false;
        }

        // Check if user is in grace period and can skip
        if (self::isUserInGracePeriod($user->ID) && self::canSkipDuringGracePeriod()) {
            return false;
        }

        $strategy = self::getEnforcementStrategy();

        switch ($strategy) {
            case 'all_users':
                return !self::isUserRoleExcluded($user);

            case 'specific_roles':
                return self::isUserRoleRequired($user);

            case 'specific_users':
                return self::isSpecificUserRequired($user);

            case 'roles_and_users':
                return self::isUserRoleRequired($user) || self::isSpecificUserRequired($user);

            default:
                return false;
        }
    }

    /**
     * Get enforcement strategy
     *
     * @return string
     */
    public static function getEnforcementStrategy(): string
    {
        return Option::getOption('otp_mfa_enforcement_strategy', false, 'specific_roles');
    }

    /**
     * Check if user's role is required to use MFA
     *
     * @param WP_User $user
     * @return bool
     */
    public static function isUserRoleRequired(WP_User $user): bool
    {
        $requiredRoles = self::getRequiredRoles();
        
        if (empty($requiredRoles)) {
            return false;
        }

        $userRoles = $user->roles;

        foreach ($userRoles as $role) {
            if (in_array($role, $requiredRoles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user's role is excluded from MFA enforcement
     *
     * @param WP_User $user
     * @return bool
     */
    public static function isUserRoleExcluded(WP_User $user): bool
    {
        $excludedRoles = self::getExcludedRoles();
        
        if (empty($excludedRoles)) {
            return false;
        }

        $userRoles = $user->roles;

        foreach ($userRoles as $role) {
            if (in_array($role, $excludedRoles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if specific user is required to use MFA
     *
     * @param WP_User $user
     * @return bool
     */
    public static function isSpecificUserRequired(WP_User $user): bool
    {
        $requiredUsers = self::getRequiredUsers();
        
        if (empty($requiredUsers)) {
            return false;
        }

        // Check by user ID
        if (in_array($user->ID, $requiredUsers)) {
            return true;
        }

        // Check by username
        if (in_array($user->user_login, $requiredUsers)) {
            return true;
        }

        // Check by email
        if (in_array($user->user_email, $requiredUsers)) {
            return true;
        }

        return false;
    }

    /**
     * Get list of required roles
     *
     * @return array
     */
    public static function getRequiredRoles(): array
    {
        $roles = Option::getOption('otp_mfa_enforcement_roles', false, []);
        
        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        return is_array($roles) ? $roles : [];
    }

    /**
     * Get list of excluded roles
     *
     * @return array
     */
    public static function getExcludedRoles(): array
    {
        $roles = Option::getOption('otp_mfa_enforcement_excluded_roles', false, []);
        
        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        return is_array($roles) ? $roles : [];
    }

    /**
     * Get list of required users
     *
     * @return array Array of user IDs, usernames, or emails
     */
    public static function getRequiredUsers(): array
    {
        $users = Option::getOption('otp_mfa_enforcement_users', false, '');
        
        if (empty($users)) {
            return [];
        }

        // Split by newlines and clean up
        $lines = explode("\n", $users);
        $cleanUsers = [];

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            $cleanUsers[] = $line;
        }

        return $cleanUsers;
    }

    /**
     * Get grace period in days
     *
     * @return int
     */
    public static function getGracePeriod(): int
    {
        return (int) Option::getOption('otp_mfa_enforcement_grace_period', false, 7);
    }

    /**
     * Check if user can skip MFA during grace period
     *
     * @return bool
     */
    public static function canSkipDuringGracePeriod(): bool
    {
        return (bool) Option::getOption('otp_mfa_enforcement_allow_skip', false, true);
    }

    /**
     * Get reminder frequency
     *
     * @return string
     */
    public static function getReminderFrequency(): string
    {
        return Option::getOption('otp_mfa_enforcement_reminder_frequency', false, 'daily');
    }

    /**
     * Check if user is in grace period
     *
     * @param int $userId
     * @return bool
     */
    public static function isUserInGracePeriod(int $userId): bool
    {
        $gracePeriod = self::getGracePeriod();
        
        // No grace period
        if ($gracePeriod === 0) {
            return false;
        }

        // Get enforcement start date for user
        $enforcementStartDate = get_user_meta($userId, 'otp_mfa_enforcement_start_date', true);
        
        // If not set, set it now
        if (empty($enforcementStartDate)) {
            $enforcementStartDate = current_time('timestamp');
            update_user_meta($userId, 'otp_mfa_enforcement_start_date', $enforcementStartDate);
        }

        // Calculate grace period end
        $gracePeriodEnd = $enforcementStartDate + ($gracePeriod * DAY_IN_SECONDS);
        
        return current_time('timestamp') < $gracePeriodEnd;
    }

    /**
     * Check if user should be reminded to set up MFA
     *
     * @param int $userId
     * @return bool
     */
    public static function shouldRemindUser(int $userId): bool
    {
        if (!self::isEnforcementEnabled()) {
            return false;
        }

        // Only remind if user is required and hasn't set up MFA
        $user = get_user_by('id', $userId);
        if (!$user || !self::isUserRequired($user)) {
            return false;
        }

        // Check if user has MFA set up
        if (self::hasUserSetupMfa($userId)) {
            return false;
        }

        // Check if user is in grace period
        if (!self::isUserInGracePeriod($userId)) {
            return false;
        }

        $frequency = self::getReminderFrequency();

        if ($frequency === 'never') {
            return false;
        }

        if ($frequency === 'every_login') {
            return true;
        }

        $lastReminder = get_user_meta($userId, 'otp_mfa_last_reminder', true);
        
        if (empty($lastReminder)) {
            return true;
        }

        $currentTime = current_time('timestamp');
        $timeSinceLastReminder = $currentTime - $lastReminder;

        switch ($frequency) {
            case 'daily':
                return $timeSinceLastReminder >= DAY_IN_SECONDS;
            
            case 'weekly':
                return $timeSinceLastReminder >= (7 * DAY_IN_SECONDS);
            
            default:
                return false;
        }
    }

    /**
     * Mark user as reminded
     *
     * @param int $userId
     * @return void
     */
    public static function markUserReminded(int $userId): void
    {
        update_user_meta($userId, 'otp_mfa_last_reminder', current_time('timestamp'));
    }

    /**
     * Check if user has set up MFA
     *
     * @param int $userId
     * @return bool
     */
    public static function hasUserSetupMfa(int $userId): bool
    {
        // Check if user has any MFA factors configured
        $emailMfa = get_user_meta($userId, 'otp_mfa_email_enabled', true);
        $phoneMfa = get_user_meta($userId, 'otp_mfa_phone_enabled', true);
        
        return !empty($emailMfa) || !empty($phoneMfa);
    }

    /**
     * Get enforcement summary for a user
     *
     * @param int|WP_User $user
     * @return array
     */
    public static function getUserEnforcementSummary($user): array
    {
        if (is_numeric($user)) {
            $user = get_user_by('id', $user);
        }

        if (!$user instanceof WP_User) {
            return [
                'enabled' => false,
                'required' => false,
                'error' => 'Invalid user',
            ];
        }

        $isRequired = self::isUserRequired($user);
        $hasSetup = self::hasUserSetupMfa($user->ID);
        $inGracePeriod = self::isUserInGracePeriod($user->ID);
        $canSkip = self::canSkipDuringGracePeriod();
        $gracePeriod = self::getGracePeriod();
        $shouldRemind = self::shouldRemindUser($user->ID);

        $summary = [
            'enabled' => self::isEnforcementEnabled(),
            'required' => $isRequired,
            'has_setup' => $hasSetup,
            'in_grace_period' => $inGracePeriod,
            'can_skip' => $canSkip && $inGracePeriod,
            'grace_period_days' => $gracePeriod,
            'should_remind' => $shouldRemind,
            'strategy' => self::getEnforcementStrategy(),
        ];

        // Calculate days remaining in grace period
        if ($inGracePeriod) {
            $startDate = get_user_meta($user->ID, 'otp_mfa_enforcement_start_date', true);
            $gracePeriodEnd = $startDate + ($gracePeriod * DAY_IN_SECONDS);
            $daysRemaining = ceil(($gracePeriodEnd - current_time('timestamp')) / DAY_IN_SECONDS);
            $summary['grace_period_days_remaining'] = max(0, $daysRemaining);
        }

        // Add enforcement reasons
        $reasons = [];
        if ($isRequired) {
            if (self::isUserRoleRequired($user)) {
                $reasons[] = 'role';
            }
            if (self::isSpecificUserRequired($user)) {
                $reasons[] = 'user';
            }
        }
        $summary['enforcement_reasons'] = $reasons;

        return $summary;
    }

    /**
     * Get enforcement statistics
     *
     * @return array
     */
    public static function getEnforcementStatistics(): array
    {
        if (!self::isEnforcementEnabled()) {
            return [
                'enabled' => false,
                'total_users' => 0,
                'required_users' => 0,
                'users_with_mfa' => 0,
                'users_without_mfa' => 0,
            ];
        }

        $strategy = self::getEnforcementStrategy();
        $requiredRoles = self::getRequiredRoles();
        $excludedRoles = self::getExcludedRoles();
        $requiredUsers = self::getRequiredUsers();

        // Get all users based on strategy
        $args = ['fields' => 'all'];
        
        if ($strategy === 'specific_roles' || $strategy === 'roles_and_users') {
            if (!empty($requiredRoles)) {
                $args['role__in'] = $requiredRoles;
            }
        }

        if ($strategy === 'all_users' && !empty($excludedRoles)) {
            $args['role__not_in'] = $excludedRoles;
        }

        $users = get_users($args);
        $requiredCount = 0;
        $withMfaCount = 0;
        $withoutMfaCount = 0;

        foreach ($users as $user) {
            if (self::isUserRequired($user)) {
                $requiredCount++;
                
                if (self::hasUserSetupMfa($user->ID)) {
                    $withMfaCount++;
                } else {
                    $withoutMfaCount++;
                }
            }
        }

        return [
            'enabled' => true,
            'strategy' => $strategy,
            'total_users' => count($users),
            'required_users' => $requiredCount,
            'users_with_mfa' => $withMfaCount,
            'users_without_mfa' => $withoutMfaCount,
            'compliance_percentage' => $requiredCount > 0 ? round(($withMfaCount / $requiredCount) * 100, 2) : 100,
        ];
    }
}

