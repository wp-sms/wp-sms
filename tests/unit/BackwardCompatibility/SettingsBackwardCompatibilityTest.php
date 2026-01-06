<?php

namespace unit\BackwardCompatibility;

use WP_SMS\Api\V1\SettingsApi;
use WP_SMS\Option;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Backward Compatibility Tests for Settings
 *
 * Ensures that settings saved via the legacy admin pages work correctly
 * with the new React dashboard, and vice versa.
 */
class SettingsBackwardCompatibilityTest extends WP_UnitTestCase
{
    /**
     * @var int
     */
    private $adminUserId;

    /**
     * Legacy settings format - as stored by WordPress Settings API
     * @var array
     */
    private $legacySettings = [
        'gateway_name'              => 'twilio',
        'gateway_key'               => 'test_api_key_123',
        'gateway_password'          => 'test_password_456',
        'gateway_sender_id'         => '+15551234567',
        'admin_mobile_number'       => '+15559876543',
        'international_mobile'      => '1',
        'add_mobile_field'          => 'add_mobile_field',
        'mobile_field_source'       => 'wpsms',
        'mobile_county_code'        => '1',
        // Checkbox values stored as option key name when checked
        'store_outbox_messages'     => 'store_outbox_messages',
        'store_inbox_messages'      => 'store_inbox_messages',
        'display_notifications'     => 'display_notifications',
        // Notification settings
        'notif_publish_new_post_enabled' => '1',
        'notif_publish_new_post_receiver' => '+15551111111',
        'notif_register_new_user_enabled' => '1',
        // Integer/number values stored as strings
        'outbox_retention_days'     => '30',
        'message_retention'         => '90',
    ];

    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->adminUserId = self::factory()->user->create([
            'role' => 'administrator'
        ]);
        wp_set_current_user($this->adminUserId);

        // Initialize REST server
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        // Clear settings before each test
        delete_option('wpsms_settings');
        delete_option('wps_pp_settings');
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void
    {
        parent::tearDown();
        wp_set_current_user(0);
        delete_option('wpsms_settings');
        delete_option('wps_pp_settings');
    }

    /**
     * Test: Settings saved via legacy admin are readable via REST API
     */
    public function testLegacySettingsReadableViaRestApi()
    {
        // Save settings the legacy way (direct update_option)
        update_option('wpsms_settings', $this->legacySettings);

        // Read via REST API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('settings', $data['data']);

        $apiSettings = $data['data']['settings'];

        // Verify gateway name is preserved
        $this->assertEquals('twilio', $apiSettings['gateway_name']);

        // Verify sender ID is preserved
        $this->assertEquals('+15551234567', $apiSettings['gateway_sender_id']);

        // Verify admin mobile is preserved
        $this->assertEquals('+15559876543', $apiSettings['admin_mobile_number']);

        // Verify checkbox values are preserved (as string)
        $this->assertEquals('1', $apiSettings['international_mobile']);
    }

    /**
     * Test: Sensitive fields from legacy settings are masked in API response
     */
    public function testLegacySensitiveFieldsAreMasked()
    {
        update_option('wpsms_settings', $this->legacySettings);

        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $apiSettings = $data['data']['settings'];

        // Sensitive fields should be masked
        $this->assertEquals('••••••••', $apiSettings['gateway_key']);
        $this->assertEquals('••••••••', $apiSettings['gateway_password']);
    }

