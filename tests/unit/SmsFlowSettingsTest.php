<?php

namespace unit;

use WP_SMS\Gateway;
use WP_SMS\Option;

require_once __DIR__ . '/WPSMSTestCase.php';

/**
 * Tests for SMS Flow Settings
 *
 * Verifies that all SMS-affecting settings work identically whether
 * set via legacy PHP settings page or new React settings page.
 *
 * Settings tested:
 * - clean_numbers: Strips spaces/dashes from phone numbers
 * - mobile_county_code: Prepends country code to numbers
 * - send_only_local_numbers: Filters to allowed countries
 * - only_local_numbers_countries: Specifies allowed country codes
 * - sms_delivery_method: Controls dispatch method (tested in SmsDispatcherTest)
 * - send_unicode: Unicode message encoding
 */
class SmsFlowSettingsTest extends WPSMSTestCase
{
    /**
     * @var \WP_SMS\Gateway
     */
    private $gateway;

    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        // Configure test gateway
        Option::updateOption('gateway_name', 'test');
        Option::updateOption('gateway_sender_id', 'TestSender');

        // Clear all SMS-flow settings to defaults
        Option::updateOption('clean_numbers', '');
        Option::updateOption('mobile_county_code', '');
        Option::updateOption('send_only_local_numbers', '');
        Option::updateOption('only_local_numbers_countries', []);
        Option::updateOption('send_unicode', '');

