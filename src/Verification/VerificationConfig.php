<?php

namespace WSms\Verification;

defined('ABSPATH') || exit;

class VerificationConfig
{
    private const OPTION_KEY = 'wsms_verification_settings';

    private const DEFAULTS = [
        'enabled'     => true,
        'email'       => ['enabled' => true,  'code_length' => 6, 'expiry' => 300, 'max_attempts' => 3, 'cooldown' => 60],
        'phone'       => ['enabled' => true,  'code_length' => 6, 'expiry' => 300, 'max_attempts' => 3, 'cooldown' => 60],
        'session_ttl' => 1800,
    ];

    private ?array $settings = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    public function getChannelConfig(string $channel): array
    {
        $all = $this->all();
        $channelDefaults = self::DEFAULTS[$channel] ?? [];
        $config = array_merge($channelDefaults, $all[$channel] ?? []);

        // Enforce safe minimums to prevent misconfiguration.
        $config['code_length'] = max(4, (int) ($config['code_length'] ?? 6));
        $config['expiry'] = max(60, (int) ($config['expiry'] ?? 300));
        $config['max_attempts'] = max(1, (int) ($config['max_attempts'] ?? 3));
        $config['cooldown'] = max(0, (int) ($config['cooldown'] ?? 60));

        return $config;
    }

    public function isChannelEnabled(string $channel): bool
    {
        $config = $this->getChannelConfig($channel);

        return !empty($config['enabled']);
    }

    public function all(): array
    {
        if ($this->settings === null) {
            $stored = get_option(self::OPTION_KEY, []);
            $this->settings = array_replace_recursive(self::DEFAULTS, is_array($stored) ? $stored : []);
        }

        return $this->settings;
    }
}
