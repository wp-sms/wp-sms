<?php

namespace WSms\MessagingButton;

defined('ABSPATH') || exit;

class MessagingButtonSettings
{
    public const OPTION_KEY = 'wsms_messaging_button';

    private ?array $cached = null;

    public const DEFAULTS = [
        'enabled' => false,
        'button' => [
            'position' => 'bottom-right',
            'style' => 'icon-text',
            'text' => 'Chat with us',
            'primary_color' => null,
            'text_color' => '#ffffff',
            'attention' => 'none',
        ],
        'widget' => [
            'title' => 'Hi there!',
            'subtitle' => 'How can we help?',
            'theme' => 'light',
        ],
        'pages' => [
            'welcome' => [
                'enabled' => true,
                'greeting' => 'Welcome! Choose an option below to get started.',
                'cta_label' => 'Send a message',
            ],
            'contact_form' => [
                'enabled' => true,
                'fields' => ['name', 'email', 'phone', 'message'],
                'required_fields' => ['email', 'message'],
                'channel' => 'email',
                'gateway_id' => null,
                'notification_recipients' => [],
                'auto_tag' => null,
                'auto_list' => null,
            ],
            'team' => ['enabled' => false],
            'resources' => ['enabled' => false, 'links' => []],
        ],
        'team_members' => [],
        'display_rules' => [
            'auto_inject' => true,
            'include_urls' => [],
            'exclude_urls' => [],
            'visibility' => 'everyone',
        ],
        'greeting_bubble' => [
            'enabled' => false,
            'message' => 'Need help? We\'re online!',
            'delay' => 3,
            'duration' => 8,
            'open_on_click' => true,
        ],
        'default_message' => '',
        'triggers' => [
            'auto_open_delay' => 0,
            'scroll_percent' => 0,
            'exit_intent' => false,
        ],
        'business_hours' => [
            'enabled' => false,
            'timezone' => 'UTC',
            'schedule' => [],
            'offline_message' => 'We are currently offline.',
        ],
        'gdpr' => [
            'enabled' => false,
            'consent_text' => 'I agree to the privacy policy.',
            'link_url' => '',
        ],
    ];

    /**
     * Return translated default texts (cannot use __() inside class constants).
     */
    public static function getTranslatedDefaults(): array
    {
        $defaults = self::DEFAULTS;
        $defaults['button']['text'] = __('Chat with us', 'wp-sms');
        $defaults['widget']['title'] = __('Hi there!', 'wp-sms');
        $defaults['widget']['subtitle'] = __('How can we help?', 'wp-sms');
        $defaults['pages']['welcome']['greeting'] = __('Welcome! Choose an option below to get started.', 'wp-sms');
        $defaults['pages']['welcome']['cta_label'] = __('Send a message', 'wp-sms');
        $defaults['greeting_bubble']['message'] = __('Need help? We\'re online!', 'wp-sms');
        $defaults['business_hours']['offline_message'] = __('We are currently offline.', 'wp-sms');
        $defaults['gdpr']['consent_text'] = __('I agree to the privacy policy.', 'wp-sms');

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

        $merged = $this->mergeDefaults($raw, self::getTranslatedDefaults());

        // Always use the WordPress timezone
        $merged['business_hours']['timezone'] = wp_timezone_string();

        $this->cached = $merged;

        return $this->cached;
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
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            // Sequential arrays (team_members, links, etc.) must be replaced wholesale,
            // not merged — array_merge would concatenate them. Associative arrays get merged.
            if (is_array(self::DEFAULTS[$key]) && is_array($value) && !array_is_list(self::DEFAULTS[$key])) {
                $current[$key] = array_merge($current[$key], $value);
            } else {
                $current[$key] = $value;
            }
        }

        $this->cached = null;

        return update_option(self::OPTION_KEY, $current);
    }

    public function isEnabled(): bool
    {
        // Read the raw option directly to avoid triggering getTranslatedDefaults()
        // (which calls __()) before the textdomain is loaded at 'init'.
        if ($this->cached !== null) {
            return (bool) ($this->cached['enabled'] ?? false);
        }

        $raw = get_option(self::OPTION_KEY, []);

        return (bool) (is_array($raw) ? ($raw['enabled'] ?? self::DEFAULTS['enabled']) : self::DEFAULTS['enabled']);
    }

    /**
     * Get only the public-safe config (no admin-only fields).
     */
    public function getPublicConfig(): array
    {
        $all = $this->all();

        // Exclude admin-only keys (display_rules)
        unset($all['display_rules']);

        return $all;
    }

    private function mergeDefaults(array $raw, array $defaults = null): array
    {
        $defaults = $defaults ?? self::DEFAULTS;
        $result = [];

        foreach ($defaults as $key => $default) {
            if (!isset($raw[$key])) {
                $result[$key] = $default;
            } elseif (is_array($default) && is_array($raw[$key])) {
                $result[$key] = $this->deepMerge($default, $raw[$key]);
            } else {
                $result[$key] = $raw[$key];
            }
        }

        return $result;
    }

    private function deepMerge(array $defaults, array $values): array
    {
        $result = $defaults;

        foreach ($values as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && !array_is_list($defaults[$key])) {
                $result[$key] = $this->deepMerge($defaults[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
