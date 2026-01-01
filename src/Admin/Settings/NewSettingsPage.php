<?php

namespace WP_SMS\Admin\Settings;

use WP_SMS\Components\Singleton;
use WP_SMS\Option;
use WP_SMS\Gateway;
use WP_SMS\Newsletter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * New Settings Page - React-based settings interface
 */
class NewSettingsPage extends Singleton
{
    /**
     * Sensitive fields that should be masked before sending to frontend
     *
     * @var array
     */
    private $sensitiveFields = [
        'gateway_password',
        'gateway_key',
    ];

    /**
     * Initialize the settings page
     */
    public function init()
    {
        // Assets are enqueued for the main SMS page (wp-sms)
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Static instance method required by MenuUtil
     *
     * @return static
     */
    public static function instance()
    {
        return parent::getInstance();
    }

    /**
     * Render the settings page
     */
    public function view()
    {
        // Hide WordPress admin notices, footer, and add full-width container
        echo '<style>
            .wrap { max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .wrap > h1:first-child { display: none; }
            .notice, .updated, .error, .is-dismissible { display: none !important; }
            #wpfooter { display: none !important; }
            #wpbody-content { padding: 0 !important; }
            #wpcontent { padding: 0 !important; }
            /* Hide chatbox by default - React Preview button will toggle it */
            .wpsms-chatbox { display: none !important; }
            .wpsms-chatbox.wpsms-chatbox--visible { display: block !important; }
        </style>';
        echo '<div id="wpsms-settings-root" class="wpsms-settings-app"></div>';
    }

    /**
     * Enqueue assets for the settings page
     *
     * @param string $hook
     */
    public function enqueueAssets($hook)
    {
        // This class no longer registers a menu, so assets are not loaded
        // The UnifiedAdminPage class handles asset loading for the Dashboard
        return;

        $distPath = WP_SMS_DIR . 'assets/dist/settings/';
        $distUrl = WP_SMS_URL . 'assets/dist/settings/';

        // Check for Vite manifest
        $manifestPath = $distPath . '.vite/manifest.json';

        // Use dev server if WPSMS_SETTINGS_DEV is defined or manifest doesn't exist
        $useDevServer = defined('WPSMS_SETTINGS_DEV') && WPSMS_SETTINGS_DEV;

        if (!$useDevServer && file_exists($manifestPath)) {
            // Production build
            $this->enqueueProductionAssets($manifestPath, $distUrl);
        } else {
            // Development mode - use Vite dev server
            $this->enqueueDevelopmentAssets();
        }

        // Localize script data
        wp_localize_script('wpsms-new-settings', 'wpSmsSettings', $this->getLocalizedData());
    }

    /**
     * Enqueue production assets from Vite build
     *
     * @param string $manifestPath
     * @param string $distUrl
     */
    private function enqueueProductionAssets($manifestPath, $distUrl)
    {
        $manifest = json_decode(file_get_contents($manifestPath), true);

        // Find the main entry file
        $mainEntry = null;
        foreach ($manifest as $key => $entry) {
            if (isset($entry['isEntry']) && $entry['isEntry']) {
                $mainEntry = $entry;
                break;
            }
        }

        if (!$mainEntry) {
            return;
        }

        // Enqueue CSS
        if (isset($mainEntry['css'])) {
            foreach ($mainEntry['css'] as $index => $cssFile) {
                wp_enqueue_style(
                    'wpsms-new-settings' . ($index > 0 ? '-' . $index : ''),
                    $distUrl . $cssFile,
                    [],
                    WP_SMS_VERSION
                );
            }
        }

        // Enqueue JS
        wp_enqueue_script(
            'wpsms-new-settings',
            $distUrl . $mainEntry['file'],
            [],
            WP_SMS_VERSION,
            true
        );

        // Add type="module" to the script tag for ESM
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle === 'wpsms-new-settings') {
                return str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Enqueue development assets from Vite dev server
     */
    private function enqueueDevelopmentAssets()
    {
        $viteDevServerUrl = 'http://localhost:5177';
        $localizedData = $this->getLocalizedData();

        // Inject localized data and Vite scripts directly in footer
        add_action('admin_footer', function () use ($viteDevServerUrl, $localizedData) {
            ?>
            <script>
                window.wpSmsSettings = <?php echo wp_json_encode($localizedData); ?>;
            </script>
            <script type="module">
                import RefreshRuntime from '<?php echo esc_url($viteDevServerUrl); ?>/@react-refresh'
                RefreshRuntime.injectIntoGlobalHook(window)
                window.$RefreshReg$ = () => {}
                window.$RefreshSig$ = () => (type) => type
                window.__vite_plugin_react_preamble_installed__ = true
            </script>
            <script type="module" src="<?php echo esc_url($viteDevServerUrl); ?>/@vite/client"></script>
            <script type="module" src="<?php echo esc_url($viteDevServerUrl); ?>/main.jsx"></script>
            <?php
        });

        // Register empty script for wp_localize_script compatibility
        wp_register_script('wpsms-new-settings', '', [], null, true);
        wp_enqueue_script('wpsms-new-settings');
    }

    /**
     * Mask sensitive fields in settings array
     *
     * Replaces actual values with masked placeholder to prevent
     * sensitive data from being exposed in the frontend
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
                // Mark as having a value but don't expose the actual value
                $settings[$field] = '••••••••';
            }
        }

        return $settings;
    }

    /**
     * Get localized data for the React app
     *
     * @return array
     */
    private function getLocalizedData()
    {
        return [
            'apiUrl'           => rest_url('wpsms/v1/'),
            'nonce'            => wp_create_nonce('wp_rest'),
            'settings'         => $this->maskSensitiveSettings(Option::getOptions()),
            'proSettings'      => $this->maskSensitiveSettings(Option::getOptions(true)),
            'addons'           => $this->getActiveAddons(),
            'gateways'         => Gateway::gateway(),
            'gateway'          => $this->getGatewayCapabilities(),
            'adminUrl'         => admin_url(),
            'siteUrl'          => site_url(),
            'version'          => WP_SMS_VERSION,
            'i18n'             => $this->getTranslations(),
            // Dynamic data for multi-select fields
            'countries'        => $this->getCountries(),
            'postTypes'        => $this->getPostTypes(),
            'taxonomies'       => $this->getTaxonomiesWithTerms(),
            'roles'            => $this->getUserRoles(),
            'groups'           => $this->getNewsletterGroups(),
            // Add-on settings schema for dynamic rendering
            'addonSettings'    => $this->getAddonSettingsSchema(),
        ];
    }

    /**
     * Allowed field types for add-on settings
     *
     * @var array
     */
    private $allowedFieldTypes = [
        'text',
        'textarea',
        'number',
        'select',
        'multi-select',
        'switch',
        'checkbox',
        'repeater',
        'password',
    ];

    /**
     * Allowed target pages for add-on settings
     *
     * @var array
     */
    private $allowedPages = [
        'overview',
        'gateway',
        'phone',
        'message-button',
        'notifications',
        'newsletter',
        'integrations',
        'advanced',
    ];

    /**
     * Get add-on settings schema from filter
     *
     * Allows add-ons to register their settings via the wpsms_addon_settings_schema filter.
     * The schema defines fields, sections, and their locations in the settings UI.
     *
     * @return array Validated add-on settings schemas
     */
    private function getAddonSettingsSchema()
    {
        /**
         * Filter to register add-on settings schema
         *
         * Add-ons can use this filter to register their settings fields for the React settings page.
         *
         * @param array $schemas Empty array to be populated by add-ons
         *
         * @example
         * add_filter('wpsms_addon_settings_schema', function($schemas) {
         *     $schemas['my-addon'] = [
         *         'name' => 'My Add-on',
         *         'version' => '1.0.0',
         *         'sections' => [...],
         *         'fields' => [...],
         *     ];
         *     return $schemas;
         * });
         */
        $schemas = apply_filters('wpsms_addon_settings_schema', []);

        return $this->validateAddonSchemas($schemas);
    }

    /**
     * Validate and sanitize add-on schemas
     *
     * Ensures all add-on schemas follow the expected format and removes invalid entries.
     *
     * @param array $schemas Raw schemas from filter
     * @return array Validated and sanitized schemas
     */
    private function validateAddonSchemas($schemas)
    {
        if (!is_array($schemas)) {
            return [];
        }

        $validated = [];

        foreach ($schemas as $addonSlug => $schema) {
            if (!is_array($schema) || empty($schema['fields'])) {
                continue;
            }

            $validatedFields = [];
            foreach ($schema['fields'] as $field) {
                $validatedField = $this->validateAddonField($field);
                if ($validatedField) {
                    $validatedFields[] = $validatedField;
                }
            }

            if (empty($validatedFields)) {
                continue;
            }

            $validatedSections = [];
            if (!empty($schema['sections']) && is_array($schema['sections'])) {
                foreach ($schema['sections'] as $section) {
                    $validatedSection = $this->validateAddonSection($section);
                    if ($validatedSection) {
                        $validatedSections[] = $validatedSection;
                    }
                }
            }

            $validated[sanitize_key($addonSlug)] = [
                'name'     => sanitize_text_field($schema['name'] ?? $addonSlug),
                'version'  => sanitize_text_field($schema['version'] ?? '1.0.0'),
                'sections' => $validatedSections,
                'fields'   => $validatedFields,
                'data'     => $schema['data'] ?? [],
            ];
        }

        return $validated;
    }

    /**
     * Validate a single add-on field
     *
     * @param array $field Field configuration
     * @return array|null Validated field or null if invalid
     */
    private function validateAddonField($field)
    {
        // Required properties
        if (empty($field['id']) || empty($field['type']) || empty($field['target'])) {
            return null;
        }

        // Validate field type
        if (!in_array($field['type'], $this->allowedFieldTypes, true)) {
            return null;
        }

        // Validate target page
        if (empty($field['target']['page']) || !in_array($field['target']['page'], $this->allowedPages, true)) {
            return null;
        }

        // Build validated field
        $validated = [
            'id'          => sanitize_key($field['id']),
            'type'        => $field['type'],
            'label'       => sanitize_text_field($field['label'] ?? ''),
            'description' => wp_kses_post($field['description'] ?? ''),
            'default'     => $field['default'] ?? null,
            'isPro'       => !empty($field['isPro']),
            'target'      => [
                'page'     => $field['target']['page'],
                'section'  => sanitize_key($field['target']['section'] ?? ''),
                'priority' => absint($field['target']['priority'] ?? 100),
            ],
        ];

        // Optional properties
        if (!empty($field['placeholder'])) {
            $validated['placeholder'] = sanitize_text_field($field['placeholder']);
        }

        if (!empty($field['required'])) {
            $validated['required'] = true;
        }

        if (!empty($field['disabled'])) {
            $validated['disabled'] = true;
        }

        // Type-specific properties
        if (!empty($field['options']) && is_array($field['options'])) {
            $validated['options'] = array_map(function ($option) {
                return [
                    'value' => sanitize_text_field($option['value'] ?? ''),
                    'label' => sanitize_text_field($option['label'] ?? ''),
                ];
            }, $field['options']);
        }

        if (!empty($field['rows']) && $field['type'] === 'textarea') {
            $validated['rows'] = absint($field['rows']);
        }

        if (!empty($field['fields']) && $field['type'] === 'repeater') {
            $validated['fields'] = $this->validateRepeaterFields($field['fields']);
        }

        if (!empty($field['maxItems']) && $field['type'] === 'repeater') {
            $validated['maxItems'] = absint($field['maxItems']);
        }

        if (!empty($field['addLabel'])) {
            $validated['addLabel'] = sanitize_text_field($field['addLabel']);
        }

        // Conditional display
        if (!empty($field['conditions']) && is_array($field['conditions'])) {
            $validated['conditions'] = $this->validateConditions($field['conditions']);
        }

        // Validation rules
        if (!empty($field['validation']) && is_array($field['validation'])) {
            $validated['validation'] = $this->validateValidationRules($field['validation']);
        }

        return $validated;
    }

    /**
     * Validate add-on section
     *
     * @param array $section Section configuration
     * @return array|null Validated section or null if invalid
     */
    private function validateAddonSection($section)
    {
        if (empty($section['id']) || empty($section['title']) || empty($section['page'])) {
            return null;
        }

        if (!in_array($section['page'], $this->allowedPages, true)) {
            return null;
        }

        return [
            'id'          => sanitize_key($section['id']),
            'title'       => sanitize_text_field($section['title']),
            'description' => wp_kses_post($section['description'] ?? ''),
            'icon'        => sanitize_text_field($section['icon'] ?? ''),
            'page'        => $section['page'],
            'priority'    => absint($section['priority'] ?? 100),
        ];
    }

    /**
     * Validate repeater sub-fields
     *
     * @param array $fields Repeater field definitions
     * @return array Validated fields
     */
    private function validateRepeaterFields($fields)
    {
        $validated = [];
        foreach ($fields as $field) {
            if (empty($field['name']) || empty($field['type'])) {
                continue;
            }
            $validated[] = [
                'name'        => sanitize_key($field['name']),
                'label'       => sanitize_text_field($field['label'] ?? ''),
                'type'        => sanitize_text_field($field['type']),
                'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
            ];
        }
        return $validated;
    }

    /**
     * Validate conditional display rules
     *
     * @param array $conditions Condition definitions
     * @return array Validated conditions
     */
    private function validateConditions($conditions)
    {
        $allowedOperators = ['==', '!=', 'contains', 'empty', 'notEmpty'];
        $validated = [];

        foreach ($conditions as $condition) {
            if (empty($condition['field'])) {
                continue;
            }

            $operator = $condition['operator'] ?? '==';
            if (!in_array($operator, $allowedOperators, true)) {
                $operator = '==';
            }

            $validated[] = [
                'field'    => sanitize_key($condition['field']),
                'operator' => $operator,
                'value'    => $condition['value'] ?? null,
            ];
        }

        return $validated;
    }

    /**
     * Validate validation rules
     *
     * @param array $rules Validation rule definitions
     * @return array Validated rules
     */
    private function validateValidationRules($rules)
    {
        $validated = [];
        $allowedRules = ['required', 'minLength', 'maxLength', 'min', 'max', 'pattern', 'type'];

        foreach ($allowedRules as $rule) {
            if (isset($rules[$rule])) {
                if (in_array($rule, ['minLength', 'maxLength', 'min', 'max'], true)) {
                    $validated[$rule] = is_numeric($rules[$rule]) ? floatval($rules[$rule]) : null;
                } elseif ($rule === 'required') {
                    $validated[$rule] = (bool) $rules[$rule];
                } elseif ($rule === 'pattern' || $rule === 'type') {
                    $validated[$rule] = sanitize_text_field($rules[$rule]);
                }
            }
        }

        return array_filter($validated, function ($v) {
            return $v !== null;
        });
    }

    /**
     * Get active gateway capabilities
     *
     * Retrieves the current gateway's properties like flash SMS support,
     * media support, bulk send capability, and validation requirements.
     *
     * @return array Gateway capabilities
     */
    private function getGatewayCapabilities()
    {
        try {
            global $sms;

            // Initialize gateway if not already done
            if (!$sms || !is_object($sms)) {
                if (function_exists('wp_sms_initial_gateway')) {
                    $sms = wp_sms_initial_gateway();
                }
            }

            if (!$sms || !is_object($sms)) {
                return [
                    'flash'          => '',
                    'supportMedia'   => false,
                    'bulk_send'      => false,
                    'validateNumber' => '',
                    'from'           => '',
                    'gatewayFields'  => [],
                    'help'           => '',
                    'documentUrl'    => '',
                ];
            }

            // Build gateway fields array with current values
            $gatewayFields = [];
            if (!empty($sms->gatewayFields) && is_array($sms->gatewayFields)) {
                foreach ($sms->gatewayFields as $key => $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $gatewayFields[$key] = [
                        'id'          => $field['id'] ?? '',
                        'name'        => $field['name'] ?? '',
                        'desc'        => $field['desc'] ?? '',
                        'placeholder' => $field['place_holder'] ?? '',
                        'type'        => $field['type'] ?? 'text',
                        'options'     => $field['options'] ?? [],
                    ];
                }
            }

            // Sanitize help text to ensure valid JSON encoding
            $help = '';
            if (!empty($sms->help) && $sms->help !== false) {
                $help = wp_kses_post($sms->help);
            }

            return [
                'flash'          => $sms->flash ?? '',
                'supportMedia'   => $sms->supportMedia ?? false,
                'bulk_send'      => $sms->bulk_send ?? false,
                'validateNumber' => $sms->validateNumber ?? '',
                'from'           => $sms->from ?? '',
                'gatewayFields'  => $gatewayFields,
                'help'           => $help,
                'documentUrl'    => is_string($sms->documentUrl ?? '') ? ($sms->documentUrl ?? '') : '',
            ];
        } catch (\Exception $e) {
            return [
                'flash'          => '',
                'supportMedia'   => false,
                'bulk_send'      => false,
                'validateNumber' => '',
                'from'           => '',
                'gatewayFields'  => [],
                'help'           => '',
                'documentUrl'    => '',
                'error'          => $e->getMessage(),
            ];
        }
    }

    /**
     * Get countries list
     *
     * @return array
     */
    private function getCountries()
    {
        if (function_exists('wp_sms_countries')) {
            return wp_sms_countries()->getCountries();
        }
        return [];
    }

    /**
     * Get post types with show_ui enabled
     *
     * @return array
     */
    private function getPostTypes()
    {
        $postTypes = get_post_types(['show_ui' => true], 'objects');
        $result = [];

        foreach ($postTypes as $postType) {
            $result[$postType->name] = $postType->label;
        }

        return $result;
    }

    /**
     * Get taxonomies with their terms
     *
     * @return array
     */
    private function getTaxonomiesWithTerms()
    {
        $taxonomies = get_taxonomies(['show_ui' => true], 'objects');
        $result = [];

        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy->name,
                'hide_empty' => false,
                'number'     => 100, // Limit for performance
            ]);

            if (!is_wp_error($terms) && !empty($terms)) {
                $termList = [];
                foreach ($terms as $term) {
                    $termList[$term->term_id] = $term->name;
                }

                $result[$taxonomy->name] = [
                    'label' => $taxonomy->label,
                    'terms' => $termList,
                ];
            }
        }

        return $result;
    }

