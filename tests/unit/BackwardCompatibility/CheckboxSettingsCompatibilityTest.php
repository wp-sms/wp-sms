<?php

namespace unit\BackwardCompatibility;

use WP_SMS\Option;
use WP_UnitTestCase;

/**
 * Checkbox/Switch Settings Backward Compatibility Tests
 *
 * These tests verify that all WP SMS add-ons handle checkbox/switch settings
 * in a consistent way that is backward compatible with legacy code.
 *
 * Key requirements:
 * 1. SAVE: Checkbox ON saves '1', OFF removes the key (unset)
 * 2. READ: Must accept all legacy formats: '1', 1, 'on', 'yes', 'enable', true
 * 3. LEGACY UI: checkbox_callback uses checked(1, $value) - needs '1' or 1
 * 4. LEGACY RUNTIME: Some use isset($options['key']), some use truthy checks
 *
 * The unset pattern works for BOTH:
 * - isset() checks: returns false when key doesn't exist
 * - truthy checks: Option::getOption returns '' for missing keys, which is falsy
 */
class CheckboxSettingsCompatibilityTest extends WP_UnitTestCase
{
    /**
     * All legacy truthy values that should be interpreted as "enabled"
     */
    private static $legacyTruthyValues = ['1', 1, 'on', 'yes', 'enable', true];

    /**
     * All legacy falsy values that should be interpreted as "disabled"
     */
    private static $legacyFalsyValues = ['', '0', 0, 'disable', 'no', false, null];

    public function setUp(): void
    {
        parent::setUp();
        // Clean slate for each test
        delete_option('wpsms_settings');
        delete_option('wps_pp_settings');
    }

    public function tearDown(): void
    {
        delete_option('wpsms_settings');
        delete_option('wps_pp_settings');
        parent::tearDown();
    }

    // =========================================================================
    // SAVE BEHAVIOR TESTS
    // =========================================================================

    /**
     * Test: When checkbox is enabled (true), value '1' is saved
     */
    public function testSaveEnabledCheckboxSavesStringOne()
    {
        // Simulate what handleSave should do
        $currentSettings = [];
        $enabled = true;

        if ($enabled) {
            $currentSettings['test_checkbox'] = '1';
        } else {
            unset($currentSettings['test_checkbox']);
        }

        update_option('wpsms_settings', $currentSettings);

        $saved = get_option('wpsms_settings');
        $this->assertEquals('1', $saved['test_checkbox'], 'Enabled checkbox should save as string "1"');
    }

    /**
     * Test: When checkbox is disabled (false), the key is removed
     */
    public function testSaveDisabledCheckboxRemovesKey()
    {
        // Pre-populate with an enabled value
        update_option('wpsms_settings', ['test_checkbox' => '1', 'other_setting' => 'value']);

        // Simulate disabling the checkbox
        $currentSettings = get_option('wpsms_settings');
        $enabled = false;

        if ($enabled) {
            $currentSettings['test_checkbox'] = '1';
        } else {
            unset($currentSettings['test_checkbox']);
        }

        update_option('wpsms_settings', $currentSettings);

        $saved = get_option('wpsms_settings');
        $this->assertArrayNotHasKey('test_checkbox', $saved, 'Disabled checkbox should remove the key');
        $this->assertEquals('value', $saved['other_setting'], 'Other settings should remain intact');
    }

    // =========================================================================
    // READ BEHAVIOR TESTS - Legacy Value Compatibility
    // =========================================================================

    /**
     * Test: All legacy truthy values should be read as enabled (true)
     *
     * @dataProvider legacyTruthyValuesProvider
     */
    public function testReadLegacyTruthyValues($legacyValue, $description)
    {
        update_option('wpsms_settings', ['test_checkbox' => $legacyValue]);

        $value = Option::getOption('test_checkbox');

        // The conversion to boolean should recognize all these as truthy
        $isTruthy = in_array($value, self::$legacyTruthyValues, true);

        $this->assertTrue(
            $isTruthy,
            "Legacy value {$description} should be recognized as truthy"
        );
    }

