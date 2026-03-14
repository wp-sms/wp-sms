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
            'enabled'        => false,
            'code_length'    => 6,
            'expiry'         => 300,
            'max_attempts'   => 3,
            'cooldown'       => 60,
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
}
