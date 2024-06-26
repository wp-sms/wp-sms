<?php

class MobileNumberValidityTest extends \Codeception\TestCase\WPTestCase
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function setUp(): void
    {
        // Before...
        parent::setUp();

        // Your set up methods here.
    }

    public function tearDown(): void
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    public function testNumeric()
    {
        $validity = \WP_SMS\Helper::checkMobileNumberValidity('+1111111111');

        $this->assertTrue($validity);
    }

    public function testNotNumeric()
    {
        $validity = \WP_SMS\Helper::checkMobileNumberValidity('+hello');

        $this->assertInstanceOf(WP_Error::class, $validity);
        $this->assertStringContainsString($validity->get_error_code(), 'invalid_number');
    }

    public function testDuplicateNumberInUserMeta()
    {
        add_user_meta(1, \WP_SMS\Helper::getUserMobileFieldName(), '+1111111111');

        $validity = \WP_SMS\Helper::checkMobileNumberValidity('+1111111111');

        $this->assertInstanceOf(WP_Error::class, $validity);
        $this->assertStringContainsString($validity->get_error_code(), 'is_duplicate');
    }

    public function testAppendCountryCodeForUs()
    {
        $gateway                                = new WP_SMS\Gateway();
        $gateway->options['mobile_county_code'] = '+1';
        $correctNumberFormat                    = '+12025550171';
        $numbers                                = [
            '2025550171', // Output: +12025550171, add +1
            '02025550171', // Output: +12025550171, remove 0 and add +1
            '002025550171', // Output: +12025550171, remove 00 and add +1
            '+12025550171', // Output: +12025550171, do nothing
        ];

        $finalNumbers = $gateway->applyCountryCode($numbers);

        foreach ($finalNumbers as $number) {
            $this->assertStringContainsString($correctNumberFormat, $number);
        }
    }

    public function testAppendCountryCodeForUae()
    {
        $gateway                                = new WP_SMS\Gateway();
        $gateway->options['mobile_county_code'] = '+971';
        $correctNumberFormat                    = '+971553401018';

        $numbers = [
            '553401018', // Output: +971553401018, add +971
            '0553401018', // Output: +971553401018, remove 0 and add +971
            '00553401018', //Output: +971553401018, remove 00 and add +971
            '+971553401018', //Output: +971553401018, do nothing
        ];

        $finalNumbers = $gateway->applyCountryCode($numbers);

        foreach ($finalNumbers as $number) {
            $this->assertStringContainsString($correctNumberFormat, $number);
        }
    }

    public function testAppendCountryCodeForJapan()
    {
        $gateway                                = new WP_SMS\Gateway();
        $gateway->options['mobile_county_code'] = '+81';
        $correctNumberFormat                    = '+81757317397';

        $numbers = [
            '757317397', // Output: +81757317397, add +81
            '0757317397', // Output: +81757317397, remove 0 and add +81
            '00757317397', // Output: +81757317397, remove 00 and add +81
            '+81757317397', // Output: +81757317397, do nothing
        ];

        $finalNumbers = $gateway->applyCountryCode($numbers);

        foreach ($finalNumbers as $number) {
            $this->assertStringContainsString($correctNumberFormat, $number);
        }
    }

    public function testValidDialCodes()
    {
        $allCounties = wp_sms_countries()->getCountryNamesByDialCode();

        $this->assertEquals($allCounties['+81'], 'Japan');
        // $this->assertEquals($allCounties['+44'], 'United Kinsgdom (UK)');
        $this->assertEquals($allCounties['+44'], 'United Kingdom (UK)');
        $this->assertEquals($allCounties['+49'], 'Germany');
    }

    public function testCountriesWithMultipleDialCodes()
    {
        $allDialCodes = wp_sms_countries()->getAllDialCodesByCode();

        $this->assertTrue(in_array('+1939', $allDialCodes['PR']));
        $this->assertTrue(!in_array('+34', $allDialCodes['FR']));
        $this->assertTrue(in_array('+33', $allDialCodes['FR']));
        $this->assertTrue(in_array('+1849', $allDialCodes['DO']));
    }

    public function testCountriesWithSimilarDialCodes()
    {
        $countriesMerged = wp_sms_countries()->getCountriesMerged();

        $this->assertTrue($countriesMerged['+1'] === 'Canada & United States (USA) (+1)');

        $this->assertStringContainsString('Guernsey (Guernési)', $countriesMerged['+44']);
        $this->assertStringContainsString('Isle of Man', $countriesMerged['+44']);
        $this->assertStringContainsString('Jersey (Jèrri)', $countriesMerged['+44']);
        $this->assertStringContainsString('United Kingdom (UK)', $countriesMerged['+44']);

        $this->assertStringContainsString('Bouvetøya', $countriesMerged['+47']);
        $this->assertStringContainsString('Norge', $countriesMerged['+47']);
        $this->assertStringContainsString('Svalbard and Jan Mayen', $countriesMerged['+47']);

        $this->assertStringContainsString('Cocos (Keeling) Islands (Pulu Kokos (Keeling))', $countriesMerged['+61']);

        $this->assertStringContainsString('Terres australes et antarctiques françaises', $countriesMerged['+262']);
        $this->assertStringContainsString('Mayotte', $countriesMerged['+262']);
        $this->assertStringContainsString('La Réunion', $countriesMerged['+262']);
    }
}
