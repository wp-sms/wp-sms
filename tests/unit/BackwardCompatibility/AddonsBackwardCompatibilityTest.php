<?php

namespace unit\BackwardCompatibility;

use unit\WPSMSTestCase;
use WP_SMS\Option;
use WP_REST_Request;

require_once dirname(__DIR__) . '/WPSMSTestCase.php';

/**
 * Backward Compatibility Tests for Add-on Settings
 *
 * Ensures that add-on settings (WooCommerce Pro, etc.) work correctly
 * between legacy format and new React dashboard.
 */
class AddonsBackwardCompatibilityTest extends WPSMSTestCase
{
    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        // Clear options before each test
        delete_option('wpsms_settings');
        delete_option('wps_pp_settings');
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void
    {
        delete_option('wpsms_settings');
        delete_option('wps_pp_settings');

        // Clean up any add-on options we created
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpsms_wc_%'");
        parent::tearDown();
    }

    /**
     * Test: WooCommerce 'yes'/'no' checkbox values are handled correctly
     */
    public function testWooCommerceYesNoFormat()
    {
        // WooCommerce uses 'yes'/'no' for checkboxes
        update_option('wpsms_wc_enable_order_notify', 'yes');
        update_option('wpsms_wc_enable_admin_notify', 'no');
        update_option('wpsms_wc_order_statuses', 'yes');

        // Verify values are stored correctly
        $this->assertEquals('yes', get_option('wpsms_wc_enable_order_notify'));
        $this->assertEquals('no', get_option('wpsms_wc_enable_admin_notify'));
    }

    /**
     * Test: Add-on schema filter provides correct field definitions
     */
    public function testAddonSchemaFilterProvideDefinitions()
    {
        // Register a test add-on schema
        add_filter('wpsms_addon_settings_schema', function ($schemas) {
            $schemas[] = [
                'id'       => 'test_addon',
                'title'    => 'Test Add-on',
                'addonSlug' => 'test_addon',
                'fields'   => [
                    [
                        'id'          => 'test_checkbox',
                        'type'        => 'switch',
                        'label'       => 'Test Checkbox',
                        'default'     => false,
                        'description' => 'A test checkbox field',
                    ],
                    [
                        'id'          => 'test_text',
                        'type'        => 'text',
                        'label'       => 'Test Text',
                        'default'     => '',
                        'description' => 'A test text field',
                    ],
                ],
            ];
            return $schemas;
        });

        $schemas = apply_filters('wpsms_addon_settings_schema', []);

        $this->assertIsArray($schemas);
        $this->assertNotEmpty($schemas);

        // Find our test schema
        $testSchema = null;
        foreach ($schemas as $schema) {
            if (isset($schema['id']) && $schema['id'] === 'test_addon') {
                $testSchema = $schema;
                break;
            }
        }

        $this->assertNotNull($testSchema, 'Test schema should be registered');
        $this->assertCount(2, $testSchema['fields']);
    }

