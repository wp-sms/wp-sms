<?php

use PHPUnit\Framework\TestCase;
use WP_SMS\Settings\Option;

class OptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean up any existing options before each test
        delete_option('wp_sms_settings');
        delete_option('wp_sms_pro_settings');
        delete_option('wp_sms_two_way_settings');
        delete_option('wp_sms_booking_integrations_settings');
        delete_option('wp_sms_fluent_integrations_settings');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up after each test
        delete_option('wp_sms_settings');
        delete_option('wp_sms_pro_settings');
        delete_option('wp_sms_two_way_settings');
        delete_option('wp_sms_booking_integrations_settings');
        delete_option('wp_sms_fluent_integrations_settings');
    }

    public function testGetOptionsReturnsEmptyArrayForNewAddon()
    {
        $options = Option::getOptions('pro');
        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    public function testGetOptionsReturnsEmptyArrayForCoreSettings()
    {
        $options = Option::getOptions();
        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    public function testUpdateOptionSavesToCorrectAddonOptionKey()
    {
        $key = 'test_key';
        $value = 'test_value';
        $addon = 'pro';

        Option::updateOption($key, $value, $addon);

        // Check that the option was saved to the correct option key
        $savedOptions = get_option('wp_sms_pro_settings');
        $this->assertIsArray($savedOptions);
        $this->assertEquals($value, $savedOptions[$key]);
    }

    public function testUpdateOptionSavesToCoreOptionKeyWhenNoAddon()
    {
        $key = 'test_key';
        $value = 'test_value';

        Option::updateOption($key, $value);

        // Check that the option was saved to the core option key
        $savedOptions = get_option('wp_sms_settings');
        $this->assertIsArray($savedOptions);
        $this->assertEquals($value, $savedOptions[$key]);
    }

    public function testGetOptionReturnsCorrectValueForAddon()
    {
        $key = 'test_key';
        $value = 'test_value';
        $addon = 'two_way';

        // Set up the option
        $options = [$key => $value];
        update_option('wp_sms_two_way_settings', $options);

        $result = Option::getOption($key, $addon);
        $this->assertEquals($value, $result);
    }

    public function testGetOptionReturnsCorrectValueForCoreSettings()
    {
        $key = 'test_key';
        $value = 'test_value';

        // Set up the option
        $options = [$key => $value];
        update_option('wp_sms_settings', $options);

        $result = Option::getOption($key);
        $this->assertEquals($value, $result);
    }

    public function testGetOptionReturnsEmptyStringForNonExistentKey()
    {
        $result = Option::getOption('non_existent_key', 'pro');
        $this->assertEquals('', $result);
    }

    public function testGetOptionsReturnsAllOptionsForAddon()
    {
        $addon = 'booking_integrations';
        $options = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        update_option('wp_sms_booking_integrations_settings', $options);

        $result = Option::getOptions($addon);
        $this->assertEquals($options, $result);
    }

    public function testUpdateOptionOverwritesExistingValue()
    {
        $key = 'test_key';
        $initialValue = 'initial_value';
        $newValue = 'new_value';
        $addon = 'fluent_integrations';

        // Set initial value
        Option::updateOption($key, $initialValue, $addon);
        
        // Update with new value
        Option::updateOption($key, $newValue, $addon);

        $result = Option::getOption($key, $addon);
        $this->assertEquals($newValue, $result);
    }


    public function testMultipleAddonsCanHaveSameKeyWithDifferentValues()
    {
        $key = 'same_key';
        $proValue = 'pro_value';
        $twoWayValue = 'two_way_value';

        Option::updateOption($key, $proValue, 'pro');
        Option::updateOption($key, $twoWayValue, 'two_way');

        $proResult = Option::getOption($key, 'pro');
        $twoWayResult = Option::getOption($key, 'two_way');

        $this->assertEquals($proValue, $proResult);
        $this->assertEquals($twoWayValue, $twoWayResult);
        $this->assertNotEquals($proResult, $twoWayResult);
    }

    public function testCoreAndAddonSettingsAreIndependent()
    {
        $key = 'shared_key';
        $coreValue = 'core_value';
        $addonValue = 'addon_value';

        Option::updateOption($key, $coreValue);
        Option::updateOption($key, $addonValue, 'pro');

        $coreResult = Option::getOption($key);
        $addonResult = Option::getOption($key, 'pro');

        $this->assertEquals($coreValue, $coreResult);
        $this->assertEquals($addonValue, $addonResult);
        $this->assertNotEquals($coreResult, $addonResult);
    }

    public function testGetOptionsWithInvalidAddonReturnsCoreOptions()
    {
        $key = 'test_key';
        $value = 'test_value';

        // Set up core option
        update_option('wp_sms_settings', [$key => $value]);

        // Try to get options with invalid addon
        $result = Option::getOptions('invalid_addon');
        
        // Should return core options due to validation
        $this->assertIsArray($result);
        $this->assertEquals($value, $result[$key]);
    }

    public function testUpdateOptionWithInvalidAddonSavesToCore()
    {
        $key = 'test_key';
        $value = 'test_value';

        Option::updateOption($key, $value, 'invalid_addon');

        // Should be saved to core options
        $coreOptions = get_option('wp_sms_settings');
        $this->assertEquals($value, $coreOptions[$key]);
    }
} 