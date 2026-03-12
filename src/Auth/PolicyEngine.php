<?php

namespace WSms\Auth;

use WSms\Enums\EnrollmentTiming;

defined('ABSPATH') || exit;

class PolicyEngine
{
    private ?array $settings = null;

    /**
     * Check whether MFA is required for a given user.
     */
    public function isMfaRequired(int $userId): bool
    {
        $settings = $this->getSettings();

        // Check if any channel is configured for MFA usage.
        $hasMfaFactors = false;

        foreach (['phone', 'email'] as $channelKey) {
            $channel = $settings[$channelKey] ?? [];
            if (!empty($channel['enabled']) && ($channel['usage'] ?? '') === 'mfa') {
                $hasMfaFactors = true;
                break;
            }
        }

        if (!$hasMfaFactors && empty($settings['backup_codes']['enabled'])) {
            return false;
        }

        $requiredRoles = $settings['mfa_required_roles'] ?? [];
        if (empty($requiredRoles)) {
            return false;
        }

        $user = get_userdata($userId);
        if (!$user) {
            return false;
        }

        $userRoles = $user->roles;
        if (empty(array_intersect($userRoles, $requiredRoles))) {
            return false;
        }

        $timing = EnrollmentTiming::tryFrom($settings['enrollment_timing'] ?? 'voluntary')
            ?? EnrollmentTiming::Voluntary;

        if ($timing === EnrollmentTiming::Voluntary) {
            $hasFactor = (bool) get_user_meta($userId, 'wsms_mfa_enabled', true);
            if (!$hasFactor) {
                return false;
            }
        }

        if ($timing === EnrollmentTiming::GracePeriod) {
            $graceDays = (int) ($settings['grace_period_days'] ?? 7);
            $registered = strtotime($user->user_registered);
            $graceExpiry = $registered + ($graceDays * DAY_IN_SECONDS);

            if (time() < $graceExpiry) {
                $hasFactor = (bool) get_user_meta($userId, 'wsms_mfa_enabled', true);
                if (!$hasFactor) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the WordPress roles that require MFA.
     *
     * @return string[]
     */
    public function getRequiredRoles(): array
    {
        $settings = $this->getSettings();

        return $settings['mfa_required_roles'] ?? [];
    }

    /**
     * Derive available primary methods from channel settings.
     *
     * Primary methods = channels where enabled && usage === 'login' && allow_sign_in,
     * plus 'password' if enabled.
     *
     * @return string[]
     */
    public function getAvailablePrimaryMethods(): array
    {
        $settings = $this->getSettings();
        $methods = [];

        // Password.
        $password = $settings['password'] ?? [];
        if (!empty($password['enabled']) && !empty($password['allow_sign_in'])) {
            $methods[] = 'password';
        }

        // Phone channel.
        $phone = $settings['phone'] ?? [];
        if (!empty($phone['enabled']) && ($phone['usage'] ?? '') === 'login' && !empty($phone['allow_sign_in'])) {
            $methods[] = 'phone';
        }

        // Email channel.
        $email = $settings['email'] ?? [];
        if (!empty($email['enabled']) && ($email['usage'] ?? '') === 'login' && !empty($email['allow_sign_in'])) {
            $methods[] = 'email';
        }

        return $methods ?: ['password'];
    }

    /**
     * Derive available MFA factors from channel settings.
     *
     * MFA factors = channels where enabled && usage === 'mfa', plus backup_codes if enabled.
     *
     * @return string[]
     */
    public function getAvailableMfaFactors(): array
    {
        $settings = $this->getSettings();
        $factors = [];

        foreach (['phone', 'email'] as $channelKey) {
            $channel = $settings[$channelKey] ?? [];
            if (!empty($channel['enabled']) && ($channel['usage'] ?? '') === 'mfa') {
                $factors[] = $channelKey;
            }
        }

        if (!empty($settings['backup_codes']['enabled'])) {
            $factors[] = 'backup_codes';
        }

        return $factors;
    }

    /**
     * Policy conflicts are eliminated by design — usage is mutually exclusive
     * per channel (login OR mfa), so no validation needed.
     *
     * @return bool Always true.
     */
    public function validatePolicyConflicts(string $primaryMethod, string $mfaFactor): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        return $this->settings ??= get_option('wsms_auth_settings', []);
    }
}