    /**
     * Get WordPress user roles
     *
     * @return array
     */
    private function getUserRoles()
    {
        $roles = wp_roles()->get_names();
        return $roles;
    }

    /**
     * Get newsletter subscriber groups
     *
     * @return array
     */
    private function getNewsletterGroups()
    {
        if (class_exists('WP_SMS\\Newsletter')) {
            $groups = Newsletter::getGroups();
            if (is_array($groups)) {
                $result = [];
                foreach ($groups as $group) {
                    if (isset($group->ID) && isset($group->name)) {
                        $result[$group->ID] = $group->name;
                    }
                }
                return $result;
            }
        }
        return [];
    }

    /**
     * Get active add-ons
     *
     * @return array
     */
    private function getActiveAddons()
    {
        $addons = [
            'pro'         => 'wp-sms-pro/wp-sms-pro.php',
            'woocommerce' => 'wp-sms-woocommerce-pro/wp-sms-woocommerce-pro.php',
            'two-way'     => 'wp-sms-two-way/wp-sms-two-way.php',
            'elementor'   => 'wp-sms-elementor-form/wp-sms-elementor-form.php',
            'membership'  => 'wp-sms-membership-integrations/wp-sms-membership-integrations.php',
            'booking'     => 'wp-sms-booking-integrations/wp-sms-booking-integrations.php',
            'fluent'      => 'wp-sms-fluent-integrations/wp-sms-fluent-integrations.php',
            'otp'         => 'wp-sms-otp-mfa/wp-sms-otp-mfa.php',
        ];

        $active = [];
        foreach ($addons as $key => $plugin) {
            $active[$key] = is_plugin_active($plugin);
        }

        return $active;
    }

    /**
     * Get translations for the React app
     *
     * @return array
     */
    private function getTranslations()
    {
        return [
            'save'           => __('Save Changes', 'wp-sms'),
            'saving'         => __('Saving...', 'wp-sms'),
            'saved'          => __('Changes saved', 'wp-sms'),
            'error'          => __('Error saving changes', 'wp-sms'),
            'unsavedChanges' => __('You have unsaved changes', 'wp-sms'),
            'discard'        => __('Discard', 'wp-sms'),
            'cancel'         => __('Cancel', 'wp-sms'),
            'confirm'        => __('Confirm', 'wp-sms'),
            'loading'        => __('Loading...', 'wp-sms'),
            'search'         => __('Search...', 'wp-sms'),
            'noResults'      => __('No results found', 'wp-sms'),
            'required'       => __('This field is required', 'wp-sms'),
            'invalid'        => __('Invalid value', 'wp-sms'),
        ];
    }
}