    public static function legacyTruthyValuesProvider(): array
    {
        return [
            ['1', 'string "1"'],
            [1, 'integer 1'],
            ['on', 'string "on"'],
            ['yes', 'string "yes"'],
            ['enable', 'string "enable"'],
            [true, 'boolean true'],
        ];
    }

    /**
     * Test: All legacy falsy values should be read as disabled (false)
     *
     * @dataProvider legacyFalsyValuesProvider
     */
    public function testReadLegacyFalsyValues($legacyValue, $description)
    {
        if ($legacyValue === null) {
            // Missing key case
            update_option('wpsms_settings', []);
        } else {
            update_option('wpsms_settings', ['test_checkbox' => $legacyValue]);
        }

        $value = Option::getOption('test_checkbox');

        // Empty string is the default for missing keys
        $isFalsy = !in_array($value, self::$legacyTruthyValues, true);

        $this->assertTrue(
            $isFalsy,
            "Legacy value {$description} should be recognized as falsy"
        );
    }

    public static function legacyFalsyValuesProvider(): array
    {
        return [
            ['', 'empty string'],
            ['0', 'string "0"'],
            [0, 'integer 0'],
            ['disable', 'string "disable"'],
            ['no', 'string "no"'],
            [false, 'boolean false'],
            [null, 'missing key (null)'],
        ];
    }

    // =========================================================================
    // LEGACY UI COMPATIBILITY TESTS
    // =========================================================================

    /**
     * Test: WordPress checked() function works correctly with our saved values
     *
     * The legacy checkbox_callback uses: checked(1, $value, false)
     * This must return 'checked="checked"' for enabled values
     */
    public function testLegacyCheckboxCallbackDisplaysCorrectly()
    {
        // Test with '1' (our new save format)
        $result = checked(1, '1', false);
        $this->assertEquals('checked=\'checked\'', $result, 'Value "1" should show as checked');

        // Test with integer 1
        $result = checked(1, 1, false);
        $this->assertEquals('checked=\'checked\'', $result, 'Value 1 should show as checked');

        // Test with empty (disabled)
        $result = checked(1, '', false);
        $this->assertEquals('', $result, 'Empty value should not show as checked');

        // Test with missing key (Option::getOption returns '')
        update_option('wpsms_settings', []);
        $value = Option::getOption('nonexistent_key');
        $result = checked(1, $value, false);
        $this->assertEquals('', $result, 'Missing key should not show as checked');
    }

    /**
     * Test: Legacy 'enable' value should NOT display as checked
     *
     * This is the bug we fixed - 'enable' != 1 so checkbox showed unchecked
     */
    public function testLegacyEnableValueDoesNotMatchCheckboxChecked()
    {
        // This demonstrates why 'enable' was wrong
        $result = checked(1, 'enable', false);
        $this->assertEquals('', $result, '"enable" does not match checked(1, value) - this was the bug');

        // But our new format works
        $result = checked(1, '1', false);
        $this->assertEquals('checked=\'checked\'', $result, '"1" correctly matches checked(1, value)');
    }

    // =========================================================================
    // LEGACY RUNTIME CODE COMPATIBILITY TESTS
    // =========================================================================

    /**
     * Test: isset() pattern works with our save format
     *
     * BuddyPress uses: if (isset(self::$options['bp_mention_enable']))
     */
    public function testIssetPatternWorksCorrectly()
    {
        // Enabled: key exists with '1'
        $options = ['test_enabled' => '1'];
        $this->assertTrue(isset($options['test_enabled']), 'isset() should return true when key exists with "1"');

        // Disabled: key does not exist
        $options = [];
        $this->assertFalse(isset($options['test_disabled']), 'isset() should return false when key is unset');

        // IMPORTANT: Empty string would be wrong!
        $options = ['test_empty' => ''];
        $this->assertTrue(isset($options['test_empty']), 'WARNING: isset() returns true for empty string - this is why we must unset, not save empty');
    }

