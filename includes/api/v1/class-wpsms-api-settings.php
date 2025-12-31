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
            'settings'    => $settings,
            'proSettings' => $proSettings,
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

        // Update main settings
        if (isset($data['settings']) && is_array($data['settings'])) {
            $currentSettings = Option::getOptions();
            $sanitizedSettings = $this->sanitizeSettings($data['settings']);
            $mergedSettings = array_merge($currentSettings, $sanitizedSettings);
            update_option('wpsms_settings', $mergedSettings);
        }

        // Update pro settings
        if (isset($data['proSettings']) && is_array($data['proSettings'])) {
            $currentProSettings = Option::getOptions(true);
            $sanitizedProSettings = $this->sanitizeSettings($data['proSettings']);
            $mergedProSettings = array_merge($currentProSettings, $sanitizedProSettings);
            update_option('wps_pp_settings', $mergedProSettings);
        }

        // Clear any cached settings
        wp_cache_delete('wpsms_settings', 'options');
        wp_cache_delete('wps_pp_settings', 'options');

        return self::response(__('Settings saved successfully', 'wp-sms'), 200, [
            'settings'    => Option::getOptions(),
            'proSettings' => Option::getOptions(true),
        ]);
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
                return self::response($credit->get_error_message(), 400);
            }

            return self::response(__('Gateway connection successful', 'wp-sms'), 200, [
                'credit'  => $credit,
                'gateway' => Option::getOption('gateway_name'),
            ]);
        } catch (\Exception $e) {
            return self::response($e->getMessage(), 500);
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

        foreach ($settings as $key => $value) {
            $key = sanitize_key($key);

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeSettings($value);
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            } elseif (is_numeric($value)) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }
}

new SettingsApi();