    /**
     * Test: Settings saved via REST API are readable via legacy Option class
     */
    public function testRestApiSettingsReadableViaLegacyOption()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'gateway_name'        => 'vonage',
                'gateway_sender_id'   => '+15557777777',
                'admin_mobile_number' => '+15558888888',
            ],
        ]);

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());

        // Read via legacy Option class
        $savedGateway = Option::getOption('gateway_name');
        $savedSenderId = Option::getOption('gateway_sender_id');
        $savedAdminMobile = Option::getOption('admin_mobile_number');

        $this->assertEquals('vonage', $savedGateway);
        $this->assertEquals('+15557777777', $savedSenderId);
        $this->assertEquals('+15558888888', $savedAdminMobile);
    }

    /**
     * Test: Updating settings via API preserves unmodified legacy settings
     */
    public function testApiUpdatePreservesUnmodifiedLegacySettings()
    {
        // Save full legacy settings
        update_option('wpsms_settings', $this->legacySettings);

        // Update only one setting via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'admin_mobile_number' => '+15550000000',
            ],
        ]);

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());

        // Verify other settings are preserved
        $allSettings = Option::getOptions();

        $this->assertEquals('twilio', $allSettings['gateway_name']);
        $this->assertEquals('+15551234567', $allSettings['gateway_sender_id']);
        $this->assertEquals('1', $allSettings['international_mobile']);
        $this->assertEquals('+15550000000', $allSettings['admin_mobile_number']);
    }

    /**
     * Test: Checkbox values maintain backward compatible format
     * Legacy: 'field_name' => 'field_name' when checked
     * API: Should preserve this format when reading/writing
     */
    public function testCheckboxValuesBackwardCompatible()
    {
        // Legacy checkbox format
        $legacyWithCheckboxes = [
            'store_outbox_messages'   => 'store_outbox_messages',
            'display_notifications'   => 'display_notifications',
            'add_mobile_field'        => 'add_mobile_field',
        ];
        update_option('wpsms_settings', $legacyWithCheckboxes);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $apiSettings = $data['data']['settings'];

        // Values should be preserved for backward compatibility
        $this->assertEquals('store_outbox_messages', $apiSettings['store_outbox_messages']);
        $this->assertEquals('display_notifications', $apiSettings['display_notifications']);
        $this->assertEquals('add_mobile_field', $apiSettings['add_mobile_field']);
    }

    /**
     * Test: Boolean toggle values maintain backward compatible format
     * Legacy: '1' or '' (string)
     * API: Should handle both boolean and string formats
     */
    public function testBooleanToggleValuesBackwardCompatible()
    {
        $legacyWithToggles = [
            'international_mobile'              => '1',
            'notif_publish_new_post_enabled'   => '1',
            'notif_register_new_user_enabled'  => '',
        ];
        update_option('wpsms_settings', $legacyWithToggles);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $apiSettings = $data['data']['settings'];

        // String '1' values should be preserved
        $this->assertEquals('1', $apiSettings['international_mobile']);
        $this->assertEquals('1', $apiSettings['notif_publish_new_post_enabled']);
    }

    /**
     * Test: API updates using boolean true are compatible with legacy
     */
    public function testApiBooleanUpdatesCompatibleWithLegacy()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'notif_publish_new_post_enabled' => true,
            ],
        ]);

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());

        // Legacy code should be able to read this value
        $value = Option::getOption('notif_publish_new_post_enabled');

        // Value should be truthy when checked with legacy code
        $this->assertNotEmpty($value);
    }

    /**
     * Test: Numeric settings maintain proper format
     */
    public function testNumericSettingsFormat()
    {
        $legacyWithNumbers = [
            'outbox_retention_days' => '30',
            'message_retention'     => '90',
            'mobile_county_code'    => '1',
        ];
        update_option('wpsms_settings', $legacyWithNumbers);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $apiSettings = $data['data']['settings'];

        // Numeric values should be preserved as stored
        $this->assertEquals('30', $apiSettings['outbox_retention_days']);
        $this->assertEquals('90', $apiSettings['message_retention']);
        $this->assertEquals('1', $apiSettings['mobile_county_code']);
    }

    /**
     * Test: Pro settings (wps_pp_settings) backward compatibility
     */
    public function testProSettingsBackwardCompatibility()
    {
        $legacyProSettings = [
            'wc_notify_customer_order_status_enabled' => '1',
            'wc_notify_customer_order_status_message' => 'Your order #{order_id} status: {status}',
            'wc_notify_new_order_enabled'             => '1',
        ];
        update_option('wps_pp_settings', $legacyProSettings);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('proSettings', $data['data']);

        $proSettings = $data['data']['proSettings'];

        // Pro settings should be readable
        $this->assertEquals('1', $proSettings['wc_notify_customer_order_status_enabled']);
        $this->assertEquals(
            'Your order #{order_id} status: {status}',
            $proSettings['wc_notify_customer_order_status_message']
        );
    }

    /**
     * Test: Updating pro settings via API preserves unmodified settings
     */
    public function testApiProSettingsUpdatePreservesUnmodified()
    {
        $legacyProSettings = [
            'setting_a' => 'value_a',
            'setting_b' => 'value_b',
            'setting_c' => 'value_c',
        ];
        update_option('wps_pp_settings', $legacyProSettings);

        // Update only one pro setting via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'proSettings' => [
                'setting_b' => 'updated_value_b',
            ],
        ]);

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());

        // Verify other pro settings are preserved
        $allProSettings = Option::getOptions(true);

        $this->assertEquals('value_a', $allProSettings['setting_a']);
        $this->assertEquals('updated_value_b', $allProSettings['setting_b']);
        $this->assertEquals('value_c', $allProSettings['setting_c']);
    }

    /**
     * Test: Sensitive fields are NOT overwritten with masked value
     */
    public function testMaskedValueDoesNotOverwriteSensitiveFields()
    {
        // Save settings with actual sensitive values
        update_option('wpsms_settings', [
            'gateway_key'      => 'real_api_key_secret',
            'gateway_password' => 'real_password_secret',
        ]);

        // Send update with masked values (as React would when user doesn't change them)
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'gateway_key'         => '••••••••',
                'gateway_password'    => '••••••••',
                'gateway_sender_id'   => '+15551234567', // Change another field
            ],
        ]);

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());

        // Sensitive values should be preserved (not overwritten with mask)
        $savedSettings = Option::getOptions();

        $this->assertEquals('real_api_key_secret', $savedSettings['gateway_key']);
        $this->assertEquals('real_password_secret', $savedSettings['gateway_password']);
        $this->assertEquals('+15551234567', $savedSettings['gateway_sender_id']);
    }

    /**
     * Test: Settings structure matches what legacy PHP admin expects
     */
    public function testSettingsStructureMatchesLegacyExpectations()
    {
        // These are the core option keys that legacy code depends on
        $requiredOptionKeys = [
            'gateway_name',
            'gateway_key',
            'gateway_password',
            'gateway_sender_id',
            'admin_mobile_number',
            'international_mobile',
            'mobile_county_code',
            'add_mobile_field',
        ];

        // Save via API
        $settingsToSave = [];
        foreach ($requiredOptionKeys as $key) {
            $settingsToSave[$key] = 'test_value_' . $key;
        }

        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => $settingsToSave,
        ]);

        rest_do_request($request);

        // Read via legacy method
        $savedOptions = get_option('wpsms_settings');

        // All keys should exist in the saved option
        foreach ($requiredOptionKeys as $key) {
            $this->assertArrayHasKey($key, $savedOptions, "Missing key: {$key}");
        }
    }

    /**
     * Test: Option::updateOption works with new API-saved data
     */
    public function testLegacyOptionUpdateWorksWithApiData()
    {
        // First save via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'gateway_name' => 'twilio',
            ],
        ]);
        rest_do_request($request);

        // Then update via legacy Option class
        Option::updateOption('admin_mobile_number', '+15551234567');

        // Both values should exist
        $allSettings = Option::getOptions();

        $this->assertEquals('twilio', $allSettings['gateway_name']);
        $this->assertEquals('+15551234567', $allSettings['admin_mobile_number']);
    }

    /**
     * Test: Empty settings don't cause errors in either direction
     */
    public function testEmptySettingsDoNotCauseErrors()
    {
        // Start with empty settings
        delete_option('wpsms_settings');

        // Read via API should return empty array, not error
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('settings', $data['data']);

        // Legacy read should also handle empty gracefully
        $legacySettings = Option::getOptions();
        $this->assertIsArray($legacySettings);
    }

    /**
     * Test: Special characters in settings are properly handled
     */
    public function testSpecialCharactersInSettings()
    {
        $settingsWithSpecialChars = [
            'gateway_sender_id'   => 'Company & Sons <SMS>',
            'custom_message'      => "Hello! It's a \"test\" message with symbols: @#$%",
        ];
        update_option('wpsms_settings', $settingsWithSpecialChars);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        // Special characters should be preserved but safely encoded
        $apiSettings = $data['data']['settings'];

        $this->assertNotEmpty($apiSettings['gateway_sender_id']);
        $this->assertNotEmpty($apiSettings['custom_message']);
    }

    /**
     * Test: Array-type settings backward compatibility
     * Some legacy settings store arrays (e.g., notification recipients)
     */
    public function testArraySettingsBackwardCompatibility()
    {
        $legacyWithArrays = [
            'newsletter_form_groups' => ['group_1', 'group_2', 'group_3'],
            'notification_receivers' => ['+15551111111', '+15552222222'],
        ];
        update_option('wpsms_settings', $legacyWithArrays);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $apiSettings = $data['data']['settings'];

        // Arrays should be preserved
        $this->assertIsArray($apiSettings['newsletter_form_groups']);
        $this->assertCount(3, $apiSettings['newsletter_form_groups']);
        $this->assertContains('group_1', $apiSettings['newsletter_form_groups']);
    }

    /**
     * Test: Gateway credentials can be updated via API
     */
    public function testGatewayCredentialsUpdatable()
    {
        // Start with existing credentials
        update_option('wpsms_settings', [
            'gateway_key'      => 'old_key',
            'gateway_password' => 'old_password',
        ]);

        // Update with new credentials (not masked value)
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'gateway_key'      => 'new_key_123',
                'gateway_password' => 'new_password_456',
            ],
        ]);

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());

        // New values should be saved
        $savedSettings = Option::getOptions();

        $this->assertEquals('new_key_123', $savedSettings['gateway_key']);
        $this->assertEquals('new_password_456', $savedSettings['gateway_password']);
    }

    /**
     * Test: Cache is properly cleared after API update
     */
    public function testCacheClearedAfterApiUpdate()
    {
        // Save initial value
        update_option('wpsms_settings', ['gateway_name' => 'initial']);

        // Read to potentially cache
        Option::getOptions();

        // Update via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'gateway_name' => 'updated',
            ],
        ]);
        rest_do_request($request);

        // Read again - should get updated value, not cached
        $settings = Option::getOptions();

        $this->assertEquals('updated', $settings['gateway_name']);
    }
}
