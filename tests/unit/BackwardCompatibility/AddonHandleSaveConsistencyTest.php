<?php

namespace unit\BackwardCompatibility;

use WP_UnitTestCase;

/**
 * Addon handleSave Consistency Tests
 *
 * These tests verify that all add-on handleSave implementations follow the same pattern:
 * - Checkbox/switch enabled: saves '1' (string)
 * - Checkbox/switch disabled: unsets the key (not empty string)
 * - Other field types: save value as-is
 *
 * This consistency is critical for backward compatibility with legacy code that uses
 * different check patterns (isset vs truthy).
 */
class AddonHandleSaveConsistencyTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        delete_option('wpsms_settings');
        delete_option('wps_pp_settings');
    }

    public function tearDown(): void
    {
        delete_option('wpsms_settings');
        delete_option('wps_pp_settings');
        parent::tearDown();
    }

    /**
     * Test: WP SMS Pro handleSave correctly saves checkbox values
     */
    public function testProHandleSaveCheckboxFormat()
    {
        // Skip if Pro not available
        if (!class_exists('WP_SMS\Pro\Admin\ReactSettings\ProSettingsSchema')) {
            $this->markTestSkipped('WP SMS Pro not installed');
        }

        // Pre-populate with some data
        update_option('wps_pp_settings', ['existing_key' => 'existing_value']);

        $fields = [
            'bp_mention_enable' => true,
            'bp_welcome_notification_enable' => false,
            'bp_mention_message' => 'Test message',
        ];

        $fieldTypes = [
            'bp_mention_enable' => 'switch',
            'bp_welcome_notification_enable' => 'switch',
            'bp_mention_message' => 'textarea',
        ];

        $result = \WP_SMS\Pro\Admin\ReactSettings\ProSettingsSchema::handleSave(false, $fields, $fieldTypes);

        $this->assertTrue($result, 'handleSave should return true');

        $saved = get_option('wps_pp_settings');

        // Enabled checkbox: should be '1'
        $this->assertArrayHasKey('bp_mention_enable', $saved);
        $this->assertEquals('1', $saved['bp_mention_enable'], 'Enabled switch should save as "1"');

        // Disabled checkbox: should be unset
        $this->assertArrayNotHasKey('bp_welcome_notification_enable', $saved, 'Disabled switch should be unset');

        // Text field: should save as-is
        $this->assertEquals('Test message', $saved['bp_mention_message']);

        // Existing key should be preserved
        $this->assertEquals('existing_value', $saved['existing_key']);
    }

    /**
     * Test: Booking Integration handleSave correctly saves checkbox values
     */
    public function testBookingHandleSaveCheckboxFormat()
    {
        // Skip if Booking not available
        if (!class_exists('WPSmsBookingIntegrations\Admin\ReactSettings\BookingSettingsSchema')) {
            $this->markTestSkipped('WP SMS Booking Integrations not installed');
        }

        $fields = [
            'bookingpress_notif_approved_appointment_admin' => true,
            'bookingpress_notif_pending_appointment_admin' => false,
            'bookingpress_notif_approved_appointment_admin_message' => 'Test message',
        ];

        $fieldTypes = [
            'bookingpress_notif_approved_appointment_admin' => 'switch',
            'bookingpress_notif_pending_appointment_admin' => 'switch',
            'bookingpress_notif_approved_appointment_admin_message' => 'textarea',
        ];

        $result = \WPSmsBookingIntegrations\Admin\ReactSettings\BookingSettingsSchema::handleSave(false, $fields, $fieldTypes);

        $this->assertTrue($result, 'handleSave should return true');

        $saved = get_option('wpsms_settings');

        // Enabled: should be '1'
        $this->assertEquals('1', $saved['bookingpress_notif_approved_appointment_admin']);

        // Disabled: should be unset
        $this->assertArrayNotHasKey('bookingpress_notif_pending_appointment_admin', $saved);

        // Text: should save as-is
        $this->assertEquals('Test message', $saved['bookingpress_notif_approved_appointment_admin_message']);
    }

    /**
     * Test: Fluent Integration handleSave correctly saves checkbox values
     */
    public function testFluentHandleSaveCheckboxFormat()
    {
        // Skip if Fluent not available
        if (!class_exists('WPSmsFluentCrm\Admin\ReactSettings\FluentSettingsSchema')) {
            $this->markTestSkipped('WP SMS Fluent Integrations not installed');
        }

        $fields = [
            'fluent_crm_notif_contact_subscribed' => true,
            'fluent_crm_notif_contact_unsubscribed' => false,
            'fluent_crm_notif_contact_subscribed_message' => 'Welcome!',
        ];

        $fieldTypes = [
            'fluent_crm_notif_contact_subscribed' => 'switch',
            'fluent_crm_notif_contact_unsubscribed' => 'switch',
            'fluent_crm_notif_contact_subscribed_message' => 'textarea',
        ];

        $result = \WPSmsFluentCrm\Admin\ReactSettings\FluentSettingsSchema::handleSave(false, $fields, $fieldTypes);

        $this->assertTrue($result, 'handleSave should return true');

        $saved = get_option('wpsms_settings');

        $this->assertEquals('1', $saved['fluent_crm_notif_contact_subscribed']);
        $this->assertArrayNotHasKey('fluent_crm_notif_contact_unsubscribed', $saved);
        $this->assertEquals('Welcome!', $saved['fluent_crm_notif_contact_subscribed_message']);
    }

    /**
     * Test: Membership Integration handleSave correctly saves checkbox values
     */
    public function testMembershipHandleSaveCheckboxFormat()
    {
        // Skip if Membership not available
        if (!class_exists('WPSmsMembershipIntegrations\Admin\ReactSettings\MembershipSettingsSchema')) {
            $this->markTestSkipped('WP SMS Membership Integrations not installed');
        }

        $fields = [
            'pmpro_user_register_status' => true,
            'pmpro_user_expiring_status' => false,
            'pmpro_user_register_message' => 'Test',
        ];

        $fieldTypes = [
            'pmpro_user_register_status' => 'switch',
            'pmpro_user_expiring_status' => 'switch',
            'pmpro_user_register_message' => 'textarea',
        ];

        $result = \WPSmsMembershipIntegrations\Admin\ReactSettings\MembershipSettingsSchema::handleSave(false, $fields, $fieldTypes);

        $this->assertTrue($result, 'handleSave should return true');

        $saved = get_option('wpsms_settings');

        $this->assertEquals('1', $saved['pmpro_user_register_status']);
        $this->assertArrayNotHasKey('pmpro_user_expiring_status', $saved);
        $this->assertEquals('Test', $saved['pmpro_user_register_message']);
    }

    /**
     * Test: Two-Way Integration handleSave correctly saves checkbox values
     */
    public function testTwoWayHandleSaveCheckboxFormat()
    {
        // Skip if Two-Way not available
        if (!class_exists('WPSmsTwoWay\Admin\ReactSettings\TwoWaySettingsSchema')) {
            $this->markTestSkipped('WP SMS Two-Way not installed');
        }

        $fields = [
            'store_inbox_messages' => true,
            'notif_new_inbox_message' => false,
            'inbox_retention_days' => 30,
        ];

        $fieldTypes = [
            'store_inbox_messages' => 'switch',
            'notif_new_inbox_message' => 'switch',
            'inbox_retention_days' => 'number',
        ];

        $result = \WPSmsTwoWay\Admin\ReactSettings\TwoWaySettingsSchema::handleSave(false, $fields, $fieldTypes);

        $this->assertTrue($result, 'handleSave should return true');

        $saved = get_option('wpsms_settings');

        $this->assertEquals('1', $saved['store_inbox_messages']);
        $this->assertArrayNotHasKey('notif_new_inbox_message', $saved);
        $this->assertEquals(30, $saved['inbox_retention_days']);
    }

    /**
     * Test: All add-ons preserve existing unrelated settings
     *
     * When saving add-on settings, other settings in the array should not be affected.
     */
    public function testHandleSavePreservesExistingSettings()
    {
        // Pre-populate with various settings
        update_option('wpsms_settings', [
            'gateway' => 'twilio',
            'from_number' => '+15551234567',
            'existing_checkbox' => '1',
        ]);

        // Skip if no add-ons available
        if (!class_exists('WPSmsBookingIntegrations\Admin\ReactSettings\BookingSettingsSchema')) {
            $this->markTestSkipped('WP SMS Booking Integrations not installed');
        }

        $fields = ['bookingpress_notif_approved_appointment_admin' => true];
        $fieldTypes = ['bookingpress_notif_approved_appointment_admin' => 'switch'];

        \WPSmsBookingIntegrations\Admin\ReactSettings\BookingSettingsSchema::handleSave(false, $fields, $fieldTypes);

        $saved = get_option('wpsms_settings');

        // New setting added
        $this->assertEquals('1', $saved['bookingpress_notif_approved_appointment_admin']);

        // Existing settings preserved
        $this->assertEquals('twilio', $saved['gateway']);
        $this->assertEquals('+15551234567', $saved['from_number']);
        $this->assertEquals('1', $saved['existing_checkbox']);
    }

    /**
     * Test: Toggling a setting off and back on works correctly
     */
    public function testToggleOffAndOnWorksCorrectly()
    {
        // Skip if Pro not available
        if (!class_exists('WP_SMS\Pro\Admin\ReactSettings\ProSettingsSchema')) {
            $this->markTestSkipped('WP SMS Pro not installed');
        }

        // Initial state: enabled
        $fields = ['bp_mention_enable' => true];
        $fieldTypes = ['bp_mention_enable' => 'switch'];
        \WP_SMS\Pro\Admin\ReactSettings\ProSettingsSchema::handleSave(false, $fields, $fieldTypes);

        $saved = get_option('wps_pp_settings');
        $this->assertEquals('1', $saved['bp_mention_enable'], 'Initial enable');

        // Toggle off
        $fields = ['bp_mention_enable' => false];
        \WP_SMS\Pro\Admin\ReactSettings\ProSettingsSchema::handleSave(false, $fields, $fieldTypes);

        $saved = get_option('wps_pp_settings');
        $this->assertArrayNotHasKey('bp_mention_enable', $saved, 'After disable');

        // Toggle back on
        $fields = ['bp_mention_enable' => true];
        \WP_SMS\Pro\Admin\ReactSettings\ProSettingsSchema::handleSave(false, $fields, $fieldTypes);

        $saved = get_option('wps_pp_settings');
        $this->assertEquals('1', $saved['bp_mention_enable'], 'After re-enable');
    }
}