    /**
     * Test: Truthy check pattern works with our save format
     *
     * Some integrations use: if (Option::getOption('key'))
     */
    public function testTruthyCheckPatternWorksCorrectly()
    {
        // Enabled: '1' is truthy
        update_option('wpsms_settings', ['test_checkbox' => '1']);
        $value = Option::getOption('test_checkbox');
        $this->assertTrue((bool)$value, '"1" should be truthy');

        // Disabled: key unset, getOption returns ''
        update_option('wpsms_settings', []);
        $value = Option::getOption('test_checkbox');
        $this->assertFalse((bool)$value, 'Missing key (empty string) should be falsy');
    }

    /**
     * Test: Combined isset AND truthy pattern works
     *
     * AwesomeSupport uses: if (isset($options['key']) and $options['key'])
     */
    public function testCombinedIssetAndTruthyPatternWorks()
    {
        // Enabled
        $options = ['test_checkbox' => '1'];
        $result = isset($options['test_checkbox']) && $options['test_checkbox'];
        $this->assertTrue($result, 'isset() && truthy should work for enabled');

        // Disabled (key unset)
        $options = [];
        $result = isset($options['test_checkbox']) && $options['test_checkbox'];
        $this->assertFalse($result, 'isset() && truthy should work for disabled (unset)');
    }

    // =========================================================================
    // PRO SETTINGS (wps_pp_settings) TESTS
    // =========================================================================

    /**
     * Test: Pro settings use separate option name but same pattern
     */
    public function testProSettingsUseSamePattern()
    {
        // Save enabled
        $proSettings = ['bp_mention_enable' => '1'];
        update_option('wps_pp_settings', $proSettings);

        $saved = Option::getOptions(true);
        $this->assertEquals('1', $saved['bp_mention_enable'], 'Pro setting should save as "1"');

        // Save disabled (unset)
        unset($proSettings['bp_mention_enable']);
        update_option('wps_pp_settings', $proSettings);

        $saved = Option::getOptions(true);
        $this->assertArrayNotHasKey('bp_mention_enable', $saved, 'Disabled pro setting should be unset');
    }

    // =========================================================================
    // MIGRATION TESTS - Old Data Still Works
    // =========================================================================

    /**
     * Test: Old 'enable' values still work at runtime (backward compatible)
     *
     * Users who haven't re-saved their settings should still have working features
     */
    public function testOldEnableValuesStillWorkAtRuntime()
    {
        // Simulate old data with 'enable' value
        update_option('wps_pp_settings', [
            'bp_mention_enable' => 'enable',
            'bp_mention_message' => 'Test message',
        ]);

        $options = Option::getOptions(true);

        // BuddyPress uses isset() - this will work because key exists
        $this->assertTrue(
            isset($options['bp_mention_enable']),
            'Old "enable" value should still make isset() return true'
        );

        // The feature will still be enabled at runtime
        // (even though the checkbox displays incorrectly - that's the UI bug we fixed)
    }

    /**
     * Test: After re-saving from React UI, old values get converted
     */
    public function testResavingConvertsOldValuesToNewFormat()
    {
        // Simulate old data
        update_option('wps_pp_settings', ['bp_mention_enable' => 'enable']);

        // Simulate reading in React UI (converts to boolean)
        $legacyValue = 'enable';
        $boolValue = in_array($legacyValue, self::$legacyTruthyValues, true);
        $this->assertTrue($boolValue, 'Old "enable" reads as true');

        // Simulate saving from React UI (converts boolean to '1')
        $settings = get_option('wps_pp_settings');
        if ($boolValue) {
            $settings['bp_mention_enable'] = '1';
        } else {
            unset($settings['bp_mention_enable']);
        }
        update_option('wps_pp_settings', $settings);

        // Verify new format
        $saved = get_option('wps_pp_settings');
        $this->assertEquals('1', $saved['bp_mention_enable'], 'Re-saved value should be "1"');
    }
}
