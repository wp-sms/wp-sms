<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

class SettingsRepository
{
    public const OPTION_KEY = 'wsms_settings';

    private ?array $settings = null;

    /**
     * Canonical defaults for all auth settings.
     *
     * Single source of truth — frontend constants.ts must match (enforced by test).
     * InstallManager seeds the DB with this on activation.
     */
    public const DEFAULTS = [
        // --- Channel sub-objects ---
        'password' => [
            'enabled'            => true,
            'required_at_signup' => true,
            'allow_sign_in'      => true,
        ],
        'phone' => [
            'enabled'              => false,
            'usage'                => 'login',
            'verification_methods' => ['otp'],
            'delivery_channel'     => 'sms',
            'required_at_signup'   => false,
            'verify_at_signup'     => false,
            'allow_sign_in'        => true,
            'code_length'          => 6,
            'expiry'               => 300,
            'max_attempts'         => 3,
            'cooldown'             => 60,
            'reverify_on_change'   => true,
            'otp_gateway'          => null,
        ],
        'email' => [
            'enabled'              => true,
            'usage'                => 'login',
            'verification_methods' => ['otp'],
            'required_at_signup'   => true,
            'verify_at_signup'     => true,
            'allow_sign_in'        => true,
            'code_length'          => 6,
            'expiry'               => 600,
            'max_attempts'         => 3,
            'cooldown'             => 60,
            'reverify_on_change'   => true,
            'otp_gateway'          => null,
        ],
        'backup_codes' => [
            'enabled' => false,
            'count'   => 10,
            'length'  => 10,
        ],
        'totp' => [
            'enabled' => false,
        ],
        'passkey' => [
            'enabled' => false,
        ],
        'captcha' => [
            'enabled'           => false,
            'provider'          => 'turnstile',
            'site_key'          => '',
            'secret_key'        => '',
            'protected_actions' => ['login', 'register', 'forgot_password', 'subscribe', 'messaging_button'],
            'fail_open'         => false,
        ],
        'social' => [
            'google'   => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
            'github'   => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
            'telegram' => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
            'line'     => ['enabled' => false, 'client_id' => '', 'client_secret' => ''],
        ],
        'telegram' => [
            'bot_token'      => '',
            'bot_username'   => '',
            'webhook_secret' => '',
            'enabled'        => false,
            'code_length'    => 6,
            'expiry'         => 300,
            'max_attempts'   => 3,
            'cooldown'       => 60,
        ],
        'line' => [
            'enabled'        => false,
            'bot_basic_id'   => '',
            'code_length'    => 6,
            'expiry'         => 300,
            'max_attempts'   => 3,
            'cooldown'       => 60,
        ],
        'woocommerce' => [
            'verify_email_at_checkout' => false,
            'verify_phone_at_checkout' => false,
            'skip_verified_users'      => true,
            'redirect_auth'            => false,
        ],
        'contact_form_7' => [
            'verification_enabled'  => false,
            'notifications_enabled' => false,
        ],
        'trusted_devices' => [
            'enabled' => false,
            'ttl'     => 2592000, // 30 days
        ],

        // --- Top-level scalars ---
        'auth_enabled'                    => false,
        'mfa_required_roles'              => [],
        'enrollment_timing'               => 'voluntary',
        'grace_period_days'               => 7,
        'auth_base_url'                   => '/account',
        'redirect_login'                  => false,
        'auto_create_users'               => false,
        'log_verbosity'                   => 'standard',
        'log_retention_days'              => 30,
        'registration_fields'             => ['email', 'password'],
        'profile_fields'                  => [],
        'pending_user_cleanup_enabled'    => true,
        'pending_user_ttl_hours'          => 24,
        'social_profile_sync'             => 'registration_only',
        'site_phone'                      => '',
        'site_phone_channel'              => 'sms',
        'terms_url'                       => '',
        'privacy_url'                     => '',
        'subscription_consent_text'       => '',
        'subscription_consent_required'   => false,
    ];

    /**
     * Get all settings with defaults applied.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $defaults = apply_filters('wsms_settings_defaults', self::DEFAULTS);
        $raw = get_option(self::OPTION_KEY, []);
        $merged = self::deepMergeDefaults($defaults, $raw);

        // Migrate old registration_fields to profile_fields if needed.
        if (empty($merged['profile_fields']) && !empty($merged['registration_fields'])) {
            $merged['profile_fields'] = $this->migrateRegistrationFields($merged['registration_fields']);
        }

        return $this->settings = $merged;
    }

    /**
     * Reset the in-memory cache so the next all() re-reads from DB.
     */
    public function invalidateCache(): void
    {
        $this->settings = null;
    }

    /**
     * Deep-merge defaults with raw DB values.
     *
     * - Associative arrays: recurse (preserves sibling keys, e.g. social.github when only social.google is in DB)
     * - Indexed arrays: replace wholesale (admin's ['login'] replaces 5-item default)
     * - Scalars/null: replace
     */
    private static function deepMergeDefaults(array $defaults, array $raw): array
    {
        $result = $defaults;

        foreach ($raw as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key]) && !array_is_list($value)) {
                $result[$key] = self::deepMergeDefaults($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get settings for a specific channel.
     *
     * @return array<string, mixed>
     */
    public function channel(string $channelId): array
    {
        return $this->all()[$channelId] ?? [];
    }

    /**
     * Get a single top-level setting value (with defaults applied).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Whether auth pages are active (enabled and not in transition mode).
     */
    public function isAuthActive(): bool
    {
        return (bool) $this->get('auth_enabled', false)
            && !get_option('wsms_transition_mode');
    }

    /**
     * Resolve the effective privacy policy URL.
     *
     * Falls back to the WordPress Privacy Policy page when the setting is empty.
     */
    public function getPrivacyUrl(): string
    {
        $url = $this->get('privacy_url', '');
        if (!$url) {
            $url = get_privacy_policy_url();
        }
        return (string) $url;
    }

    /**
     * Migrate old registration_fields (string[]) to profile_fields format.
     *
     * @param string[] $regFields
     * @return array<int, array<string, mixed>>
     */
    private function migrateRegistrationFields(array $regFields): array
    {
        $profileFields = [];
        $order = 1;

        foreach ($regFields as $fieldId) {
            $profileFields[] = [
                'id'         => $fieldId,
                'source'     => 'system',
                'visibility' => $fieldId === 'password' ? 'registration' : 'both',
                'required'   => in_array($fieldId, ['email', 'password'], true),
                'sort_order' => $order++,
            ];
        }

        return $profileFields;
    }
}
