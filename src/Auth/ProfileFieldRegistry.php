<?php

namespace WSms\Auth;

use WSms\Auth\ValueObjects\ProfileFieldDefinition;

defined('ABSPATH') || exit;

class ProfileFieldRegistry
{
    /** Meta key prefixes blacklisted from the picker endpoint. */
    private const META_KEY_BLACKLIST_PREFIXES = ['wp_', 'wsms_', '_'];

    /** Exact meta keys blacklisted from the picker endpoint. */
    private const META_KEY_BLACKLIST_EXACT = [
        'session_tokens', 'rich_editing', 'syntax_highlighting',
        'comment_shortcuts', 'admin_color', 'locale', 'dismissed_wp_pointers',
        'show_admin_bar_front', 'use_ssl',
    ];

    public function __construct(
        private SettingsRepository $settings,
    ) {
    }

    /**
     * System field definitions for the 6 built-in fields.
     *
     * @return ProfileFieldDefinition[]
     */
    public function getSystemDefaults(): array
    {
        return [
            new ProfileFieldDefinition(
                id: 'email', type: 'text', label: 'Email',
                source: 'system', metaKey: 'user_email',
                visibility: 'both', required: true, sortOrder: 1,
            ),
            new ProfileFieldDefinition(
                id: 'password', type: 'text', label: 'Password',
                source: 'system', metaKey: 'user_pass',
                visibility: 'registration', required: true, sortOrder: 2,
            ),
            new ProfileFieldDefinition(
                id: 'phone', type: 'text', label: 'Phone Number',
                source: 'system', metaKey: 'wsms_phone',
                visibility: 'both', required: false, sortOrder: 3,
            ),
            new ProfileFieldDefinition(
                id: 'first_name', type: 'text', label: 'First Name',
                source: 'system', metaKey: 'first_name',
                visibility: 'both', required: false, sortOrder: 4,
            ),
            new ProfileFieldDefinition(
                id: 'last_name', type: 'text', label: 'Last Name',
                source: 'system', metaKey: 'last_name',
                visibility: 'both', required: false, sortOrder: 5,
            ),
            new ProfileFieldDefinition(
                id: 'display_name', type: 'text', label: 'Display Name',
                source: 'system', metaKey: 'display_name',
                visibility: 'both', required: false, sortOrder: 6,
            ),
        ];
    }

    /**
     * Get all fields (system + custom), sorted by sort_order.
     *
     * @return ProfileFieldDefinition[]
     */
    public function getAllFields(): array
    {
        $systemFields = $this->getSystemDefaults();
        $customData = $this->settings->get('profile_fields', []);

        // Build index of system field IDs for override.
        $systemById = [];
        foreach ($systemFields as $field) {
            $systemById[$field->id] = $field;
        }

        // Custom profile_fields may override system fields (sort_order, required, visibility).
        $merged = [];
        $customIds = [];

        foreach ($customData as $raw) {
            $def = ProfileFieldDefinition::fromArray($raw);

            if (isset($systemById[$def->id])) {
                // Override system field with admin-configured sort_order, required, visibility.
                $sys = $systemById[$def->id];
                $merged[] = new ProfileFieldDefinition(
                    id: $sys->id,
                    type: $sys->type,
                    label: $raw['label'] ?? $sys->label,
                    source: $sys->source,
                    metaKey: $sys->metaKey,
                    visibility: $raw['visibility'] ?? $sys->visibility,
                    required: $raw['required'] ?? $sys->required,
                    sortOrder: $raw['sort_order'] ?? $sys->sortOrder,
                    placeholder: $raw['placeholder'] ?? $sys->placeholder,
                    description: $raw['description'] ?? $sys->description,
                    defaultValue: $raw['default_value'] ?? $sys->defaultValue,
                );
                $customIds[$def->id] = true;
            } else {
                $merged[] = $def;
                $customIds[$def->id] = true;
            }
        }

        // Add system fields not present in profile_fields.
        foreach ($systemFields as $sys) {
            if (!isset($customIds[$sys->id])) {
                $merged[] = $sys;
            }
        }

        usort($merged, fn(ProfileFieldDefinition $a, ProfileFieldDefinition $b) => $a->sortOrder <=> $b->sortOrder);

        return $merged;
    }

    /**
     * Get fields filtered by context ('registration' or 'profile').
     *
     * @return ProfileFieldDefinition[]
     */
    public function getFieldsForContext(string $context): array
    {
        return array_values(array_filter(
            $this->getAllFields(),
            fn(ProfileFieldDefinition $f) => $f->isVisibleIn($context),
        ));
    }

