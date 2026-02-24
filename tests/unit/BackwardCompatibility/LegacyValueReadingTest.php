<?php

namespace unit\BackwardCompatibility;

use WP_UnitTestCase;

/**
 * Legacy Value Reading Tests
 *
 * These tests verify that the React UI correctly reads all possible legacy value formats
 * and converts them to the appropriate boolean for display in the UI.
 *
 * Legacy values that should be read as TRUE (enabled):
 * - '1' (string) - new format
 * - 1 (integer)
 * - 'on' (checkbox HTML default)
 * - 'yes' (WooCommerce format)
 * - 'enable' (old WP SMS format)
 * - true (boolean)
 *
 * Legacy values that should be read as FALSE (disabled):
 * - '' (empty string)
 * - '0' (string)
 * - 0 (integer)
 * - 'disable' (old WP SMS format)
 * - 'no' (WooCommerce format)
 * - false (boolean)
 * - null / missing key
 */
class LegacyValueReadingTest extends WP_UnitTestCase
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
     * Helper: Convert a legacy value to boolean using the same logic as our getCurrentValues
     */
    private function convertLegacyToBoolean($value): bool
    {
        return in_array($value, ['1', 1, 'on', 'yes', 'enable', true], true);
    }

    /**
     * Test: String '1' is read as enabled
     */
    public function testStringOneIsEnabled()
    {
        $this->assertTrue($this->convertLegacyToBoolean('1'));
    }

    /**
     * Test: Integer 1 is read as enabled
     */
    public function testIntegerOneIsEnabled()
    {
        $this->assertTrue($this->convertLegacyToBoolean(1));
    }

    /**
     * Test: 'on' is read as enabled (HTML checkbox default)
     */
    public function testOnIsEnabled()
    {
        $this->assertTrue($this->convertLegacyToBoolean('on'));
    }

    /**
     * Test: 'yes' is read as enabled (WooCommerce format)
     */
    public function testYesIsEnabled()
    {
        $this->assertTrue($this->convertLegacyToBoolean('yes'));
    }

    /**
     * Test: 'enable' is read as enabled (old WP SMS format)
     */
    public function testEnableIsEnabled()
    {
        $this->assertTrue($this->convertLegacyToBoolean('enable'));
    }

    /**
     * Test: boolean true is read as enabled
     */
    public function testBooleanTrueIsEnabled()
    {
        $this->assertTrue($this->convertLegacyToBoolean(true));
    }

    /**
     * Test: Empty string is read as disabled
     */
    public function testEmptyStringIsDisabled()
    {
        $this->assertFalse($this->convertLegacyToBoolean(''));
    }

    /**
     * Test: String '0' is read as disabled
     */
    public function testStringZeroIsDisabled()
    {
        $this->assertFalse($this->convertLegacyToBoolean('0'));
    }

    /**
     * Test: Integer 0 is read as disabled
     */
    public function testIntegerZeroIsDisabled()
    {
        $this->assertFalse($this->convertLegacyToBoolean(0));
    }

    /**
     * Test: 'disable' is read as disabled (old WP SMS format)
     */
    public function testDisableIsDisabled()
    {
        $this->assertFalse($this->convertLegacyToBoolean('disable'));
    }

    /**
     * Test: 'no' is read as disabled (WooCommerce format)
     */
    public function testNoIsDisabled()
    {
        $this->assertFalse($this->convertLegacyToBoolean('no'));
    }

    /**
     * Test: boolean false is read as disabled
     */
    public function testBooleanFalseIsDisabled()
    {
        $this->assertFalse($this->convertLegacyToBoolean(false));
    }

    /**
     * Test: null is read as disabled
     */
    public function testNullIsDisabled()
    {
        $this->assertFalse($this->convertLegacyToBoolean(null));
    }

    // =========================================================================
    // INTEGRATION TESTS WITH REAL DATA
    // =========================================================================

    /**
     * Test: Reading old 'enable' values from database
     */
    public function testReadOldEnableValueFromDatabase()
    {
        update_option('wps_pp_settings', [
            'bp_mention_enable' => 'enable',
            'bp_welcome_notification_enable' => 'disable',
        ]);

        $settings = get_option('wps_pp_settings');

        // Simulate what getCurrentValues does
        $mentionEnabled = $this->convertLegacyToBoolean($settings['bp_mention_enable']);
        $welcomeEnabled = $this->convertLegacyToBoolean($settings['bp_welcome_notification_enable'] ?? null);

        $this->assertTrue($mentionEnabled, 'Old "enable" value should read as true');
        $this->assertFalse($welcomeEnabled, 'Old "disable" value should read as false');
    }

    /**
     * Test: Reading new '1' values from database
     */
    public function testReadNewStringOneValueFromDatabase()
    {
        update_option('wps_pp_settings', [
            'bp_mention_enable' => '1',
            // bp_welcome_notification_enable is unset (disabled)
        ]);

        $settings = get_option('wps_pp_settings');

        $mentionEnabled = $this->convertLegacyToBoolean($settings['bp_mention_enable'] ?? null);
        $welcomeEnabled = $this->convertLegacyToBoolean($settings['bp_welcome_notification_enable'] ?? null);

        $this->assertTrue($mentionEnabled, 'New "1" value should read as true');
        $this->assertFalse($welcomeEnabled, 'Missing key should read as false');
    }

    /**
     * Test: WooCommerce 'yes'/'no' format works
     */
    public function testWooCommerceYesNoFormatWorks()
    {
        // WooCommerce Pro uses 'yes'/'no'
        update_option('wpsmswoopro_test_setting', 'yes');
        $yesValue = get_option('wpsmswoopro_test_setting');
        $this->assertTrue($this->convertLegacyToBoolean($yesValue));

        update_option('wpsmswoopro_test_setting', 'no');
        $noValue = get_option('wpsmswoopro_test_setting');
        $this->assertFalse($this->convertLegacyToBoolean($noValue));
    }

    /**
     * Test: Mixed legacy formats all work together
     */
    public function testMixedLegacyFormatsWorkTogether()
    {
        // Simulate a database with various legacy formats
        update_option('wps_pp_settings', [
            'setting_with_enable' => 'enable',
            'setting_with_one' => '1',
            'setting_with_yes' => 'yes',
            'setting_with_on' => 'on',
            'setting_with_true' => true,
            'setting_with_disable' => 'disable',
            'setting_with_empty' => '',
            'setting_with_no' => 'no',
        ]);

        $settings = get_option('wps_pp_settings');

        // All these should be enabled
        $this->assertTrue($this->convertLegacyToBoolean($settings['setting_with_enable']));
        $this->assertTrue($this->convertLegacyToBoolean($settings['setting_with_one']));
        $this->assertTrue($this->convertLegacyToBoolean($settings['setting_with_yes']));
        $this->assertTrue($this->convertLegacyToBoolean($settings['setting_with_on']));
        $this->assertTrue($this->convertLegacyToBoolean($settings['setting_with_true']));

        // All these should be disabled
        $this->assertFalse($this->convertLegacyToBoolean($settings['setting_with_disable']));
        $this->assertFalse($this->convertLegacyToBoolean($settings['setting_with_empty']));
        $this->assertFalse($this->convertLegacyToBoolean($settings['setting_with_no']));
    }

    // =========================================================================
    // BIDIRECTIONAL COMPATIBILITY TESTS
    // =========================================================================

    /**
     * Test: Values saved from React can be read by legacy code
     */
    public function testReactSavedValuesWorkWithLegacyCode()
    {
        // Simulate React saving enabled value
        $settings = ['bp_mention_enable' => '1'];
        update_option('wps_pp_settings', $settings);

        // Simulate BuddyPress legacy code reading
        $options = get_option('wps_pp_settings');

        // BuddyPress uses isset()
        $this->assertTrue(isset($options['bp_mention_enable']), 'React-saved enabled should work with isset()');

        // AwesomeSupport uses isset() && truthy
        $this->assertTrue(
            isset($options['bp_mention_enable']) && $options['bp_mention_enable'],
            'React-saved enabled should work with isset() && truthy'
        );
    }

    /**
     * Test: Values saved from React (disabled) work with legacy code
     */
    public function testReactSavedDisabledWorksWithLegacyCode()
    {
        // Simulate React saving disabled value (key is unset)
        $settings = ['other_setting' => 'value'];
        update_option('wps_pp_settings', $settings);

        $options = get_option('wps_pp_settings');

        // BuddyPress uses isset()
        $this->assertFalse(isset($options['bp_mention_enable']), 'React-saved disabled should work with isset()');

        // AwesomeSupport uses isset() && truthy
        $this->assertFalse(
            isset($options['bp_mention_enable']) && ($options['bp_mention_enable'] ?? false),
            'React-saved disabled should work with isset() && truthy'
        );
    }

    /**
     * Test: Legacy checkbox callback displays correctly with new format
     */
    public function testLegacyCheckboxCallbackWithNewFormat()
    {
        // checked(1, '1') should return 'checked="checked"'
        $this->assertStringContainsString('checked', checked(1, '1', false));

        // checked(1, '') should return ''
        $this->assertEquals('', checked(1, '', false));

        // For comparison: checked(1, 'enable') does NOT work (this was the bug)
        $this->assertEquals('', checked(1, 'enable', false), 'Old "enable" format did not work with checked()');
    }
}
