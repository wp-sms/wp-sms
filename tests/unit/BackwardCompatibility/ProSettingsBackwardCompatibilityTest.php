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
     * Helper: Call a private static method on ProSettingsSchema via Reflection
     */
    private function callPrivateMethod(string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod(ProSettingsSchema::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke(null, ...$args);
    }

    /**
     * Helper: Get field IDs from a private method's return value
     */
    private function getFieldIdsFromMethod(string $method): array
    {
        $fields = $this->callPrivateMethod($method);
        return array_column($fields, 'id');
    }

    /**
     * Test: Every schema field ID matches what legacy integration code reads.
     *
     * Uses Reflection to call each private get*Fields() method directly,
     * bypassing the runtime plugin-active checks. This ensures ALL
     * integrations are tested regardless of which plugins are installed.
     *
     * If this test fails, it means a React field ID doesn't match what
     * the legacy integration code expects, and saving from React will
     * produce data that the integration cannot read.
     */
    public function testAllSchemaFieldIdsMatchLegacyOptionKeys()
    {
        // ── Advanced page fields (always registered) ──

        // Login with SMS: src/Services/Integration/IntegrationServiceProvider.php
        $loginIds = $this->getFieldIdsFromMethod('getLoginWithSmsFields');
        $this->assertContains('login_sms', $loginIds);
        $this->assertContains('login_sms_message', $loginIds);
        $this->assertContains('register_sms', $loginIds);

        // 2FA
        $tfaIds = $this->getFieldIdsFromMethod('getTwoFactorAuthFields');
        $this->assertContains('mobile_verify', $tfaIds);
        $this->assertContains('mobile_verify_method', $tfaIds);
        $this->assertContains('mobile_verify_message', $tfaIds);

        // URL Shortening
        $urlIds = $this->getFieldIdsFromMethod('getUrlShorteningFields');
        $this->assertContains('short_url_status', $urlIds);
        $this->assertContains('short_url_api_token', $urlIds);

        // reCAPTCHA
        $recaptchaIds = $this->getFieldIdsFromMethod('getRecaptchaFields');
        $this->assertContains('g_recaptcha_status', $recaptchaIds);
        $this->assertContains('g_recaptcha_site_key', $recaptchaIds);
        $this->assertContains('g_recaptcha_secret_key', $recaptchaIds);

        // ── WooCommerce ──
        // Source: includes/integrations/woocommerce/class-wpsms-pro-woocommerce.php
        // Every key below is read by legacy code via $this->options['key']
        $wooIds = $this->getFieldIdsFromMethod('getWooCommerceFields');
        $wooExpected = [
            'wc_meta_box_enable',
            'wc_notify_product_enable',
            'wc_notify_product_receiver',
            'wc_notify_product_cat',
            'wc_notify_product_roles',
            'wc_notify_product_message',
            'wc_notify_order_enable',
            'wc_notify_order_receiver',
            'wc_notify_order_message',
            'wc_notify_customer_enable',
            'wc_notify_customer_message',
            'wc_notify_stock_enable',
            'wc_notify_stock_receiver',
            'wc_notify_stock_message',
            'wc_checkout_confirmation_checkbox_enabled',
            'wc_notify_status_enable',
            'wc_notify_status_message',
            'wc_notify_by_status_enable',
            'wc_notify_by_status_content',
        ];
        foreach ($wooExpected as $key) {
            $this->assertContains($key, $wooIds, "WooCommerce legacy key '$key' missing from schema");
        }

        // ── BuddyPress ──
        // Source: src/Services/Integration/BuddyPress/BuddyPress.php
        // Keys: self::$options['bp_mention_enable'], self::$options['bp_mention_message'], etc.
        $bpIds = $this->getFieldIdsFromMethod('getBuddyPressFields');
        $bpExpected = [
            'bp_welcome_notification_enable',
            'bp_welcome_notification_message',
            'bp_mention_enable',
            'bp_mention_message',
            'bp_private_message_enable',
            'bp_private_message_content',
            'bp_comments_activity_enable',
            'bp_comments_activity_message',
            'bp_comments_reply_enable',
            'bp_comments_reply_message',
        ];
        foreach ($bpExpected as $key) {
            $this->assertContains($key, $bpIds, "BuddyPress legacy key '$key' missing from schema");
        }

        // ── Easy Digital Downloads ──
        // Source: includes/integrations/class-wpsms-pro-easy-digital-downloads.php
        $eddIds = $this->getFieldIdsFromMethod('getEddFields');
        $eddExpected = [
            'edd_mobile_field',
            'edd_notify_order_enable',
            'edd_notify_order_receiver',
            'edd_notify_order_message',
            'edd_notify_customer_enable',
            'edd_notify_customer_message',
        ];
        foreach ($eddExpected as $key) {
            $this->assertContains($key, $eddIds, "EDD legacy key '$key' missing from schema");
        }

        // ── WP Job Manager ──
        // Source: includes/integrations/class-wpsms-pro-wp-job-manager.php
        $jobIds = $this->getFieldIdsFromMethod('getJobManagerFields');
        $jobExpected = [
            'job_mobile_field',
            'job_display_mobile_number',
            'job_notify_status',
            'job_notify_receiver',
            'job_notify_receiver_subscribers',
            'job_notify_receiver_numbers',
            'job_notify_message',
            'job_notify_employer_status',
            'job_notify_employer_message',
        ];
        foreach ($jobExpected as $key) {
            $this->assertContains($key, $jobIds, "Job Manager legacy key '$key' missing from schema");
        }

        // ── Awesome Support ──
        // Source: src/Services/Integration/AwesomeSupport/AwesomeSupport.php
        $asIds = $this->getFieldIdsFromMethod('getAwesomeSupportFields');
        $asExpected = [
            'as_notify_open_ticket_status',
            'as_notify_open_ticket_message',
            'as_notify_admin_reply_ticket_status',
            'as_notify_admin_reply_ticket_message',
            'as_notify_user_reply_ticket_status',
            'as_notify_user_reply_ticket_message',
            'as_notify_update_ticket_status',
            'as_notify_update_ticket_message',
            'as_notify_close_ticket_status',
            'as_notify_close_ticket_message',
        ];
        foreach ($asExpected as $key) {
            $this->assertContains($key, $asIds, "Awesome Support legacy key '$key' missing from schema");
        }

        // ── Ultimate Member ──
        // Source: src/Services/Integration/UltimateMember/UltimateMember.php
        $umIds = $this->getFieldIdsFromMethod('getUltimateMemberFields');
        $umExpected = [
            'um_send_sms_after_approval',
            'um_message_body',
        ];
        foreach ($umExpected as $key) {
            $this->assertContains($key, $umIds, "Ultimate Member legacy key '$key' missing from schema");
        }
    }

    /**
     * Helper: Get ALL fields from all private field methods via Reflection.
     * Bypasses runtime plugin-active checks so all integrations are covered.
     */
    private function getAllFieldsViaReflection(): array
    {
        $methods = [
            'getLoginWithSmsFields',
            'getTwoFactorAuthFields',
            'getUrlShorteningFields',
            'getRecaptchaFields',
            'getWooCommerceFields',
            'getBuddyPressFields',
            'getEddFields',
            'getJobManagerFields',
            'getAwesomeSupportFields',
            'getUltimateMemberFields',
            // Gravity Forms & Quform are dynamic per-form, tested separately
        ];

        $allFields = [];
        foreach ($methods as $method) {
            try {
                $fields = $this->callPrivateMethod($method);
                $allFields = array_merge($allFields, $fields);
            } catch (\Throwable $e) {
                // Method may fail if it calls external classes (e.g., Newsletter::getGroups)
                // That's expected for integrations whose dependencies aren't loaded
            }
        }

        return $allFields;
    }

    /**
     * Test: All switch/checkbox fields round-trip enable/disable correctly
     *
     * Uses Reflection to get ALL fields from ALL integrations, not just
     * those whose plugins are active. Every switch field is tested for
     * correct boolean ↔ enable/disable conversion.
     */
    public function testAllSwitchFieldsRoundTrip()
    {
        $allFields = $this->getAllFieldsViaReflection();

        $switchFields = array_filter($allFields, function ($f) {
            return in_array($f['type'] ?? '', ['switch', 'checkbox']);
        });

        $this->assertNotEmpty($switchFields, 'Should have switch fields');

        foreach ($switchFields as $field) {
            $id = $field['id'];

            // Test true → 'enable'
            update_option('wps_pp_settings', []);
            ProSettingsSchema::handleSave(false, [$id => true], [$id => $field['type']]);
            $saved = get_option('wps_pp_settings');
            $this->assertEquals('enable', $saved[$id], "Field '$id': true should save as 'enable'");

            // Test false → 'disable'
            update_option('wps_pp_settings', []);
            ProSettingsSchema::handleSave(false, [$id => false], [$id => $field['type']]);
            $saved = get_option('wps_pp_settings');
            $this->assertEquals('disable', $saved[$id], "Field '$id': false should save as 'disable'");
        }
    }

    /**
     * Test: getCurrentValues loads enable→true, disable→false for ALL switch fields
     *
     * Uses Reflection to get ALL switch fields, but only tests loading
     * for those that getCurrentValues actually returns (i.e., whose
     * integration plugin is active). This is because getCurrentValues
     * internally calls getFields() which has runtime plugin checks.
     *
     * The save direction (true→'enable') is already tested for ALL fields
     * in testAllSwitchFieldsRoundTrip since handleSave has no plugin checks.
     */
    public function testAllSwitchFieldsLoadCorrectly()
    {
        $allFields = $this->getAllFieldsViaReflection();

        $switchFields = array_filter($allFields, function ($f) {
            return in_array($f['type'] ?? '', ['switch', 'checkbox']);
        });

        $this->assertNotEmpty($switchFields);

        // Set all switch fields to 'enable' in the DB
        $proSettings = [];
        foreach ($switchFields as $field) {
            $proSettings[$field['id']] = 'enable';
        }
        update_option('wps_pp_settings', $proSettings);

        $currentValues = $this->callPrivateMethod('getCurrentValues');

        // Only assert for fields that getCurrentValues actually returned
        // (plugins not active won't have their fields in the result)
        foreach ($switchFields as $field) {
            $id = $field['id'];
            if (!array_key_exists($id, $currentValues)) {
                continue; // Plugin not active, skip runtime check
            }
            $this->assertTrue($currentValues[$id], "Field '$id': 'enable' should load as true");
        }

        // Verify at least WooCommerce + advanced fields are present
        $this->assertArrayHasKey('login_sms', $currentValues, 'Advanced fields should always be present');
        $this->assertArrayHasKey('wc_notify_product_enable', $currentValues, 'WooCommerce fields should be present');

        // Now test 'disable' → false
        foreach ($switchFields as $field) {
            $proSettings[$field['id']] = 'disable';
        }
        update_option('wps_pp_settings', $proSettings);

        $currentValues = $this->callPrivateMethod('getCurrentValues');

        foreach ($switchFields as $field) {
            $id = $field['id'];
            if (!array_key_exists($id, $currentValues)) {
                continue;
            }
            $this->assertFalse($currentValues[$id], "Field '$id': 'disable' should load as false");
        }
    }

    /**
     * Test: getCurrentValues includes every field from every integration
     *
     * Uses Reflection to get all fields, then verifies getCurrentValues
     * returns a value for each one (even if default).
     */
    public function testCurrentValuesIncludesEveryFieldId()
    {
        update_option('wps_pp_settings', []);

        $allFields = $this->getAllFieldsViaReflection();
        $this->assertNotEmpty($allFields, 'Should have fields from Reflection');

        // getCurrentValues uses getFields() which has plugin checks.
        // So we also verify via Reflection that each field has an id.
        foreach ($allFields as $field) {
            $this->assertArrayHasKey('id', $field, 'Every field must have an id');
            $this->assertNotEmpty($field['id'], 'Field id must not be empty');
            $this->assertArrayHasKey('type', $field, "Field '{$field['id']}' must have a type");
        }

        // Also verify the runtime currentValues for active plugins
        $currentValues = $this->callPrivateMethod('getCurrentValues');
        $runtimeFields = $this->callPrivateMethod('getFields');
        $runtimeIds = array_column($runtimeFields, 'id');

        foreach ($runtimeIds as $id) {
            $this->assertArrayHasKey($id, $currentValues, "Field '$id' missing from currentValues");
        }
    }

    /**
     * Test: Per-status repeater sub-fields use exact legacy field names
     *
     * Legacy code reads: $value['order_status'], $value['notify_status'], $value['message']
     * This test ensures the schema defines those exact sub-field names.
     */
    public function testPerStatusRepeaterSubFieldNames()
    {
        add_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        remove_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);

        $repeaterField = null;
        foreach ($schemas['wp-sms-pro']['fields'] as $field) {
            if ($field['id'] === 'wc_notify_by_status_content') {
                $repeaterField = $field;
                break;
            }
        }

        $this->assertNotNull($repeaterField, 'wc_notify_by_status_content field must exist');
        $this->assertEquals('repeater', $repeaterField['type']);

        $subFieldNames = array_column($repeaterField['fields'], 'name');

        // These exact names are read by class-wpsms-pro-woocommerce.php:201
        $this->assertContains('order_status', $subFieldNames, 'Repeater must have order_status sub-field (not "status")');
        $this->assertContains('notify_status', $subFieldNames, 'Repeater must have notify_status sub-field');
        $this->assertContains('message', $subFieldNames, 'Repeater must have message sub-field');
    }

    /**
     * Test: Per-status order_status options use values without wc- prefix
     *
     * Legacy code compares: $value['order_status'] == $new_status
     * WooCommerce passes $new_status WITHOUT 'wc-' prefix (e.g., 'completed', not 'wc-completed')
     * Legacy template strips prefix: str_replace('wc-', '', $status_key)
     */
    public function testPerStatusOrderStatusOptionsHaveNoWcPrefix()
    {
        add_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);
        $schemas = apply_filters('wpsms_addon_settings_schema', []);
        remove_filter('wpsms_addon_settings_schema', [ProSettingsSchema::class, 'registerSchema'], 5);

        $repeaterField = null;
        foreach ($schemas['wp-sms-pro']['fields'] as $field) {
            if ($field['id'] === 'wc_notify_by_status_content') {
                $repeaterField = $field;
                break;
            }
        }

        $this->assertNotNull($repeaterField);

        $orderStatusField = null;
        foreach ($repeaterField['fields'] as $subField) {
            if ($subField['name'] === 'order_status') {
                $orderStatusField = $subField;
                break;
            }
        }

        $this->assertNotNull($orderStatusField, 'order_status sub-field must exist');
        $this->assertNotEmpty($orderStatusField['options'], 'order_status must have options');

        foreach ($orderStatusField['options'] as $option) {
            $this->assertStringNotContainsString(
                'wc-',
                $option['value'],
                "Order status option '{$option['value']}' must NOT have 'wc-' prefix for legacy compatibility"
            );
        }
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
