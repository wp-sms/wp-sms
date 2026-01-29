<?php

namespace unit\BackwardCompatibility;

use unit\WPSMSTestCase;
use WP_SMS\Option;
use WP_SMS\Pro\Admin\ReactSettings\ProSettingsSchema;

require_once dirname(__DIR__) . '/WPSMSTestCase.php';

// Load Pro plugin class (not autoloaded by main plugin)
$proSchemaPath = dirname(__DIR__, 4) . '/wp-sms-pro/src/Admin/ReactSettings/ProSettingsSchema.php';
if (file_exists($proSchemaPath)) {
    require_once $proSchemaPath;
}

/**
 * Backward Compatibility Tests for WP SMS Pro Settings
 *
 * Ensures that Pro integration settings correctly read/write from the
 * wps_pp_settings serialized array (legacy format) when using the
 * React dashboard interface.
 */
class ProSettingsBackwardCompatibilityTest extends WPSMSTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        if (!defined('WP_SMS_PRO_VERSION')) {
            define('WP_SMS_PRO_VERSION', '4.0.0-test');
        }
        delete_option('wps_pp_settings');
    }

    public function tearDown(): void
    {
        delete_option('wps_pp_settings');
        remove_all_filters('wpsms_addon_save_settings_wp-sms-pro');
        remove_all_filters('wpsms_addon_settings_schema');
        parent::tearDown();
    }

    /**
     * Test: handleSave writes to wps_pp_settings array, not individual options
     */
    public function testHandleSaveMergesIntoProSettingsArray()
    {
        // Pre-existing legacy settings
        update_option('wps_pp_settings', [
            'existing_key' => 'existing_value',
            'login_sms'    => 'enable',
        ]);

        $fields = [
            'wc_meta_box_enable'       => true,
            'wc_notify_order_receiver' => '+15551234567',
        ];

        $fieldTypes = [
            'wc_meta_box_enable'       => 'switch',
            'wc_notify_order_receiver' => 'text',
        ];

        $result = ProSettingsSchema::handleSave(false, $fields, $fieldTypes);

        $this->assertTrue($result, 'handleSave should return true');

        $saved = get_option('wps_pp_settings');

        // New fields saved
        $this->assertEquals('enable', $saved['wc_meta_box_enable']);
        $this->assertEquals('+15551234567', $saved['wc_notify_order_receiver']);

        // Pre-existing fields preserved
        $this->assertEquals('existing_value', $saved['existing_key']);
        $this->assertEquals('enable', $saved['login_sms']);
    }

    /**
     * Test: handleSave converts boolean true to 'enable' for switch fields
     */
    public function testHandleSaveConvertsTrueToEnable()
    {
        update_option('wps_pp_settings', []);

        ProSettingsSchema::handleSave(false, ['login_sms' => true], ['login_sms' => 'switch']);

        $saved = get_option('wps_pp_settings');
        $this->assertEquals('enable', $saved['login_sms']);
    }

    /**
     * Test: handleSave converts boolean false to 'disable' for switch fields
     */
    public function testHandleSaveConvertsFalseToDisable()
    {
        update_option('wps_pp_settings', []);

        ProSettingsSchema::handleSave(false, ['login_sms' => false], ['login_sms' => 'switch']);

        $saved = get_option('wps_pp_settings');
        $this->assertEquals('disable', $saved['login_sms']);
    }

    /**
     * Test: handleSave converts checkbox type the same as switch
     */
    public function testHandleSaveConvertsCheckboxType()
    {
        update_option('wps_pp_settings', []);

        ProSettingsSchema::handleSave(false, ['some_check' => true], ['some_check' => 'checkbox']);

        $saved = get_option('wps_pp_settings');
        $this->assertEquals('enable', $saved['some_check']);
    }

    /**
     * Test: handleSave leaves text fields as-is (no boolean conversion)
     */
    public function testHandleSaveLeavesTextFieldsAsIs()
    {
        update_option('wps_pp_settings', []);

        ProSettingsSchema::handleSave(
            false,
            ['short_url_api_token' => 'abc123token'],
            ['short_url_api_token' => 'text']
        );

        $saved = get_option('wps_pp_settings');
        $this->assertEquals('abc123token', $saved['short_url_api_token']);
    }

    /**
     * Test: handleSave defaults unknown field types to text (no conversion)
     */
    public function testHandleSaveDefaultsToTextForUnknownType()
    {
        update_option('wps_pp_settings', []);

        ProSettingsSchema::handleSave(
            false,
            ['some_field' => 'raw_value'],
            [] // no type mapping
        );

        $saved = get_option('wps_pp_settings');
        $this->assertEquals('raw_value', $saved['some_field']);
    }

    /**
     * Test: Round-trip — legacy 'enable' value loads as boolean true in React,
     * saving true writes back 'enable' to wps_pp_settings
     */
    public function testRoundTripEnableDisable()
    {
        // Legacy code sets a value
        update_option('wps_pp_settings', [
            'login_sms'     => 'enable',
            'register_sms'  => 'disable',
            'short_url_api_token' => 'my-token',
        ]);

        // Simulate React loading — getCurrentValues is private, so we test via schema
        $schema = null;
        add_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        remove_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);

        $this->assertArrayHasKey('wp-sms-pro', $schemas);
        $currentValues = $schemas['wp-sms-pro']['data']['currentValues'] ?? [];

        $this->assertTrue($currentValues['login_sms'], 'enable should load as true');
        $this->assertFalse($currentValues['register_sms'], 'disable should load as false');
        $this->assertEquals('my-token', $currentValues['short_url_api_token']);

        // Now simulate React saving back
        ProSettingsSchema::handleSave(
            false,
            ['login_sms' => true, 'register_sms' => false, 'short_url_api_token' => 'my-token'],
            ['login_sms' => 'switch', 'register_sms' => 'switch', 'short_url_api_token' => 'text']
        );

        // Legacy code should still see the correct values
        $proSettings = Option::getOptions(true);
        $this->assertEquals('enable', $proSettings['login_sms']);
        $this->assertEquals('disable', $proSettings['register_sms']);
        $this->assertEquals('my-token', $proSettings['short_url_api_token']);
    }

    /**
     * Test: getCurrentValues converts empty/null to false for switch fields
     */
    public function testCurrentValuesConvertsEmptyToFalse()
    {
        update_option('wps_pp_settings', [
            'login_sms' => '',
        ]);

        add_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        remove_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);

        $currentValues = $schemas['wp-sms-pro']['data']['currentValues'] ?? [];
        $this->assertFalse($currentValues['login_sms']);
    }

    /**
     * Test: getCurrentValues uses default when key is missing from wps_pp_settings
     */
    public function testCurrentValuesUsesDefaultForMissingKeys()
    {
        // Empty pro settings — no keys set
        update_option('wps_pp_settings', []);

        add_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        remove_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);

        $currentValues = $schemas['wp-sms-pro']['data']['currentValues'] ?? [];

        // Switch fields with default false should be false
        $this->assertFalse($currentValues['login_sms']);
        $this->assertFalse($currentValues['mobile_verify']);
        $this->assertFalse($currentValues['short_url_status']);
        $this->assertFalse($currentValues['g_recaptcha_status']);
    }

    /**
     * Test: The save filter is triggered via REST API with addonValues
     */
    public function testSaveFilterTriggeredViaRestApi()
    {
        ProSettingsSchema::init();

        update_option('wps_pp_settings', []);

        $request = $this->createJsonRequest('POST', '/wpsms/v1/settings', [
            'addonValues' => [
                'wp-sms-pro' => [
                    'login_sms'           => true,
                    'login_sms_message'   => 'Your code is %code%',
                    'short_url_api_token' => 'bitly-token-123',
                ],
            ],
        ]);

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());

        // Verify saved to wps_pp_settings
        $saved = get_option('wps_pp_settings');
        $this->assertEquals('enable', $saved['login_sms']);
        $this->assertEquals('Your code is %code%', $saved['login_sms_message']);
        $this->assertEquals('bitly-token-123', $saved['short_url_api_token']);
    }

    /**
     * Test: Schema registers on the wpsms_addon_settings_schema filter
     */
    public function testSchemaRegistersViaFilter()
    {
        add_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        remove_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);

        $this->assertArrayHasKey('wp-sms-pro', $schemas);
        $this->assertEquals('WP SMS Pro', $schemas['wp-sms-pro']['name']);
        $this->assertNotEmpty($schemas['wp-sms-pro']['sections']);
        $this->assertNotEmpty($schemas['wp-sms-pro']['fields']);
        $this->assertArrayHasKey('currentValues', $schemas['wp-sms-pro']['data']);
    }

    /**
     * Test: All advanced section field IDs are present in the schema
     */
    public function testAdvancedFieldIdsPresent()
    {
        add_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        remove_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);

        $fieldIds = array_column($schemas['wp-sms-pro']['fields'], 'id');

        $expectedIds = [
            // Login SMS
            'login_sms', 'login_sms_message', 'register_sms',
            // 2FA
            'mobile_verify', 'mobile_verify_method', 'mobile_verify_message',
            // URL Shortening
            'short_url_status', 'short_url_api_token',
            // reCAPTCHA
            'g_recaptcha_status', 'g_recaptcha_site_key', 'g_recaptcha_secret_key',
        ];

        foreach ($expectedIds as $id) {
            $this->assertContains($id, $fieldIds, "Field '$id' should be present in schema");
        }
    }

    /**
     * Test: handleSave preserves array/repeater values without conversion
     */
    public function testHandleSavePreservesArrayValues()
    {
        update_option('wps_pp_settings', []);

        $repeaterData = [
            ['order_status' => 'completed', 'notify_status' => '1', 'message' => 'Order done'],
            ['order_status' => 'processing', 'notify_status' => '1', 'message' => 'Processing'],
        ];

        ProSettingsSchema::handleSave(
            false,
            ['wc_notify_by_status_content' => $repeaterData],
            ['wc_notify_by_status_content' => 'repeater']
        );

        $saved = get_option('wps_pp_settings');
        $this->assertIsArray($saved['wc_notify_by_status_content']);
        $this->assertCount(2, $saved['wc_notify_by_status_content']);
        $this->assertEquals('completed', $saved['wc_notify_by_status_content'][0]['order_status']);
    }

    /**
     * Test: Multiple saves accumulate in wps_pp_settings without losing data
     */
    public function testMultipleSavesAccumulate()
    {
        update_option('wps_pp_settings', ['pre_existing' => 'value']);

        // First save
        ProSettingsSchema::handleSave(false, ['login_sms' => true], ['login_sms' => 'switch']);

        // Second save (different fields)
        ProSettingsSchema::handleSave(false, ['short_url_api_token' => 'token'], ['short_url_api_token' => 'text']);

        $saved = get_option('wps_pp_settings');
        $this->assertEquals('value', $saved['pre_existing']);
        $this->assertEquals('enable', $saved['login_sms']);
        $this->assertEquals('token', $saved['short_url_api_token']);
    }

    /**
     * Test: Per-status repeater round-trip preserves legacy field names
     *
     * Legacy integration code reads:
     *   $value['notify_status'] == '1'
     *   $value['order_status'] == $new_status
     *   $value['message']
     *
     * React must save with these exact keys so legacy code keeps working.
     */
    public function testPerStatusRepeaterRoundTrip()
    {
        // Legacy data as stored by the old UI
        $legacyData = [
            [
                'order_status'  => 'completed',
                'notify_status' => '1',
                'message'       => 'Hi %billing_first_name%, order #%order_number% is complete.',
            ],
            [
                'order_status'  => 'processing',
                'notify_status' => '2',
                'message'       => 'Order #%order_number% is processing.',
            ],
        ];

        update_option('wps_pp_settings', [
            'wc_notify_by_status_enable'  => 'enable',
            'wc_notify_by_status_content' => $legacyData,
        ]);

        // Simulate React loading via schema
        add_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        remove_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);

        $currentValues = $schemas['wp-sms-pro']['data']['currentValues'] ?? [];

        // Verify the repeater data loaded correctly
        $this->assertIsArray($currentValues['wc_notify_by_status_content']);
        $this->assertCount(2, $currentValues['wc_notify_by_status_content']);
        $this->assertEquals('completed', $currentValues['wc_notify_by_status_content'][0]['order_status']);
        $this->assertEquals('1', $currentValues['wc_notify_by_status_content'][0]['notify_status']);
        $this->assertEquals('2', $currentValues['wc_notify_by_status_content'][1]['notify_status']);

        // Simulate React saving (user edits message, adds a new row)
        $reactData = [
            [
                'order_status'  => 'completed',
                'notify_status' => '1',
                'message'       => 'Hi %billing_first_name%, your order is done!',
            ],
            [
                'order_status'  => 'processing',
                'notify_status' => '1',
                'message'       => 'Order #%order_number% is processing.',
            ],
            [
                'order_status'  => 'on-hold',
                'notify_status' => '1',
                'message'       => 'Order on hold.',
            ],
        ];

        ProSettingsSchema::handleSave(
            false,
            ['wc_notify_by_status_content' => $reactData],
            ['wc_notify_by_status_content' => 'repeater']
        );

        // Verify legacy code can still read the data correctly
        $proSettings = get_option('wps_pp_settings');
        $content = $proSettings['wc_notify_by_status_content'];

        $this->assertCount(3, $content);

        // Legacy code checks: $value['notify_status'] == '1' && $value['order_status'] == $new_status
        $this->assertEquals('1', $content[0]['notify_status'], 'notify_status must be string "1" for legacy code');
        $this->assertEquals('completed', $content[0]['order_status'], 'order_status key must match legacy format');
        $this->assertArrayHasKey('message', $content[0], 'message key must exist');

        // Verify second row was changed from disabled to enabled
        $this->assertEquals('1', $content[1]['notify_status']);
        $this->assertEquals('processing', $content[1]['order_status']);

        // Verify new row was added
        $this->assertEquals('on-hold', $content[2]['order_status']);
        $this->assertEquals('1', $content[2]['notify_status']);
    }

    /**
     * Test: Per-status repeater with empty data doesn't break
     */
    public function testPerStatusRepeaterEmptyData()
    {
        update_option('wps_pp_settings', [
            'wc_notify_by_status_enable'  => 'enable',
            'wc_notify_by_status_content' => [],
        ]);

        ProSettingsSchema::handleSave(
            false,
            ['wc_notify_by_status_content' => []],
            ['wc_notify_by_status_content' => 'repeater']
        );

        $saved = get_option('wps_pp_settings');
        $this->assertIsArray($saved['wc_notify_by_status_content']);
        $this->assertCount(0, $saved['wc_notify_by_status_content']);
    }

    /**
     * Test: Data section includes currentValues key
     */
    public function testDataIncludesCurrentValues()
    {
        update_option('wps_pp_settings', ['login_sms' => 'enable']);

        add_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        remove_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);

        $data = $schemas['wp-sms-pro']['data'];
        $this->assertArrayHasKey('currentValues', $data);
        $this->assertArrayHasKey('hasLicense', $data);
        $this->assertArrayHasKey('integrations', $data);
    }
}
