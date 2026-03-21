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