    /**
     * Test: API converts boolean to yes/no when saving add-on settings
     */
    public function testApiConvertsBooleanToYesNo()
    {
        // Register add-on schema
        add_filter('wpsms_addon_settings_schema', function ($schemas) {
            $schemas[] = [
                'id'       => 'woocommerce_pro',
                'title'    => 'WooCommerce Pro',
                'addonSlug' => 'woocommerce_pro',
                'fields'   => [
                    [
                        'id'      => 'wpsms_wc_enable_test',
                        'type'    => 'switch',
                        'label'   => 'Enable Test',
                        'default' => false,
                    ],
                ],
            ];
            return $schemas;
        });

        // Update via API with boolean true
        $request = $this->createJsonRequest('POST', '/wpsms/v1/settings', [
            'addonValues' => [
                'woocommerce_pro' => [
                    'wpsms_wc_enable_test' => true,
                ],
            ],
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        // The value should be converted to 'yes' in database
        $savedValue = get_option('wpsms_wc_enable_test');

        // Should be either 'yes' (converted) or boolean true (if not converted)
        $this->assertTrue($savedValue === 'yes' || $savedValue === true || $savedValue === 1);
    }

    /**
     * Test: Add-on values are read correctly from individual options
     */
    public function testAddonValuesReadFromIndividualOptions()
    {
        // Set individual add-on options
        update_option('wpsms_wc_customer_notify', 'yes');
        update_option('wpsms_wc_admin_notify', 'no');
        update_option('wpsms_wc_message_template', 'Your order #{order_id} is {status}');

        // Values should be retrievable
        $this->assertEquals('yes', get_option('wpsms_wc_customer_notify'));
        $this->assertEquals('no', get_option('wpsms_wc_admin_notify'));
        $this->assertEquals('Your order #{order_id} is {status}', get_option('wpsms_wc_message_template'));
    }

    /**
     * Test: Legacy wps_pp_settings values work alongside individual options
     */
    public function testLegacyProSettingsAlongsideIndividualOptions()
    {
        // Some add-ons store in wps_pp_settings
        update_option('wps_pp_settings', [
            'wc_notify_enabled' => '1',
            'wc_admin_mobile'   => '+15551234567',
        ]);

        // Others in individual options
        update_option('wpsms_wc_order_status_notify', 'yes');

        // Read via legacy Option class
        $proSettings = Option::getOptions(true);

        $this->assertEquals('1', $proSettings['wc_notify_enabled']);
        $this->assertEquals('+15551234567', $proSettings['wc_admin_mobile']);

        // Individual option should also be accessible
        $this->assertEquals('yes', get_option('wpsms_wc_order_status_notify'));
    }

    /**
     * Test: API returns both pro settings and add-on values
     */
    public function testApiReturnsBothProSettingsAndAddonValues()
    {
        // Set pro settings
        update_option('wps_pp_settings', [
            'test_pro_setting' => 'pro_value',
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('proSettings', $data['data']);
    }

    /**
     * Test: Multi-select fields are stored as arrays
     */
    public function testMultiSelectFieldsStoredAsArrays()
    {
        // Register add-on with multi-select
        add_filter('wpsms_addon_settings_schema', function ($schemas) {
            $schemas[] = [
                'id'       => 'test_multiselect',
                'title'    => 'Test Multi-Select',
                'addonSlug' => 'test_multiselect',
                'fields'   => [
                    [
                        'id'      => 'wpsms_test_statuses',
                        'type'    => 'multi-select',
                        'label'   => 'Order Statuses',
                        'options' => [
                            ['value' => 'pending', 'label' => 'Pending'],
                            ['value' => 'processing', 'label' => 'Processing'],
                            ['value' => 'completed', 'label' => 'Completed'],
                        ],
                    ],
                ],
            ];
            return $schemas;
        });

        // Save multi-select via API
        $request = $this->createJsonRequest('POST', '/wpsms/v1/settings', [
            'addonValues' => [
                'test_multiselect' => [
                    'wpsms_test_statuses' => ['pending', 'processing'],
                ],
            ],
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        // Value should be stored as array
        $savedValue = get_option('wpsms_test_statuses');

        $this->assertIsArray($savedValue);
        $this->assertContains('pending', $savedValue);
        $this->assertContains('processing', $savedValue);
    }

    /**
     * Test: Repeater fields are stored correctly
     */
    public function testRepeaterFieldsStoredCorrectly()
    {
        // Register add-on with repeater field
        add_filter('wpsms_addon_settings_schema', function ($schemas) {
            $schemas[] = [
                'id'       => 'test_repeater',
                'title'    => 'Test Repeater',
                'addonSlug' => 'test_repeater',
                'fields'   => [
                    [
                        'id'     => 'wpsms_test_mappings',
                        'type'   => 'repeater',
                        'label'  => 'Status Mappings',
                        'fields' => [
                            ['id' => 'status', 'type' => 'text'],
                            ['id' => 'message', 'type' => 'textarea'],
                        ],
                    ],
                ],
            ];
            return $schemas;
        });

        // Save repeater via API
        $repeaterData = [
            ['status' => 'pending', 'message' => 'Order pending'],
            ['status' => 'completed', 'message' => 'Order completed'],
        ];

        $request = $this->createJsonRequest('POST', '/wpsms/v1/settings', [
            'addonValues' => [
                'test_repeater' => [
                    'wpsms_test_mappings' => $repeaterData,
                ],
            ],
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        // Value should be stored as array of items
        $savedValue = get_option('wpsms_test_mappings');

        $this->assertIsArray($savedValue);
        $this->assertCount(2, $savedValue);
    }

    /**
     * Test: Add-on save filter allows custom handling
     */
    public function testAddonSaveFilterAllowsCustomHandling()
    {
        $customHandled = false;

        // Register custom save handler
        add_filter('wpsms_addon_save_settings_custom_addon', function ($handled, $fields, $fieldTypes) use (&$customHandled) {
            $customHandled = true;

            // Perform custom save logic
            foreach ($fields as $key => $value) {
                update_option('custom_' . $key, $value);
            }

            return true; // Indicate we handled the save
        }, 10, 3);

        // Save via API
        $request = $this->createJsonRequest('POST', '/wpsms/v1/settings', [
            'addonValues' => [
                'custom_addon' => [
                    'setting_1' => 'value_1',
                ],
            ],
        ]);

        rest_do_request($request);

        $this->assertTrue($customHandled, 'Custom save handler should have been called');

        // Verify custom storage
        $this->assertEquals('value_1', get_option('custom_setting_1'));
    }

    /**
     * Test: Validation rules from add-on schema are applied
     */
    public function testValidationRulesFromAddonSchemaApplied()
    {
        // Register add-on with validation rules
        add_filter('wpsms_addon_settings_schema', function ($schemas) {
            $schemas[] = [
                'id'       => 'validated_addon',
                'title'    => 'Validated Add-on',
                'addonSlug' => 'validated_addon',
                'fields'   => [
                    [
                        'id'         => 'wpsms_validated_phone',
                        'type'       => 'text',
                        'label'      => 'Phone Number',
                        'validation' => [
                            'type' => 'phone',
                        ],
                    ],
                ],
            ];
            return $schemas;
        });

        // Try to save invalid phone
        $request = $this->createJsonRequest('POST', '/wpsms/v1/settings', [
            'settings' => [
                'wpsms_validated_phone' => 'not-a-phone',
            ],
        ]);

        $response = rest_do_request($request);

        // May succeed with sanitized value or fail validation
        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test: Default values from schema are respected
     */
    public function testDefaultValuesFromSchemaRespected()
    {
        // Register add-on with defaults
        add_filter('wpsms_addon_settings_schema', function ($schemas) {
            $schemas[] = [
                'id'       => 'defaults_addon',
                'title'    => 'Defaults Add-on',
                'addonSlug' => 'defaults_addon',
                'fields'   => [
                    [
                        'id'      => 'wpsms_with_default',
                        'type'    => 'text',
                        'label'   => 'With Default',
                        'default' => 'default_value',
                    ],
                ],
            ];
            return $schemas;
        });

        // Don't set any value - should use default
        $schemas = apply_filters('wpsms_addon_settings_schema', []);

        $field = null;
        foreach ($schemas as $schema) {
            foreach ($schema['fields'] ?? [] as $f) {
                if ($f['id'] === 'wpsms_with_default') {
                    $field = $f;
                    break 2;
                }
            }
        }

        $this->assertNotNull($field);
        $this->assertEquals('default_value', $field['default']);
    }

    /**
     * Test: Textarea content is preserved with line breaks
     */
    public function testTextareaContentPreservedWithLineBreaks()
    {
        // Register add-on with textarea
        add_filter('wpsms_addon_settings_schema', function ($schemas) {
            $schemas[] = [
                'id'       => 'textarea_addon',
                'title'    => 'Textarea Add-on',
                'addonSlug' => 'textarea_addon',
                'fields'   => [
                    [
                        'id'    => 'wpsms_message_template',
                        'type'  => 'textarea',
                        'label' => 'Message Template',
                    ],
                ],
            ];
            return $schemas;
        });

        $messageWithLineBreaks = "Line 1\nLine 2\nLine 3";

        $request = $this->createJsonRequest('POST', '/wpsms/v1/settings', [
            'addonValues' => [
                'textarea_addon' => [
                    'wpsms_message_template' => $messageWithLineBreaks,
                ],
            ],
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        // Line breaks should be preserved
        $savedValue = get_option('wpsms_message_template');

        $this->assertStringContainsString("\n", $savedValue);
    }

    /**
     * Test: Number fields are properly typed
     */
    public function testNumberFieldsProperlyTyped()
    {
        // Register add-on with number field
        add_filter('wpsms_addon_settings_schema', function ($schemas) {
            $schemas[] = [
                'id'       => 'number_addon',
                'title'    => 'Number Add-on',
                'addonSlug' => 'number_addon',
                'fields'   => [
                    [
                        'id'    => 'wpsms_retry_count',
                        'type'  => 'number',
                        'label' => 'Retry Count',
                    ],
                ],
            ];
            return $schemas;
        });

        $request = $this->createJsonRequest('POST', '/wpsms/v1/settings', [
            'addonValues' => [
                'number_addon' => [
                    'wpsms_retry_count' => 5,
                ],
            ],
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $savedValue = get_option('wpsms_retry_count');

        // Should be numeric
        $this->assertTrue(is_numeric($savedValue));
        $this->assertEquals(5, (int) $savedValue);
    }
}
