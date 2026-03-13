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

        // Password — allow_sign_in defaults to true (matches frontend defaults).
        $password = $settings['password'] ?? [];
        if (!empty($password['enabled']) && ($password['allow_sign_in'] ?? true)) {
            $methods[] = 'password';
        }

        // Phone channel.
        $phone = $settings['phone'] ?? [];
        if (!empty($phone['enabled']) && ($phone['usage'] ?? '') === 'login' && ($phone['allow_sign_in'] ?? true)) {
            $methods[] = 'phone';
        }

        // Email channel.
        $email = $settings['email'] ?? [];
        if (!empty($email['enabled']) && ($email['usage'] ?? '') === 'login' && ($email['allow_sign_in'] ?? true)) {
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
     * Get available authentication methods for a specific user.
     *
     * Uses getAvailablePrimaryMethods() as the source of truth for globally
     * enabled methods, then cross-references with the user's data.
     *
     * @return array<int, array{method: string, type: string, channel: string}>
     */
    public function getAvailableMethodsForUser(int $userId): array
    {
        $globalMethods = $this->getAvailablePrimaryMethods();
        $settings = $this->getSettings();
        $user = get_userdata($userId);

        if (!$user) {
            return [];
        }

        $methods = [];
        $userPhone = get_user_meta($userId, 'wsms_phone', true);

        if (in_array('password', $globalMethods, true)) {
            $methods[] = ['method' => 'password', 'type' => 'password', 'channel' => 'password'];
        }

        if (in_array('phone', $globalMethods, true) && !empty($userPhone)) {
            $verificationMethods = ($settings['phone'] ?? [])['verification_methods'] ?? ['otp'];
            if (in_array('otp', $verificationMethods, true)) {
                $methods[] = ['method' => 'phone_otp', 'type' => 'otp', 'channel' => 'phone'];
            }
            if (in_array('magic_link', $verificationMethods, true)) {
                $methods[] = ['method' => 'phone_magic_link', 'type' => 'magic_link', 'channel' => 'phone'];
            }
        }

        if (in_array('email', $globalMethods, true) && !empty($user->user_email) && !AccountManager::isPlaceholderEmail($user->user_email)) {
            $verificationMethods = ($settings['email'] ?? [])['verification_methods'] ?? ['otp'];
            if (in_array('otp', $verificationMethods, true)) {
                $methods[] = ['method' => 'email_otp', 'type' => 'otp', 'channel' => 'email'];
            }
            if (in_array('magic_link', $verificationMethods, true)) {
                $methods[] = ['method' => 'email_magic_link', 'type' => 'magic_link', 'channel' => 'email'];
            }
        }

        return $methods;
    }

    /**
     * Determine the smart default method based on how the user identified themselves.
     */
    public function getDefaultMethod(string $identifierType, array $availableMethods): ?string
    {
        if (empty($availableMethods)) {
            return null;
        }

        $methodNames = array_column($availableMethods, 'method');

        // Phone identifier → prefer phone_otp.
        if ($identifierType === 'phone') {
            if (in_array('phone_otp', $methodNames, true)) {
                return 'phone_otp';
            }
            if (in_array('phone_magic_link', $methodNames, true)) {
                return 'phone_magic_link';
            }
        }

        // Email identifier → prefer password.
        if ($identifierType === 'email') {
            if (in_array('password', $methodNames, true)) {
                return 'password';
            }
            if (in_array('email_otp', $methodNames, true)) {
                return 'email_otp';
            }
        }

        // Username → prefer password.
        if ($identifierType === 'username') {
            if (in_array('password', $methodNames, true)) {
                return 'password';
            }
        }

        // Fallback to first available.
        return $methodNames[0] ?? null;
    }

    /**
     * Pick the best MFA factor from a different channel than primary auth.
     */
    public function getSmartMfaDefault(string $primaryMethod, array $availableFactors): ?string
    {
        if (empty($availableFactors)) {
            return null;
        }

        $factorIds = array_column($availableFactors, 'channel_id');

        // Determine the primary channel.
        $primaryChannel = match (true) {
            str_starts_with($primaryMethod, 'phone') => 'phone',
            str_starts_with($primaryMethod, 'email') => 'email',
            $primaryMethod === 'password'             => 'password',
            default                                   => null,
        };

        // Prefer a factor from a different channel.
        foreach ($factorIds as $factorId) {
            if ($factorId !== $primaryChannel) {
                return $factorId;
            }
        }

        // Fallback to first available.
        return $factorIds[0] ?? null;
    }

    /**
     * Check admin settings + user meta to determine pending verifications.
     *
     * @return array<int, array{type: string, status: string}>
     */
    public function getPendingVerifications(int $userId): array
    {
        $settings = $this->getSettings();
        $pending = [];

        if (!empty($settings['email']['verify_at_signup'])) {
            $userEmail = get_userdata($userId)?->user_email ?? '';
            $hasEmail = !empty($userEmail) && !AccountManager::isPlaceholderEmail($userEmail);
            $emailVerified = (bool) get_user_meta($userId, 'wsms_email_verified', true);
            if ($hasEmail && !$emailVerified) {
                $pending[] = ['type' => 'email', 'status' => 'pending'];
            }
        }

        if (!empty($settings['phone']['verify_at_signup'])) {
            $hasPhone = !empty(get_user_meta($userId, 'wsms_phone', true));
            $phoneVerified = (bool) get_user_meta($userId, 'wsms_phone_verified', true);
            if ($hasPhone && !$phoneVerified) {
                $pending[] = ['type' => 'phone', 'status' => 'pending'];
            }
        }

        return $pending;
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
     * Compute effective registration fields from channel settings.
     *
     * Email and password are included only when their channel has required_at_signup.
     * Other fields from registration_fields (phone, first_name, last_name, etc.) pass through.
     *
     * @return string[]
     */
    public function getEffectiveRegistrationFields(): array
    {
        $settings = $this->getSettings();
        $regFields = $settings['registration_fields'] ?? ['email', 'password'];
        $effectiveFields = [];

        if (!empty($settings['email']['required_at_signup'])) {
            $effectiveFields[] = 'email';
        }

        if (!empty($settings['password']['required_at_signup'])) {
            $effectiveFields[] = 'password';
        }

        foreach ($regFields as $f) {
            if (!in_array($f, ['email', 'password'], true) && !in_array($f, $effectiveFields, true)) {
                $effectiveFields[] = $f;
            }
        }

        return $effectiveFields;
    }

    /**
     * Backend defaults matching the frontend constants (resources/react/src/lib/constants.ts).
     * Applied so that settings missing from the DB still behave as the admin UI shows.
     */
    private const CHANNEL_DEFAULTS = [
        'password' => [
            'enabled'            => true,
            'required_at_signup' => true,
            'allow_sign_in'      => true,
        ],
        'phone' => [
            'enabled'              => false,
            'usage'                => 'login',
            'verification_methods' => ['otp'],
            'allow_sign_in'        => true,
        ],
        'email' => [
            'enabled'              => true,
            'usage'                => 'login',
            'verification_methods' => ['otp'],
            'allow_sign_in'        => true,
            'required_at_signup'   => true,
        ],
        'backup_codes' => [
            'enabled' => false,
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $raw = get_option('wsms_auth_settings', []);

        // Deep-merge channel defaults so missing keys fall back to sane values.
        foreach (self::CHANNEL_DEFAULTS as $key => $defaults) {
            $raw[$key] = array_merge($defaults, $raw[$key] ?? []);
        }

        return $this->settings = $raw;
    }
}