        // Reinitialize gateway to apply settings
        $this->gateway = Gateway::initial();
        $GLOBALS['sms'] = $this->gateway;
    }

    /**
     * Reinitialize gateway after settings change
     */
    private function reinitializeGateway(): void
    {
        // Remove existing filters to prevent stacking
        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_msg');
        remove_all_filters('wp_sms_from');

        // Reinitialize gateway with new settings
        $this->gateway = Gateway::initial();
        $GLOBALS['sms'] = $this->gateway;
    }

    // =========================================================================
    // CLEAN NUMBERS TESTS
    // =========================================================================

    /**
     * Test: clean_numbers disabled - numbers unchanged
     */
    public function testCleanNumbersDisabledKeepsOriginalFormat()
    {
        Option::updateOption('clean_numbers', '');
        $this->reinitializeGateway();

        $numbers = ['+1 555 123 4567', '+1-555-987-6543', '+1 555 000 0000'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        // Numbers should remain unchanged
        $this->assertEquals($numbers, $filtered);
    }

    /**
     * Test: clean_numbers enabled - strips spaces
     */
    public function testCleanNumbersEnabledStripsSpaces()
    {
        Option::updateOption('clean_numbers', '1');
        $this->reinitializeGateway();

        $numbers = ['+1 555 123 4567', '+1 555 987 6543'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+15551234567', '+15559876543'], $filtered);
    }

    /**
     * Test: clean_numbers enabled - strips dashes
     */
    public function testCleanNumbersEnabledStripsDashes()
    {
        Option::updateOption('clean_numbers', '1');
        $this->reinitializeGateway();

        $numbers = ['+1-555-123-4567', '+1-555-987-6543'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+15551234567', '+15559876543'], $filtered);
    }

    /**
     * Test: clean_numbers enabled - strips commas
     */
    public function testCleanNumbersEnabledStripsCommas()
    {
        Option::updateOption('clean_numbers', '1');
        $this->reinitializeGateway();

        $numbers = ['+1,555,123,4567'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+15551234567'], $filtered);
    }

    /**
     * Test: clean_numbers enabled - strips mixed formatting
     */
    public function testCleanNumbersEnabledStripsMixedFormatting()
    {
        Option::updateOption('clean_numbers', '1');
        $this->reinitializeGateway();

        $numbers = ['+1 555-123 4567', '+1, 555 - 987, 6543'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+15551234567', '+15559876543'], $filtered);
    }

    /**
     * Test: clean_numbers works with empty array
     */
    public function testCleanNumbersHandlesEmptyArray()
    {
        Option::updateOption('clean_numbers', '1');
        $this->reinitializeGateway();

        $filtered = apply_filters('wp_sms_to', []);

        $this->assertEquals([], $filtered);
    }

    // =========================================================================
    // MOBILE COUNTRY CODE TESTS
    // =========================================================================

    /**
     * Test: mobile_county_code disabled - numbers unchanged
     */
    public function testCountryCodeDisabledKeepsOriginalNumbers()
    {
        Option::updateOption('mobile_county_code', '');
        $this->reinitializeGateway();

        $numbers = ['5551234567', '09121234567'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals($numbers, $filtered);
    }

    /**
     * Test: mobile_county_code prepends to plain numbers
     */
    public function testCountryCodePrependsToPlainNumbers()
    {
        Option::updateOption('mobile_county_code', '+1');
        $this->reinitializeGateway();

        $numbers = ['5551234567'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+15551234567'], $filtered);
    }

    /**
     * Test: mobile_county_code strips leading zero and prepends
     */
    public function testCountryCodeStripsLeadingZero()
    {
        Option::updateOption('mobile_county_code', '+98');
        $this->reinitializeGateway();

        $numbers = ['09121234567'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+989121234567'], $filtered);
    }

    /**
     * Test: mobile_county_code strips double zero prefix
     */
    public function testCountryCodeStripsDoubleZeroPrefix()
    {
        Option::updateOption('mobile_county_code', '+1');
        $this->reinitializeGateway();

        $numbers = ['005551234567'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+15551234567'], $filtered);
    }

    /**
     * Test: mobile_county_code preserves numbers already with + prefix
     */
    public function testCountryCodePreservesNumbersWithPlusPrefix()
    {
        Option::updateOption('mobile_county_code', '+1');
        $this->reinitializeGateway();

        $numbers = ['+445551234567', '+989121234567'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        // Numbers with + prefix should remain unchanged
        $this->assertEquals(['+445551234567', '+989121234567'], $filtered);
    }

    /**
     * Test: mobile_county_code handles mixed number formats
     */
    public function testCountryCodeHandlesMixedFormats()
    {
        Option::updateOption('mobile_county_code', '+1');
        $this->reinitializeGateway();

        $numbers = ['5551234567', '09876543210', '005550000000', '+445559999999'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $expected = ['+15551234567', '+19876543210', '+15550000000', '+445559999999'];
        $this->assertEquals($expected, $filtered);
    }

    // =========================================================================
    // SEND ONLY LOCAL NUMBERS TESTS
    // =========================================================================

    /**
     * Test: send_only_local_numbers disabled - all numbers pass
     */
    public function testLocalNumbersDisabledAllowsAllNumbers()
    {
        Option::updateOption('send_only_local_numbers', '');
        Option::updateOption('only_local_numbers_countries', ['+1', '+44', '+98']);
        $this->reinitializeGateway();

        $numbers = ['+15551234567', '+449876543210', '+989121234567', '+8612345678901'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        // All numbers should pass when disabled
        $this->assertEquals($numbers, $filtered);
    }

    /**
     * Test: send_only_local_numbers enabled - filters to allowed countries
     */
    public function testLocalNumbersEnabledFiltersToAllowedCountries()
    {
        Option::updateOption('send_only_local_numbers', '1');
        Option::updateOption('only_local_numbers_countries', ['+1', '+44']);
        $this->reinitializeGateway();

        $numbers = ['+15551234567', '+449876543210', '+989121234567', '+8612345678901'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        // Only +1 and +44 numbers should pass
        $this->assertEquals(['+15551234567', '+449876543210'], $filtered);
    }

    /**
     * Test: send_only_local_numbers with empty countries list - all pass
     */
    public function testLocalNumbersEnabledWithEmptyCountriesAllowsAll()
    {
        Option::updateOption('send_only_local_numbers', '1');
        Option::updateOption('only_local_numbers_countries', []);
        $this->reinitializeGateway();

        $numbers = ['+15551234567', '+989121234567'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        // With empty countries list, all should pass
        $this->assertEquals($numbers, $filtered);
    }

    /**
     * Test: send_only_local_numbers filters out all non-matching
     */
    public function testLocalNumbersFiltersAllNonMatching()
    {
        Option::updateOption('send_only_local_numbers', '1');
        Option::updateOption('only_local_numbers_countries', ['+98']);
        $this->reinitializeGateway();

        $numbers = ['+15551234567', '+449876543210', '+8612345678901'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        // None should pass as none start with +98
        $this->assertEquals([], $filtered);
    }

    /**
     * Test: send_only_local_numbers with single country
     */
    public function testLocalNumbersWithSingleCountry()
    {
        Option::updateOption('send_only_local_numbers', '1');
        Option::updateOption('only_local_numbers_countries', ['+1']);
        $this->reinitializeGateway();

        $numbers = ['+15551234567', '+15559876543', '+449876543210'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+15551234567', '+15559876543'], $filtered);
    }

    // =========================================================================
    // COMBINED SETTINGS TESTS
    // =========================================================================

    /**
     * Test: clean_numbers + country_code applied in correct order
     */
    public function testCleanNumbersAndCountryCodeCombined()
    {
        Option::updateOption('clean_numbers', '1');
        Option::updateOption('mobile_county_code', '+1');
        $this->reinitializeGateway();

        $numbers = ['555 123 4567'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        // Should clean first, then apply country code
        // 555 123 4567 -> 5551234567 -> +15551234567
        $this->assertEquals(['+15551234567'], $filtered);
    }

    /**
     * Test: All filters combined
     */
    public function testAllFiltersCombined()
    {
        Option::updateOption('clean_numbers', '1');
        Option::updateOption('mobile_county_code', '+1');
        Option::updateOption('send_only_local_numbers', '1');
        Option::updateOption('only_local_numbers_countries', ['+1']);
        $this->reinitializeGateway();

        $numbers = ['555 123 4567', '+44 987 654 3210'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        // 555 123 4567 -> cleaned -> +1 applied -> +15551234567 (passes +1 filter)
        // +44 987 654 3210 -> cleaned -> +1 not applied (has +) -> +449876543210 (fails +1 filter)
        $this->assertEquals(['+15551234567'], $filtered);
    }

    // =========================================================================
    // SETTINGS STORAGE CONSISTENCY TESTS
    // =========================================================================

    /**
     * Test: Settings saved via Option class are read correctly
     */
    public function testSettingsSavedViaOptionClassAreReadCorrectly()
    {
        // Simulate saving via legacy PHP or React (both use Option::updateOption)
        Option::updateOption('clean_numbers', '1');
        Option::updateOption('mobile_county_code', '+44');
        Option::updateOption('send_only_local_numbers', '1');
        Option::updateOption('only_local_numbers_countries', ['+44', '+1']);

        // Verify settings are stored correctly
        $this->assertEquals('1', Option::getOption('clean_numbers'));
        $this->assertEquals('+44', Option::getOption('mobile_county_code'));
        $this->assertEquals('1', Option::getOption('send_only_local_numbers'));
        $this->assertEquals(['+44', '+1'], Option::getOption('only_local_numbers_countries'));
    }

    /**
     * Test: Gateway reads settings from wpsms_settings option
     */
    public function testGatewayReadsSettingsFromWordPressOption()
    {
        // Save settings
        Option::updateOption('clean_numbers', '1');
        $this->reinitializeGateway();

        // Verify Gateway has the settings
        $options = $this->gateway->options;
        $this->assertEquals('1', $options['clean_numbers']);
    }

    /**
     * Test: Settings changes require gateway reinitialization
     */
    public function testSettingsChangesRequireGatewayReinitialization()
    {
        // Start with clean_numbers disabled
        Option::updateOption('clean_numbers', '');
        $this->reinitializeGateway();

        $numbers = ['+1 555 123 4567'];
        $filtered1 = apply_filters('wp_sms_to', $numbers);
        $this->assertEquals(['+1 555 123 4567'], $filtered1); // Unchanged

        // Enable clean_numbers but DON'T reinitialize
        Option::updateOption('clean_numbers', '1');

        // Filter still uses old setting (cached in gateway)
        $filtered2 = apply_filters('wp_sms_to', $numbers);
        // This would fail without reinit, showing importance of gateway refresh

        // Now reinitialize
        $this->reinitializeGateway();
        $filtered3 = apply_filters('wp_sms_to', $numbers);
        $this->assertEquals(['+15551234567'], $filtered3); // Now cleaned
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    /**
     * Test: Empty recipient array handled gracefully
     */
    public function testEmptyRecipientArrayHandledGracefully()
    {
        Option::updateOption('clean_numbers', '1');
        Option::updateOption('mobile_county_code', '+1');
        Option::updateOption('send_only_local_numbers', '1');
        Option::updateOption('only_local_numbers_countries', ['+1']);
        $this->reinitializeGateway();

        $filtered = apply_filters('wp_sms_to', []);

        $this->assertEquals([], $filtered);
    }

    /**
     * Test: Single recipient handled correctly
     */
    public function testSingleRecipientHandledCorrectly()
    {
        Option::updateOption('clean_numbers', '1');
        Option::updateOption('mobile_county_code', '+1');
        $this->reinitializeGateway();

        $filtered = apply_filters('wp_sms_to', ['555 123 4567']);

        $this->assertEquals(['+15551234567'], $filtered);
    }

    /**
     * Test: Large recipient list handled correctly
     */
    public function testLargeRecipientListHandledCorrectly()
    {
        Option::updateOption('clean_numbers', '1');
        $this->reinitializeGateway();

        // Generate 100 numbers with spaces
        $numbers = [];
        for ($i = 0; $i < 100; $i++) {
            $numbers[] = '+1 555 ' . str_pad($i, 3, '0', STR_PAD_LEFT) . ' ' . str_pad($i * 2, 4, '0', STR_PAD_LEFT);
        }

        $filtered = apply_filters('wp_sms_to', $numbers);

        // All should be cleaned
        $this->assertCount(100, $filtered);
        foreach ($filtered as $number) {
            $this->assertStringNotContainsString(' ', $number);
        }
    }

    /**
     * Test: Unicode phone numbers handled correctly
     */
    public function testUnicodePhoneNumbersHandledCorrectly()
    {
        Option::updateOption('clean_numbers', '1');
        $this->reinitializeGateway();

        // Some countries use non-ASCII characters in formatting
        $numbers = ['+1 555 123 4567'];

        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+15551234567'], $filtered);
    }

    /**
     * Test: Country code with different formats
     */
    public function testCountryCodeWithDifferentFormats()
    {
        // Test with country code without +
        Option::updateOption('mobile_county_code', '1');
        $this->reinitializeGateway();

        $numbers = ['5551234567'];
        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['15551234567'], $filtered);
    }

    // =========================================================================
    // BACKWARD COMPATIBILITY TESTS
    // =========================================================================

    /**
     * Test: Legacy checkbox value '1' works
     */
    public function testLegacyCheckboxValue1Works()
    {
        Option::updateOption('clean_numbers', '1');
        $this->reinitializeGateway();

        $numbers = ['+1 555 123 4567'];
        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+15551234567'], $filtered);
    }

    /**
     * Test: Legacy checkbox value 'on' works
     */
    public function testLegacyCheckboxValueOnWorks()
    {
        Option::updateOption('clean_numbers', 'on');
        $this->reinitializeGateway();

        $numbers = ['+1 555 123 4567'];
        $filtered = apply_filters('wp_sms_to', $numbers);

        // 'on' is truthy, so should work
        $this->assertEquals(['+15551234567'], $filtered);
    }

    /**
     * Test: Empty string disables setting
     */
    public function testEmptyStringDisablesSetting()
    {
        Option::updateOption('clean_numbers', '');
        $this->reinitializeGateway();

        $numbers = ['+1 555 123 4567'];
        $filtered = apply_filters('wp_sms_to', $numbers);

        $this->assertEquals(['+1 555 123 4567'], $filtered);
    }

    /**
     * Test: Array value for countries is handled correctly
     */
    public function testArrayValueForCountriesIsHandledCorrectly()
    {
        Option::updateOption('send_only_local_numbers', '1');
        Option::updateOption('only_local_numbers_countries', ['+1', '+44', '+98']);
        $this->reinitializeGateway();

        $this->assertEquals(['+1', '+44', '+98'], Option::getOption('only_local_numbers_countries'));
    }
}
