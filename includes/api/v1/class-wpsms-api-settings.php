<?php

namespace WP_SMS\Api\V1;

use WP_REST_Server;
use WP_REST_Request;
use WP_SMS\RestApi;
use WP_SMS\Option;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings REST API Controller
 */
class SettingsApi extends RestApi
{
    /**
     * Placeholder value for masked sensitive fields
     */
    const MASKED_VALUE = '••••••••';

    /**
     * Sensitive fields that should be masked in responses
     *
     * @var array
     */
    private $sensitiveFields = [
        'gateway_password',
        'gateway_key',
    ];

    /**
     * Validation rules for settings fields
     *
     * @var array
     */
    private $validationRules = [
        'gateway_name' => [
            'type' => 'gateway',
        ],
        'admin_mobile_number' => [
            'type' => 'phone',
        ],
        'webhook_outgoing_sms' => [
            'type' => 'url',
        ],
        'notif_publish_new_post_receiver' => [
            'type' => 'phone',
        ],
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        parent::__construct();
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes()
    {
        // Get all settings
        register_rest_route($this->namespace . '/v1', '/settings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // Get specific settings section
        register_rest_route($this->namespace . '/v1', '/settings/(?P<section>[a-zA-Z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getSettingsSection'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'section' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        // Test gateway connection
        register_rest_route($this->namespace . '/v1', '/settings/test-gateway', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'testGateway'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    /**
     * Check if user has permission to access settings
     *
     * @return bool
     */
    public function checkPermission()
    {
        return current_user_can('wpsms_setting');
    }

    /**
     * Get all settings
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getSettings(WP_REST_Request $request)
    {
        $settings = Option::getOptions();
        $proSettings = Option::getOptions(true);

        return self::response(__('Settings retrieved successfully', 'wp-sms'), 200, [
            'settings'    => $this->maskSensitiveSettings($settings),
            'proSettings' => $this->maskSensitiveSettings($proSettings),
        ]);
    }

    /**
     * Get settings for a specific section
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getSettingsSection(WP_REST_Request $request)
    {
        $section = $request->get_param('section');
        $settings = Option::getOptions();
        $proSettings = Option::getOptions(true);

        $sectionSettings = $this->filterSettingsBySection($settings, $proSettings, $section);

        return self::response(__('Section settings retrieved successfully', 'wp-sms'), 200, [
            'section'  => $section,
            'settings' => $sectionSettings,
        ]);
    }

    /**
     * Update settings
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function updateSettings(WP_REST_Request $request)
    {
        $data = $request->get_json_params();

        if (empty($data)) {
            return self::response(__('No settings data provided', 'wp-sms'), 400);
        }

        $allErrors = [];

        // Validate and update main settings
        if (isset($data['settings']) && is_array($data['settings'])) {
            $sanitizedSettings = $this->sanitizeSettings($data['settings']);

            // Validate settings before saving
            $validation = $this->validateSettings($sanitizedSettings);
            if (!$validation['valid']) {
                $allErrors = array_merge($allErrors, $validation['errors']);
            }
        }

        // Validate pro settings
        if (isset($data['proSettings']) && is_array($data['proSettings'])) {
            $sanitizedProSettings = $this->sanitizeSettings($data['proSettings']);

            // Validate pro settings before saving
            $proValidation = $this->validateSettings($sanitizedProSettings);
            if (!$proValidation['valid']) {
                $allErrors = array_merge($allErrors, $proValidation['errors']);
            }
        }

        // Return validation errors if any
        if (!empty($allErrors)) {
            return self::response(__('Validation failed', 'wp-sms'), 400, [
                'errors' => $allErrors,
            ]);
        }

        // Update main settings
        if (isset($data['settings']) && is_array($data['settings'])) {
            $currentSettings = Option::getOptions();
            $sanitizedSettings = $this->sanitizeSettings($data['settings']);
            // Preserve existing values for masked sensitive fields
            $sanitizedSettings = $this->preserveSensitiveFields($sanitizedSettings, $currentSettings);
            $mergedSettings = array_merge($currentSettings, $sanitizedSettings);
            update_option('wpsms_settings', $mergedSettings);
        }

        // Update pro settings
        if (isset($data['proSettings']) && is_array($data['proSettings'])) {
            $currentProSettings = Option::getOptions(true);
            $sanitizedProSettings = $this->sanitizeSettings($data['proSettings']);
            // Preserve existing values for masked sensitive fields
            $sanitizedProSettings = $this->preserveSensitiveFields($sanitizedProSettings, $currentProSettings);
            $mergedProSettings = array_merge($currentProSettings, $sanitizedProSettings);
            update_option('wps_pp_settings', $mergedProSettings);
        }

        // Update add-on settings (individual WordPress options)
        if (isset($data['addonValues']) && is_array($data['addonValues'])) {
            $this->updateAddonSettings($data['addonValues']);
        }

        // Clear any cached settings
        wp_cache_delete('wpsms_settings', 'options');
        wp_cache_delete('wps_pp_settings', 'options');

        // Fire action for audit logging
        $this->triggerSettingsUpdatedAction($data, $currentSettings ?? [], $currentProSettings ?? []);

        return self::response(__('Settings saved successfully', 'wp-sms'), 200, [
            'settings'    => $this->maskSensitiveSettings(Option::getOptions()),
            'proSettings' => $this->maskSensitiveSettings(Option::getOptions(true)),
        ]);
    }

    /**
     * Trigger action hook for settings audit logging
     *
     * Allows plugins to track settings changes for audit purposes
     *
     * @param array $newData New settings data from request
     * @param array $oldSettings Previous main settings
     * @param array $oldProSettings Previous pro settings
     */
    private function triggerSettingsUpdatedAction($newData, $oldSettings, $oldProSettings)
    {
        $changedKeys = [];
        $oldValues = [];
        $newValues = [];

        // Check main settings changes
        if (isset($newData['settings']) && is_array($newData['settings'])) {
            foreach ($newData['settings'] as $key => $value) {
                $oldValue = $oldSettings[$key] ?? null;
                // Skip masked values (they weren't actually changed)
                if ($value === self::MASKED_VALUE) {
                    continue;
                }
                if ($oldValue !== $value) {
                    $changedKeys[] = $key;
                    $oldValues[$key] = $oldValue;
                    $newValues[$key] = $value;
                }
            }
        }

        // Check pro settings changes
        if (isset($newData['proSettings']) && is_array($newData['proSettings'])) {
            foreach ($newData['proSettings'] as $key => $value) {
                $oldValue = $oldProSettings[$key] ?? null;
                if ($value === self::MASKED_VALUE) {
                    continue;
                }
                if ($oldValue !== $value) {
                    $changedKeys[] = 'pro_' . $key;
                    $oldValues['pro_' . $key] = $oldValue;
                    $newValues['pro_' . $key] = $value;
                }
            }
        }

        // Only fire if there were actual changes
        if (!empty($changedKeys)) {
            /**
             * Action: wpsms_settings_updated
             *
             * Fires when settings are updated via the REST API.
             * Useful for audit logging and tracking configuration changes.
             *
             * @param array $changedKeys List of setting keys that changed
             * @param array $oldValues Previous values (keyed by setting name)
             * @param array $newValues New values (keyed by setting name)
             * @param int $userId ID of the user who made the changes
             */
            do_action('wpsms_settings_updated', $changedKeys, $oldValues, $newValues, get_current_user_id());
        }
    }

    /**
     * Update add-on settings as individual WordPress options
     *
     * Handles saving add-on field values to their respective option keys.
     * Converts booleans to 'yes'/'no' for WooCommerce compatibility.
     *
     * @param array $addonValues Add-on values keyed by addon slug
     */
    private function updateAddonSettings($addonValues)
    {
        $addonFieldTypes = $this->getAddonFieldTypes();

        foreach ($addonValues as $addonSlug => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            /**
             * Filter to allow add-ons to handle their own save logic.
             *
             * If the filter returns true, the default save is skipped for this add-on.
             * This is useful for add-ons that use legacy storage like wpsms_settings array.
             *
             * @param bool  $handled   Whether the add-on handled the save. Default false.
             * @param array $fields    The field values to save.
             * @param array $fieldTypes Field type mapping for sanitization reference.
             */
            $handled = apply_filters('wpsms_addon_save_settings_' . $addonSlug, false, $fields, $addonFieldTypes);

            if ($handled) {
                continue;
            }

            foreach ($fields as $optionKey => $value) {
                $sanitizedKey = sanitize_key($optionKey);
                $fieldType = $addonFieldTypes[$sanitizedKey] ?? 'text';

                // Convert boolean values to 'yes'/'no' for WooCommerce compatibility
                if (in_array($fieldType, ['switch', 'checkbox'], true)) {
                    $value = $value ? 'yes' : 'no';
                } elseif ($fieldType === 'multi-select' && is_array($value)) {
                    // Multi-select arrays are saved as-is
                    $value = array_map('sanitize_text_field', $value);
                } elseif (is_array($value)) {
                    // Other arrays (like repeater)
                    $value = $this->sanitizeAddonField($value, $fieldType);
                } else {
                    // Sanitize based on field type
                    $value = $this->sanitizeAddonField($value, $fieldType);
                }

                update_option($sanitizedKey, $value);
            }
        }
    }

    /**
     * Test gateway connection
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function testGateway(WP_REST_Request $request)
    {
        global $sms;

        if (!$sms) {
            return self::response(__('Gateway not configured', 'wp-sms'), 400);
        }

        try {
            $credit = $sms->GetCredit();

            if (is_wp_error($credit)) {
                return self::response($credit->get_error_message(), 400, [
                    'rawResponse' => var_export($credit->get_error_message(), true),
                ]);
            }

            return self::response(__('Gateway connection successful', 'wp-sms'), 200, [
                'credit'      => $credit,
                'gateway'     => Option::getOption('gateway_name'),
                'rawResponse' => var_export($credit, true),
            ]);
        } catch (\Exception $e) {
            return self::response($e->getMessage(), 500, [
                'rawResponse' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Filter settings by section
     *
     * @param array $settings
     * @param array $proSettings
     * @param string $section
     * @return array
     */
    private function filterSettingsBySection($settings, $proSettings, $section)
    {
        $sectionMappings = [
            'gateway'        => ['gateway_name', 'gateway_username', 'gateway_password', 'gateway_key', 'gateway_sender_id'],
            'phone'          => ['admin_mobile_number', 'mobile_county_code', 'international_mobile', 'mobile_field_source'],
            'newsletter'     => ['newsletter_form_groups', 'newsletter_form_verify', 'newsletter_form_gdpr'],
            'notifications'  => ['notif_publish_new_post', 'notif_register_new_user', 'notif_new_comment', 'notif_user_login'],
            'advanced'       => ['webhook_outgoing_sms', 'message_retention', 'report_wpsms_statistics'],
        ];

        if (!isset($sectionMappings[$section])) {
            return [];
        }

        $result = [];
        $keys = $sectionMappings[$section];

        foreach ($keys as $key) {
            if (isset($settings[$key])) {
                $result[$key] = $settings[$key];
            } elseif (isset($proSettings[$key])) {
                $result[$key] = $proSettings[$key];
            }
        }

        return $result;
    }

    /**
     * Sanitize settings array
     *
     * @param array $settings
     * @return array
     */
    private function sanitizeSettings($settings)
    {
        $sanitized = [];
        $addonFieldTypes = $this->getAddonFieldTypes();

        foreach ($settings as $key => $value) {
            $sanitizedKey = sanitize_key($key);

            // Check if this is an add-on field with a specific type
            if (isset($addonFieldTypes[$sanitizedKey])) {
                $sanitized[$sanitizedKey] = $this->sanitizeAddonField($value, $addonFieldTypes[$sanitizedKey]);
            } elseif (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeSettings($value);
            } elseif (is_bool($value)) {
                $sanitized[$sanitizedKey] = $value;
            } elseif (is_numeric($value)) {
                $sanitized[$sanitizedKey] = $value;
            } else {
                $sanitized[$sanitizedKey] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize an add-on field based on its type
     *
     * @param mixed $value Field value
     * @param string $type Field type from schema
     * @return mixed Sanitized value
     */
    private function sanitizeAddonField($value, $type)
    {
        switch ($type) {
            case 'switch':
            case 'checkbox':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            case 'number':
                return is_numeric($value) ? floatval($value) : 0;

            case 'multi-select':
                if (!is_array($value)) {
                    return [];
                }
                return array_map('sanitize_text_field', $value);

            case 'repeater':
                if (!is_array($value)) {
                    return [];
                }
                // Recursively sanitize each item in the repeater
                return array_map(function ($item) {
                    if (!is_array($item)) {
                        return [];
                    }
                    $sanitizedItem = [];
                    foreach ($item as $itemKey => $itemValue) {
                        $sanitizedItem[sanitize_key($itemKey)] = is_array($itemValue)
                            ? array_map('sanitize_text_field', $itemValue)
                            : sanitize_text_field($itemValue);
                    }
                    return $sanitizedItem;
                }, $value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'password':
            case 'text':
            case 'select':
            default:
                return is_array($value)
                    ? array_map('sanitize_text_field', $value)
                    : sanitize_text_field($value);
        }
    }

    /**
     * Mask sensitive fields in settings array
     *
     * Replaces actual values with masked placeholder to prevent
     * sensitive data from being exposed via the API
     *
     * @param array $settings
     * @return array
     */
    private function maskSensitiveSettings($settings)
    {
        if (!is_array($settings)) {
            return $settings;
        }

        foreach ($this->sensitiveFields as $field) {
            if (isset($settings[$field]) && !empty($settings[$field])) {
                $settings[$field] = self::MASKED_VALUE;
            }
        }

        return $settings;
    }

    /**
     * Preserve existing values for sensitive fields when masked value is received
     *
     * When the frontend sends the masked placeholder value, we keep
     * the existing value instead of overwriting with the placeholder
     *
     * @param array $newSettings New settings from request
     * @param array $currentSettings Current stored settings
     * @return array Settings with sensitive fields preserved if masked
     */
    private function preserveSensitiveFields($newSettings, $currentSettings)
    {
        foreach ($this->sensitiveFields as $field) {
            if (isset($newSettings[$field])) {
                // If the value is the masked placeholder, keep the existing value
                if ($newSettings[$field] === self::MASKED_VALUE) {
                    if (isset($currentSettings[$field])) {
                        $newSettings[$field] = $currentSettings[$field];
                    } else {
                        unset($newSettings[$field]);
                    }
                }
                // If the value is empty, allow clearing the field
                // Otherwise, the new value will be saved
            }
        }

        return $newSettings;
    }

    /**
     * Validate settings against defined rules
     *
     * @param array $settings Settings to validate
     * @return array Array with 'valid' boolean and 'errors' array
     */
    private function validateSettings($settings)
    {
        $errors = [];

        // Merge core validation rules with add-on validation rules
        $allRules = array_merge($this->validationRules, $this->getAddonValidationRules());

        foreach ($settings as $key => $value) {
            if (!isset($allRules[$key])) {
                continue;
            }

            $rule = $allRules[$key];
            $error = $this->validateField($key, $value, $rule);

            if ($error) {
                $errors[$key] = $error;
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get validation rules from add-on settings schemas
     *
     * Extracts validation rules defined by add-ons via the wpsms_addon_settings_schema filter.
     *
     * @return array Validation rules keyed by field ID
     */
    private function getAddonValidationRules()
    {
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        $rules = [];

        foreach ($schemas as $schema) {
            if (empty($schema['fields']) || !is_array($schema['fields'])) {
                continue;
            }

            foreach ($schema['fields'] as $field) {
                if (empty($field['id']) || empty($field['validation'])) {
                    continue;
                }

                $rules[$field['id']] = $field['validation'];
            }
        }

        return $rules;
    }

    /**
     * Get field types from add-on settings schemas
     *
     * Used for type-specific sanitization of add-on settings.
     *
     * @return array Field types keyed by field ID
     */
    private function getAddonFieldTypes()
    {
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        $types = [];

        foreach ($schemas as $schema) {
            if (empty($schema['fields']) || !is_array($schema['fields'])) {
                continue;
            }

            foreach ($schema['fields'] as $field) {
                if (empty($field['id']) || empty($field['type'])) {
                    continue;
                }

                $types[$field['id']] = $field['type'];
            }
        }

        return $types;
    }

    /**
     * Validate a single field based on its rule
     *
     * @param string $key Field name
     * @param mixed $value Field value
     * @param array $rule Validation rule
     * @return string|null Error message or null if valid
     */
    private function validateField($key, $value, $rule)
    {
        // Check if this is a core validation rule (has 'type' key)
        if (isset($rule['type'])) {
            // Skip validation for empty values (they're optional)
            if (empty($value)) {
                return null;
            }

            switch ($rule['type']) {
                case 'gateway':
                    return $this->validateGateway($value);

                case 'phone':
                    return $this->validatePhone($value);

                case 'url':
                    return $this->validateUrl($value);

                default:
                    return null;
            }
        }

        // Handle add-on validation rules
        return $this->validateAddonFieldRules($value, $rule);
    }

    /**
     * Validate a field against add-on validation rules
     *
     * @param mixed $value Field value
     * @param array $rules Validation rules from add-on schema
     * @return string|null Error message or null if valid
     */
    private function validateAddonFieldRules($value, $rules)
    {
        // Required check
        if (!empty($rules['required']) && empty($value) && $value !== 0 && $value !== '0') {
            return __('This field is required', 'wp-sms');
        }

        // Skip other validations for empty values
        if (empty($value) && $value !== 0 && $value !== '0') {
            return null;
        }

        // String length validations
        if (is_string($value)) {
            $length = mb_strlen($value);

            if (isset($rules['minLength']) && $length < $rules['minLength']) {
                return sprintf(
                    __('Value must be at least %d characters', 'wp-sms'),
                    $rules['minLength']
                );
            }

            if (isset($rules['maxLength']) && $length > $rules['maxLength']) {
                return sprintf(
                    __('Value must not exceed %d characters', 'wp-sms'),
                    $rules['maxLength']
                );
            }

            // Pattern validation
            if (isset($rules['pattern'])) {
                $pattern = '/' . $rules['pattern'] . '/';
                if (!preg_match($pattern, $value)) {
                    return __('Value does not match the required format', 'wp-sms');
                }
            }
        }

        // Numeric validations
        if (is_numeric($value)) {
            $numericValue = floatval($value);

            if (isset($rules['min']) && $numericValue < $rules['min']) {
                return sprintf(
                    __('Value must be at least %s', 'wp-sms'),
                    $rules['min']
                );
            }

            if (isset($rules['max']) && $numericValue > $rules['max']) {
                return sprintf(
                    __('Value must not exceed %s', 'wp-sms'),
                    $rules['max']
                );
            }
        }

        // Type-specific validations
        if (isset($rules['type'])) {
            switch ($rules['type']) {
                case 'phone':
                    return $this->validatePhone($value);

                case 'email':
                    if (!is_email($value)) {
                        return __('Invalid email address', 'wp-sms');
                    }
                    break;

                case 'url':
                    return $this->validateUrl($value);
            }
        }

        return null;
    }

    /**
     * Validate gateway name against available gateways
     *
     * @param string $value Gateway name
     * @return string|null Error message or null if valid
     */
    private function validateGateway($value)
    {
        $availableGateways = \WP_SMS\Gateway::gateway();

        // Flatten gateway groups to get all gateway keys
        $allGateways = [];
        foreach ($availableGateways as $group) {
            if (is_array($group)) {
                $allGateways = array_merge($allGateways, array_keys($group));
            }
        }

        if (!in_array($value, $allGateways, true)) {
            return __('Invalid gateway selected', 'wp-sms');
        }

        return null;
    }

    /**
     * Validate phone number format
     *
     * @param string $value Phone number
     * @return string|null Error message or null if valid
     */
    private function validatePhone($value)
    {
        // Allow + at start, then digits, spaces, dashes, parentheses
        $pattern = '/^\+?[\d\s\-\(\)]{5,20}$/';

        if (!preg_match($pattern, $value)) {
            return __('Invalid phone number format', 'wp-sms');
        }

        return null;
    }

    /**
     * Validate URL format
     *
     * @param string $value URL
     * @return string|null Error message or null if valid
     */
    private function validateUrl($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return __('Invalid URL format', 'wp-sms');
        }

        // Only allow http and https protocols
        $parsed = wp_parse_url($value);
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
            return __('URL must use http or https protocol', 'wp-sms');
        }

        return null;
    }
}

new SettingsApi();
