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
}
