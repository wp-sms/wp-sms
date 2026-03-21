<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

class SettingsRepository
{
    private ?array $settings = null;

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
            'delivery_channel'     => 'sms',
            'allow_sign_in'        => true,
            'reverify_on_change'   => false,
            'otp_gateway'          => null,
        ],
        'email' => [
            'enabled'              => true,
            'usage'                => 'login',
            'verification_methods' => ['otp'],
            'allow_sign_in'        => true,
            'required_at_signup'   => true,
            'reverify_on_change'   => false,
            'otp_gateway'          => null,
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
            'enabled'        => false,
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
            'verification_enabled'  => true,
            'notifications_enabled' => true,
        ],
        'trusted_devices' => [
            'enabled' => false,
            'ttl'     => 2592000, // 30 days
        ],
        'branding' => [
            'logo_url'                => '',
            'site_name'               => '',
            'logo_position'           => 'center',
            'logo_size'               => 40,
            'primary_color'           => '#171717',
            'accent_color'            => '#6366f1',
            'text_color'              => '#1c1917',
            'error_color'             => '#dc2626',
            'background_color'        => '#ffffff',
            'background_image_url'    => '',
            'color_mode'              => 'light',
            'button_style'            => 'filled',
            'font_family'             => 'system-ui',
            'google_font'             => false,
            'layout'                  => 'centered',
            'border_radius'           => 8,
            'social_position'         => 'top',
            'split_panel_position'    => 'left',
            'split_panel_bg_color'    => '#171717',
            'split_panel_bg_image_url' => '',
            'split_welcome_heading'   => 'Welcome back',
            'split_subtitle'          => 'Sign in to continue',
        ],
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

        $raw = get_option('wsms_auth_settings', []);

        foreach (self::CHANNEL_DEFAULTS as $key => $defaults) {
            $raw[$key] = array_merge($defaults, $raw[$key] ?? []);
        }

        // Migrate old registration_fields to profile_fields if needed.
        if (empty($raw['profile_fields']) && !empty($raw['registration_fields'])) {
            $raw['profile_fields'] = $this->migrateRegistrationFields($raw['registration_fields']);
        }

        // Migrate dark_mode → color_mode in branding.
        if (isset($raw['branding']['dark_mode']) && !isset($raw['branding']['color_mode'])) {
            $raw['branding']['color_mode'] = $raw['branding']['dark_mode'] ? 'dark' : 'light';
            unset($raw['branding']['dark_mode']);
        }

        return $this->settings = $raw;
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
