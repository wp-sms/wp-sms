<?php

namespace unit;

use WP_Error;
use WP_SMS\Gateway;
use WP_SMS\Helper;
use WP_UnitTestCase;

class MobileNumberValidityTest extends WP_UnitTestCase
{
    protected $faker;

    /**
     * Setup before each test.
     */
    public function setUp(): void
    {
        parent::setUp();

        // Initialize Faker for generating test data
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Test valid numeric mobile number.
     */
    public function testNumeric()
    {
        $validity = Helper::checkMobileNumberValidity('+1111111111');
        $this->assertTrue($validity);
    }

    /**
     * Test invalid non-numeric mobile number.
     */
    public function testNotNumeric()
    {
        $validity = Helper::checkMobileNumberValidity('+hello');
        $this->assertInstanceOf(WP_Error::class, $validity);
        $this->assertStringContainsString('invalid_number', $validity->get_error_code());
    }

    /**
     * Test duplicate mobile number in user metadata.
     */
    public function testDuplicateNumberInUserMeta()
    {
        $number = '+1111111111';
        add_user_meta(1, Helper::getUserMobileFieldName(), $number);

        $validity = Helper::checkMobileNumberValidity($number);
        $this->assertInstanceOf(WP_Error::class, $validity);
        $this->assertStringContainsString('is_duplicate', $validity->get_error_code());
    }

    /**
     * Test appending country code for US numbers.
     */
    public function testAppendCountryCodeForUs()
    {
        $this->runCountryCodeTest('+1', '+12025550171', [
            '2025550171',
            '02025550171',
            '002025550171',
            '+12025550171',
        ]);
    }

    /**
     * Test appending country code for UAE numbers.
     */
    public function testAppendCountryCodeForUae()
    {
        $this->runCountryCodeTest('+971', '+971553401018', [
            '553401018',
            '0553401018',
            '00553401018',
            '+971553401018',
        ]);
    }

    /**
     * Test appending country code for Japan numbers.
     */
    public function testAppendCountryCodeForJapan()
    {
        $this->runCountryCodeTest('+81', '+81757317397', [
            '757317397',
            '0757317397',
            '00757317397',
            '+81757317397',
        ]);
    }

    /**
     * Helper method to test appending country code.
     */
    protected function runCountryCodeTest($countryCode, $correctFormat, $numbers)
    {
        $gateway                                = new Gateway();
        $gateway->options['mobile_county_code'] = $countryCode;

        $finalNumbers = $gateway->applyCountryCode($numbers);
        foreach ($finalNumbers as $number) {
            $this->assertStringContainsString($correctFormat, $number);
        }
    }

    /**
     * Test valid dial codes.
     */
    public function testValidDialCodes()
    {
        $countries = wp_sms_countries()->getCountryNamesByDialCode();

        $this->assertEquals('Japan', $countries['+81']);
        $this->assertEquals('United Kingdom (UK)', $countries['+44']);
        $this->assertEquals('Germany', $countries['+49']);
    }

    /**
     * Test countries with multiple dial codes.
     */
    public function testCountriesWithMultipleDialCodes()
    {
        $dialCodes = wp_sms_countries()->getAllDialCodesByCode();

        $this->assertTrue(in_array('+1939', $dialCodes['PR']));
        $this->assertFalse(in_array('+34', $dialCodes['FR']));
        $this->assertTrue(in_array('+33', $dialCodes['FR']));
        $this->assertTrue(in_array('+1849', $dialCodes['DO']));
    }

    /**
     * Test countries with similar dial codes.
     */
    public function testCountriesWithSimilarDialCodes()
    {
        $countriesMerged = wp_sms_countries()->getCountriesMerged();

        $this->assertEquals('Canada & United States (USA) (+1)', $countriesMerged['+1']);

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