    /**
     * Get only custom (non-system) fields.
     *
     * @return ProfileFieldDefinition[]
     */
    public function getCustomFields(): array
    {
        return array_values(array_filter(
            $this->getAllFields(),
            fn(ProfileFieldDefinition $f) => !$f->isSystem(),
        ));
    }

    /**
     * Register meta for custom-source fields via register_meta().
     * Called on WordPress 'init' hook.
     */
    public function registerMeta(): void
    {
        foreach ($this->getAllFields() as $field) {
            if ($field->source !== ProfileFieldDefinition::SOURCE_CUSTOM) {
                continue;
            }

            register_meta('user', $field->metaKey, [
                'type'              => $field->type === 'checkbox' ? 'boolean' : 'string',
                'single'            => true,
                'show_in_rest'      => false,
                'sanitize_callback' => fn($value) => $this->sanitizeValue($field, $value),
            ]);
        }
    }

    /**
     * Read a field value for a user.
     */
    public function readValue(int $userId, ProfileFieldDefinition $field): mixed
    {
        return get_user_meta($userId, $field->metaKey, true);
    }

    /**
     * Write a field value for a user.
     */
    public function writeValue(int $userId, ProfileFieldDefinition $field, mixed $value): void
    {
        $sanitized = $this->sanitizeValue($field, $value);
        update_user_meta($userId, $field->metaKey, $sanitized);
    }

    /**
     * Sanitize a value based on field type.
     */
    public function sanitizeValue(ProfileFieldDefinition $field, mixed $value): mixed
    {
        return match ($field->type) {
            'textarea' => sanitize_textarea_field((string) $value),
            'checkbox' => (bool) $value,
            'select'   => $this->sanitizeSelectValue($field, (string) $value),
            default    => sanitize_text_field((string) $value),
        };
    }

    /**
     * Validate a select value against the allowed options whitelist.
     */
    private function sanitizeSelectValue(ProfileFieldDefinition $field, string $value): string
    {
        if ($value === '') {
            return '';
        }

        $allowedValues = array_column($field->options, 'value');

        return in_array($value, $allowedValues, true) ? $value : '';
    }

    /**
     * Scan existing meta keys from wp_usermeta for the meta picker.
     * Admin-only. Results cached in a transient (1 hour).
     *
     * @return array<int, array{key: string, sample_value: string, count: int}>
     */
    public function scanMetaKeys(): array
    {
        $cached = get_transient('wsms_meta_keys_scan');
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT meta_key, COUNT(*) AS cnt, MIN(meta_value) AS sample
             FROM {$wpdb->usermeta}
             GROUP BY meta_key
             ORDER BY cnt DESC
             LIMIT 500",
        );

        $results = [];
        foreach ($rows as $row) {
            if ($this->isBlacklistedMetaKey($row->meta_key)) {
                continue;
            }

            $results[] = [
                'key'          => $row->meta_key,
                'sample_value' => mb_substr((string) $row->sample, 0, 100),
                'count'        => (int) $row->cnt,
            ];
        }

        set_transient('wsms_meta_keys_scan', $results, HOUR_IN_SECONDS);

        return $results;
    }

    private function isBlacklistedMetaKey(string $key): bool
    {
        if (in_array($key, self::META_KEY_BLACKLIST_EXACT, true)) {
            return true;
        }

        foreach (self::META_KEY_BLACKLIST_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate a profile_fields array from admin settings input.
     *
     * @return string[] Validation errors (empty if valid).
     */
    public function validateFieldsConfig(array $fields): array
    {
        $errors = [];
        $ids = [];

        foreach ($fields as $i => $field) {
            $id = $field['id'] ?? '';

            if (empty($id)) {
                $errors[] = "profile_fields[{$i}]: id is required.";
                continue;
            }

            if (isset($ids[$id])) {
                $errors[] = "profile_fields: duplicate id '{$id}'.";
            }
            $ids[$id] = true;

            $type = $field['type'] ?? 'text';
            if (!in_array($type, ProfileFieldDefinition::VALID_TYPES, true)) {
                $errors[] = "profile_fields[{$i}]: invalid type '{$type}'.";
            }

            if ($type === 'select' && empty($field['options'])) {
                $errors[] = "profile_fields[{$i}]: select type requires options.";
            }

            $visibility = $field['visibility'] ?? 'both';
            if (!in_array($visibility, ProfileFieldDefinition::VALID_VISIBILITY, true)) {
                $errors[] = "profile_fields[{$i}]: invalid visibility '{$visibility}'.";
            }

            $source = $field['source'] ?? 'custom';
            if (!in_array($source, ProfileFieldDefinition::VALID_SOURCES, true)) {
                $errors[] = "profile_fields[{$i}]: invalid source '{$source}'.";
            }
        }

        return $errors;
    }
}
