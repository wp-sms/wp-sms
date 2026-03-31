<?php

namespace WSms\Auth;

use WSms\Enums\ChannelUsage;
use WSms\Enums\EnrollmentTiming;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\MfaManager;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class PolicyEngine
{
    public function __construct(
        private MfaManager $mfaManager,
        private SettingsRepository $settingsRepo,
        private ?ProfileFieldRegistry $fieldRegistry = null,
    ) {
    }

    /**
     * Check whether MFA is required for a given user.
     */
    public function isMfaRequired(int $userId): bool
    {
        if (empty($this->getAvailableMfaFactors())) {
            return false;
        }

        // Voluntary enrollment: if user has explicitly enrolled, always require MFA.
        if ((bool) get_user_meta($userId, UserMeta::MFA_ENABLED, true)) {
            return true;
        }

        $context = $this->resolveEnrollmentContext($userId);
        if (!$context) {
            return false;
        }

        if ($context['timing'] === EnrollmentTiming::Voluntary) {
            return false;
        }

        if ($context['timing'] === EnrollmentTiming::GracePeriod && time() < $context['grace_expiry']) {
            return false;
        }

        // OnRegistration or past grace period — require MFA.
        return true;
    }

    /**
     * Get grace period info for a user, or null if not applicable.
     *
     * Returns remaining days and expiry when the user is within a grace period window.
     */
    public function getGracePeriodInfo(int $userId): ?array
    {
        // Cheap check first: enrolled users don't need grace info.
        if ((bool) get_user_meta($userId, UserMeta::MFA_ENABLED, true)) {
            return null;
        }

        if (empty($this->getAvailableMfaFactors())) {
            return null;
        }

        $context = $this->resolveEnrollmentContext($userId);
        if (!$context || $context['timing'] !== EnrollmentTiming::GracePeriod) {
            return null;
        }

        $remaining = $context['grace_expiry'] - time();
        if ($remaining <= 0) {
            return null;
        }

        return [
            'grace_period_remaining_days' => (int) ceil($remaining / DAY_IN_SECONDS),
            'grace_period_expires_at'     => gmdate('c', $context['grace_expiry']),
        ];
    }

    /**
     * Resolve enrollment timing context for a user.
     *
     * Returns null if the user doesn't match any forced-enrollment policy
     * (no required roles, role mismatch, or user not found).
     *
     * @return array{timing: EnrollmentTiming, grace_expiry: int}|null
     */
    private function resolveEnrollmentContext(int $userId): ?array
    {
        $settings = $this->settingsRepo->all();

        $requiredRoles = $settings['mfa_required_roles'];
        if (empty($requiredRoles)) {
            return null;
        }

        $user = get_userdata($userId);
        if (!$user || empty(array_intersect($user->roles, $requiredRoles))) {
            return null;
        }

        $timing = EnrollmentTiming::tryFrom($settings['enrollment_timing'])
            ?? EnrollmentTiming::Voluntary;

        $graceExpiry = 0;
        if ($timing === EnrollmentTiming::GracePeriod) {
            $graceDays = (int) $settings['grace_period_days'];
            $policyActivatedAt = (int) ($settings['mfa_policy_activated_at'] ?? 0) ?: time();
            $graceStart = max(strtotime($user->user_registered), $policyActivatedAt);
            $graceExpiry = $graceStart + ($graceDays * DAY_IN_SECONDS);
        }

        return ['timing' => $timing, 'grace_expiry' => $graceExpiry];
    }

    /**
     * Get the WordPress roles that require MFA.
     *
     * @return string[]
     */
    public function getRequiredRoles(): array
    {
        $settings = $this->settingsRepo->all();

        return $settings['mfa_required_roles'];
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
        $settings = $this->settingsRepo->all();
        $methods = [];

        $password = $settings['password'];
        if (!empty($password['enabled']) && $password['allow_sign_in']) {
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
                && ($channelSettings['usage'] ?? '') === ChannelUsage::Login->value
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
        $settings = $this->settingsRepo->all();
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
            if (isset($channelSettings['usage']) && $channelSettings['usage'] !== ChannelUsage::Mfa->value) {
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
        $settings = $this->settingsRepo->all();
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
        $settings = $this->settingsRepo->all();
        $state = UserInfo::getUserVerificationState($userId);
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
     * Channel fields (email, password, phone) are included only when their channel
     * is enabled and has required_at_signup. Other fields pass through.
     *
     * @return string[]
     */
    public function getEffectiveRegistrationFields(): array
    {
        if ($this->fieldRegistry) {
            return array_map(fn($d) => $d->id, $this->getGatedRegistrationDefs());
        }

        // Legacy path (no registry injected).
        $settings = $this->settingsRepo->all();
        $regFields = $settings['registration_fields'];
        $effectiveFields = [];

        if ($this->isChannelRequiredAtSignup('email', $settings)) {
            $effectiveFields[] = 'email';
        }

        if ($this->isChannelRequiredAtSignup('password', $settings)) {
            $effectiveFields[] = 'password';
        }

        foreach ($regFields as $f) {
            if ($f === 'phone' && !$this->isChannelRequiredAtSignup('phone', $settings)) {
                continue;
            }
            if (!in_array($f, ['email', 'password'], true) && !in_array($f, $effectiveFields, true)) {
                $effectiveFields[] = $f;
            }
        }

        return $effectiveFields;
    }

    /**
     * Get full field definitions for registration context (for frontend).
     *
     * @return array[]
     */
    public function getRegistrationFieldDefinitions(): array
    {
        if (!$this->fieldRegistry) {
            return [];
        }

        return array_map(fn($d) => $d->toArray(), $this->getGatedRegistrationDefs());
    }

    private function getGatedRegistrationDefs(): array
    {
        $settings = $this->settingsRepo->all();
        $defs = $this->fieldRegistry->getFieldsForContext('registration');

        return array_values(array_filter($defs, function ($def) use ($settings) {
            if (in_array($def->id, ['email', 'password', 'phone'], true)) {
                return $this->isChannelRequiredAtSignup($def->id, $settings);
            }

            return true;
        }));
    }

    private function isChannelRequiredAtSignup(string $channel, array $settings): bool
    {
        return !empty($settings[$channel]['enabled']) && !empty($settings[$channel]['required_at_signup']);
    }

    /**
     * Get full field definitions for profile context (for frontend).
     *
     * @return array[]
     */
    public function getProfileFieldDefinitions(): array
    {
        if (!$this->fieldRegistry) {
            return [];
        }

        $defs = $this->fieldRegistry->getFieldsForContext('profile');

        return array_map(fn($d) => $d->toArray(), $defs);
    }

    /**
     * Get channel keys that support user verification (verify_at_signup/verify_at_login).
     *
     * @return string[]
     */
    public function getVerificationChannelKeys(): array
    {
        $settings = $this->settingsRepo->all();
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
        $settings = $this->settingsRepo->all();
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
        return $this->settingsRepo->get($key, $default);
    }

    public function getPrivacyUrl(): string
    {
        return $this->settingsRepo->getPrivacyUrl();
    }

    /**
     * Resolve effective settings for a registration form.
     *
     * Starts from global settings and applies the form's auth_overrides using
     * a restrict-only model: forms can only disable globally-enabled flags,
     * never enable globally-disabled ones.
     */
    public function resolveFormConfig(RegistrationForm $form): array
    {
        return RegistrationForm::applyOverrides(
            $this->settingsRepo->all(),
            $form->getAuthOverrides(),
        );
    }

    /**
     * Get full field definitions for a registration form (for frontend).
     *
     * Cross-references the form's field list with ProfileFieldRegistry,
     * applies per-form required overrides, and sorts by form sort_order.
     *
     * @return array[]
     */
    public function getFormRegistrationFieldDefinitions(RegistrationForm $form): array
    {
        if (!$this->fieldRegistry) {
            return [];
        }

        $formFields = $form->getFields();

        $allDefs = $this->fieldRegistry->getAllFields();
        $defMap = [];
        foreach ($allDefs as $def) {
            $defMap[$def->id] = $def;
        }

        $result = [];

        foreach ($formFields as $ff) {
            $fieldId = $ff['id'];
            if (!isset($defMap[$fieldId])) {
                continue;
            }

            $def = $defMap[$fieldId];
            $arr = $def->toArray();
            $arr['required'] = !empty($ff['required']);
            $arr['sort_order'] = $ff['sort_order'] ?? 0;
            $result[] = $arr;
        }

        usort($result, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return $result;
    }

    /**
     * Get verification requirement flags for a registration form.
     *
     * @return array<string, bool>
     */
    public function getFormVerificationRequirements(RegistrationForm $form): array
    {
        $resolvedSettings = $this->resolveFormConfig($form);
        $requirements = [];

        foreach ($this->getVerificationChannelKeys() as $channelKey) {
            // Channel must be enabled globally; form can only restrict.
            $requirements['require_' . $channelKey . '_verification'] =
                !empty($resolvedSettings[$channelKey]['verify_at_signup']);
        }

        return $requirements;
    }

    /**
     * Get simple list of field IDs for a registration form.
     *
     * @return string[]
     */
    public function getFormEffectiveRegistrationFields(RegistrationForm $form): array
    {
        if (!$this->fieldRegistry) {
            return $form->getFieldIds();
        }

        $allDefs = $this->fieldRegistry->getAllFields();
        $validIds = array_map(fn($d) => $d->id, $allDefs);

        return array_values(array_filter(
            $form->getFieldIds(),
            fn(string $id) => in_array($id, $validIds, true),
        ));
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
        $settings = $this->settingsRepo->all();
        $requirements = [];

        foreach ($this->getVerificationChannelKeys() as $channelKey) {
            $requirements['require_' . $channelKey . '_verification'] =
                !empty($settings[$channelKey]['verify_at_signup']);
        }

        return $requirements;
    }

}
