<?php

namespace WSms\Auth;

use WSms\Enums\EnrollmentTiming;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\MfaManager;

defined('ABSPATH') || exit;

class PolicyEngine
{
    private ?array $settings = null;

    public function __construct(
        private MfaManager $mfaManager,
    ) {
    }

    /**
     * Check whether MFA is required for a given user.
     */
    public function isMfaRequired(int $userId): bool
    {
        $settings = $this->getSettings();

        $hasMfaFactors = !empty($this->getAvailableMfaFactors());

        if (!$hasMfaFactors) {
            return false;
        }

        // Voluntary enrollment: if user has explicitly enrolled, always require MFA.
        $userEnrolled = (bool) get_user_meta($userId, 'wsms_mfa_enabled', true);

        if ($userEnrolled) {
            return true;
        }

        // Below here: forced enrollment via admin policy.
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
            // Already handled above — user enrolled voluntarily.
            return false;
        }

        if ($timing === EnrollmentTiming::GracePeriod) {
            $graceDays = (int) ($settings['grace_period_days'] ?? 7);
            $registered = strtotime($user->user_registered);
            $graceExpiry = $registered + ($graceDays * DAY_IN_SECONDS);

            if (time() < $graceExpiry) {
                return false;
            }
        }

        // OnRegistration or past grace period — require MFA.
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

        // Dynamically iterate registered channels that support primary auth.
        foreach ($this->mfaManager->getAvailableChannels() as $channel) {
            if (!$channel->supportsPrimaryAuth()) {
                continue;
            }

            $channelKey = $channel->getId();
            $channelSettings = $settings[$channelKey] ?? [];

            if (
                !empty($channelSettings['enabled'])
                && ($channelSettings['usage'] ?? '') === 'login'
                && ($channelSettings['allow_sign_in'] ?? true)
            ) {
                $methods[] = $channelKey;
            }
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

        foreach ($this->mfaManager->getAvailableChannels() as $channel) {
            if (!$channel->supportsMfa()) {
                continue;
            }

            $channelKey = $channel->getId();
            $channelSettings = $settings[$channelKey] ?? [];

            if (empty($channelSettings[$channel->getEnabledSettingKey()])) {
                continue;
            }

            // Channels with a 'usage' toggle (phone, email) must be set to 'mfa'.
            // Channels without one (backup_codes, totp, telegram) are MFA by nature.
            if (isset($channelSettings['usage']) && $channelSettings['usage'] !== 'mfa') {
                continue;
            }

            $factors[] = $channelKey;
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

        if (in_array('password', $globalMethods, true)) {
            $methods[] = ['method' => 'password', 'type' => 'password', 'channel' => 'password'];
        }

        // Dynamically build methods for each channel.
        foreach ($this->mfaManager->getAvailableChannels() as $channel) {
            $channelKey = $channel->getId();

            if (!in_array($channelKey, $globalMethods, true)) {
                continue;
            }

            if (!$channel->isAvailableForUser($userId)) {
                continue;
            }

            $verificationMethods = ($settings[$channelKey] ?? [])['verification_methods'] ?? ['otp'];

            if (in_array('otp', $verificationMethods, true)) {
                $methods[] = ['method' => $channelKey . '_otp', 'type' => 'otp', 'channel' => $channelKey];
            }
            if (in_array('magic_link', $verificationMethods, true)) {
                $methods[] = ['method' => $channelKey . '_magic_link', 'type' => 'magic_link', 'channel' => $channelKey];
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

        // Derive the primary channel from method name (e.g., 'phone_otp' → 'phone').
        $primaryChannel = $primaryMethod === 'password' ? 'password' : null;

        if ($primaryChannel === null) {
            foreach ($this->mfaManager->getAvailableChannels() as $channel) {
                if (str_starts_with($primaryMethod, $channel->getId())) {
                    $primaryChannel = $channel->getId();
                    break;
                }
            }
        }

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
        $state = AccountManager::getUserVerificationState($userId);
        $pending = [];

        // Dynamically check each verification channel.
        foreach ($this->getVerificationChannelKeys() as $channelKey) {
            if (
                !empty($settings[$channelKey]['verify_at_signup'])
                && ($state[$channelKey]['has'] ?? false)
                && !($state[$channelKey]['verified'] ?? true)
            ) {
                $pending[] = ['type' => $channelKey, 'status' => 'pending'];
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

        if (!empty($settings['password']['enabled']) && !empty($settings['password']['required_at_signup'])) {
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
     * Get channel keys that support user verification (verify_at_signup/verify_at_login).
     *
     * @return string[]
     */
    public function getVerificationChannelKeys(): array
    {
        $settings = $this->getSettings();
        $keys = [];

        foreach ($this->mfaManager->getAvailableChannels() as $channel) {
            if ($channel->supportsPrimaryAuth()) {
                $channelKey = $channel->getId();
                if (!empty($settings[$channelKey]['enabled'])) {
                    $keys[] = $channelKey;
                }
            }
        }

        return $keys;
    }

    /**
     * Get channel-level method details for the /auth/config response.
     *
     * @return array<string, array{has_otp: bool, has_magic_link: bool, code_length: int}>
     */
    public function getMethodDetails(array $primaryMethods): array
    {
        $settings = $this->getSettings();
        $details = [];

        foreach ($this->mfaManager->getAvailableChannels() as $channel) {
            $channelKey = $channel->getId();

            if (!in_array($channelKey, $primaryMethods, true)) {
                continue;
            }

            $channelSettings = $settings[$channelKey] ?? [];
            $verificationMethods = $channelSettings['verification_methods'] ?? ['otp'];

            $details[$channelKey] = [
                'has_otp'        => in_array('otp', $verificationMethods, true),
                'has_magic_link' => in_array('magic_link', $verificationMethods, true),
                'code_length'    => (int) ($channelSettings['code_length'] ?? 6),
            ];
        }

        return $details;
    }

    /**
     * Get a single top-level setting value (with defaults applied).
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->getSettings()[$key] ?? $default;
    }

    /**
     * Build verification requirement flags for each channel.
     *
     * Always emits both true and false values so the API response shape is stable.
     *
     * @return array<string, bool>
     */
    public function getVerificationRequirements(): array
    {
        $settings = $this->getSettings();
        $requirements = [];

        foreach ($this->getVerificationChannelKeys() as $channelKey) {
            $requirements['require_' . $channelKey . '_verification'] =
                !empty($settings[$channelKey]['verify_at_signup']);
        }

        return $requirements;
    }

    /**
     * Backend defaults matching the frontend constants (resources/react/src/lib/constants.ts).
     * Applied so that settings missing from the DB still behave as the admin UI shows.
     */
    public const CHANNEL_DEFAULTS = [
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
        'totp' => [
            'enabled' => false,
        ],
        'captcha' => [
            'enabled'           => false,
            'provider'          => 'turnstile',
            'site_key'          => '',
            'secret_key'        => '',
            'protected_actions' => ['login', 'register', 'forgot_password'],
            'fail_open'         => false,
        ],
        'social' => [
            'google'   => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
            'telegram' => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
        ],
        'telegram' => [
            'bot_token'      => '',
            'bot_username'   => '',
            'webhook_secret' => '',
            'mfa_enabled'    => false,
            'code_length'    => 6,
            'expiry'         => 300,
            'max_attempts'   => 3,
            'cooldown'       => 60,
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
