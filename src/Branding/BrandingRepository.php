<?php

namespace WSms\Branding;

defined('ABSPATH') || exit;

class BrandingRepository
{
    public const OPTION_KEY = 'wsms_branding';

    private ?array $cached = null;

    public const DEFAULTS = [
        'logo_url'                 => '',
        'site_name'                => '',
        'logo_position'            => 'center',
        'logo_size'                => 40,
        'primary_color'            => '#171717',
        'accent_color'             => '#6366f1',
        'text_color'               => '#1c1917',
        'error_color'              => '#dc2626',
        'background_color'         => '#ffffff',
        'background_image_url'     => '',
        'color_mode'               => 'light',
        'button_style'             => 'filled',
        'font_family'              => 'system-ui',
        'google_font'              => false,
        'layout'                   => 'centered',
        'border_radius'            => 8,
        'social_position'          => 'top',
        'split_panel_position'     => 'left',
        'split_panel_bg_color'     => '#171717',
        'split_panel_bg_image_url' => '',
        'split_welcome_heading'    => 'Welcome back',
        'split_subtitle'           => 'Sign in to continue',
    ];

    /**
     * Return translated default texts (cannot use __() inside class constants).
     */
    public static function getTranslatedDefaults(): array
    {
        $defaults = self::DEFAULTS;
        $defaults['split_welcome_heading'] = __('Welcome back', 'wp-sms');
        $defaults['split_subtitle'] = __('Sign in to continue', 'wp-sms');

        return $defaults;
    }

    public function all(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $raw = get_option(self::OPTION_KEY, []);

        if (!is_array($raw)) {
            $raw = [];
        }

        return $this->cached = array_merge(self::getTranslatedDefaults(), $raw);
    }

    public function get(string $key, $default = null)
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    public function update(array $settings): bool
    {
        $current = $this->all();

        foreach ($settings as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                $current[$key] = $value;
            }
        }

        $this->cached = null;

        return update_option(self::OPTION_KEY, $current, false);
    }

    public function getPublicConfig(): array
    {
        return $this->all();
    }
}
