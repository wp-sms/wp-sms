<?php

namespace WSms\PhoneRestriction;

defined('ABSPATH') || exit;

class RestrictionSettings
{
    private const OPTION_KEY = 'wsms_phone_restriction_settings';

    private const DEFAULTS = [
        'auth' => [
            'enabled'           => false,
            'mode'              => 'allow',
            'allowed_countries' => [],
        ],
        'messaging' => [
            'enabled'           => false,
            'mode'              => 'allow',
            'allowed_countries' => [],
        ],
        'number_type_blocking' => [
            'enabled'       => false,
            'blocked_types' => ['premium_rate', 'toll_free', 'shared_cost'],
        ],
        'enhanced_db' => [
            'auto_update' => true,
        ],
        'phone_input' => [
            'default_country'     => '',
            'preferred_countries' => [],
            'display_mode'        => 'separate_dial_code',
        ],
    ];

    private ?array $cached = null;

    public function all(): array
    {
        if ($this->cached === null) {
            $saved        = get_option(self::OPTION_KEY, []);
            $this->cached = $this->mergeDefaults($saved);
        }

        return $this->cached;
    }

    public function save(array $settings): void
    {
        $current = $this->all();

        foreach ($settings as $section => $values) {
            if (!is_array($values) || !isset($current[$section])) {
                continue;
            }

            foreach ($values as $key => $value) {
                $current[$section][$key] = $value;
            }
        }

        update_option(self::OPTION_KEY, $current);
        $this->cached = null;
    }

    public function isRestrictionEnabled(string $context): bool
    {
        return !empty($this->all()[$context]['enabled']);
    }

    public function getMode(string $context): string
    {
        return $this->all()[$context]['mode'] ?? 'allow';
    }

    public function getAllowedCountries(string $context): array
    {
        return $this->all()[$context]['allowed_countries'] ?? [];
    }

    public function isNumberTypeBlockingEnabled(): bool
    {
        return !empty($this->all()['number_type_blocking']['enabled']);
    }

    public function getBlockedNumberTypes(): array
    {
        return $this->all()['number_type_blocking']['blocked_types'] ?? [];
    }

    public function isAutoUpdateEnabled(): bool
    {
        return !empty($this->all()['enhanced_db']['auto_update']);
    }

    public function getPhoneInputDisplayConfig(string $context = ''): array
    {
        $all = $this->all();
        $pi  = $all['phone_input'];

        $defaultCountry     = $pi['default_country'] ?: 'US';
        $preferredCountries = $pi['preferred_countries'];
        $allowedCountries   = null;
        $excludedCountries  = null;

        if ($context !== '' && !empty($all[$context]['enabled'])) {
            $countries = $all[$context]['allowed_countries'] ?? [];
            if (!empty($countries)) {
                if ($all[$context]['mode'] === 'allow') {
                    $allowedCountries = $countries;
                } else {
                    $excludedCountries = $countries;
                }
            }
        }

        // Default country not in allowed list → fall back to first allowed
        if ($allowedCountries !== null && !in_array($defaultCountry, $allowedCountries, true)) {
            $defaultCountry = $allowedCountries[0] ?? 'US';
        }
        // Default country is in excluded list → fall back to 'US', or empty if US also excluded
        if ($excludedCountries !== null && in_array($defaultCountry, $excludedCountries, true)) {
            $defaultCountry = 'US';
            if (in_array('US', $excludedCountries, true)) {
                $defaultCountry = '';
            }
        }

        // Filter preferred countries against restrictions
        if ($allowedCountries !== null) {
            $preferredCountries = array_values(
                array_intersect($preferredCountries, $allowedCountries)
            );
        }
        if ($excludedCountries !== null) {
            $preferredCountries = array_values(
                array_diff($preferredCountries, $excludedCountries)
            );
        }

        $displayMode = $pi['display_mode'] ?? 'separate_dial_code';

        $config = [
            'defaultCountry'     => $defaultCountry,
            'preferredCountries' => $preferredCountries,
            'separateDialCode'   => $displayMode === 'separate_dial_code',
            'nationalMode'       => $displayMode === 'national',
            'allowDropdown'      => $displayMode !== 'national',
        ];

        if ($allowedCountries !== null && !empty($allowedCountries)) {
            $config['allowedCountries'] = $allowedCountries;
        }
        if ($excludedCountries !== null && !empty($excludedCountries)) {
            $config['excludedCountries'] = $excludedCountries;
        }

        return $config;
    }

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    private function mergeDefaults(array $saved): array
    {
        $result = self::DEFAULTS;

        foreach (self::DEFAULTS as $section => $sectionDefaults) {
            if (!isset($saved[$section]) || !is_array($saved[$section])) {
                continue;
            }

            foreach ($sectionDefaults as $key => $default) {
                if (array_key_exists($key, $saved[$section])) {
                    $result[$section][$key] = $saved[$section][$key];
                }
            }
        }

        return $result;
    }
}
