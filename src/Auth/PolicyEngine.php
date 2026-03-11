<?php

namespace WSms\Auth;

use WSms\Enums\EnrollmentTiming;

defined('ABSPATH') || exit;

class PolicyEngine
{
    private ?array $settings = null;

    /**
     * Channel ID to conflicting channel ID mapping.
     *
     * When a primary method uses a channel, the same channel cannot be an MFA factor.
     */
    private const CONFLICT_MAP = [
        'phone_otp'  => 'sms',
        'email_otp'  => 'email_otp',
        'magic_link' => 'email_otp',
    ];

    /**
     * Check whether MFA is required for a given user.
     */
    public function isMfaRequired(int $userId): bool
    {
        $settings = $this->getSettings();

        $enabledFactors = $settings['mfa_factors'] ?? [];
        if (empty($enabledFactors)) {
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
     * Get the enabled primary authentication methods.
     *
     * @return string[]
     */
    public function getAvailablePrimaryMethods(): array
    {
        $settings = $this->getSettings();

        return $settings['primary_methods'] ?? ['password'];
    }

    /**
     * Get the enabled MFA factors.
     *
     * @return string[]
     */
    public function getAvailableMfaFactors(): array
    {
        $settings = $this->getSettings();

        return $settings['mfa_factors'] ?? [];
    }

    /**
     * Validate that a primary method and MFA factor don't conflict.
     *
     * The same channel cannot serve as both primary auth and MFA factor.
     *
     * @return bool True if the combination is valid (no conflict).
     */
    public function validatePolicyConflicts(string $primaryMethod, string $mfaFactor): bool
    {
        $blockedFactor = self::CONFLICT_MAP[$primaryMethod] ?? null;

        if ($blockedFactor === null) {
            return true;
        }

        return $mfaFactor !== $blockedFactor;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        return $this->settings ??= get_option('wsms_auth_settings', []);
    }
}
