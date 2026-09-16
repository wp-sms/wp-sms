<?php

namespace unit;

use WP_SMS\Components\NumberParser;
use WP_SMS\Helper;
use WP_SMS\Option;
use WP_UnitTestCase;

class NumberParserTest extends WP_UnitTestCase
{
    private static $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test normalized number formatting.
     */
    public function testNormalizedNumber()
    {
        $rawNumber    = '01 2 3-456-678(910)';
        $numberParser = new NumberParser($rawNumber);

        $expectedNormalized = ltrim(preg_replace('/\D/', '', $rawNumber), '0');
        $this->assertEquals($expectedNormalized, $numberParser->getNormalizedNumber());
    }

    /**
     * Test validation of a valid numeric number.
     */
    public function testValidNumericNumber()
    {
        $validNumber  = '+12025550' . str_pad(++self::$counter, 3, '0', STR_PAD_LEFT);
        $numberParser = new NumberParser($validNumber);

        $this->assertEquals($validNumber, $numberParser->getValidNumber());
    }

    /**
     * Test validation of an invalid non-numeric number.
     */
    public function testInvalidNonNumericNumber()
    {
        $invalidNumber = '+helloworld';
        $numberParser  = new NumberParser($invalidNumber);
        $validNumber   = $numberParser->getValidNumber();

        $this->assertWPError($validNumber);
        $this->assertStringContainsString('invalid_number', $validNumber->get_error_code());
    }

    /**
     * Test validation of number length.
     */
    public function testNumberLength()
    {
        $shortNumber  = '+123';
        $numberParser = new NumberParser($shortNumber);
        $validNumber  = $numberParser->getValidNumber();

        $this->assertWPError($validNumber);
        $this->assertStringContainsString('invalid_length', $validNumber->get_error_code());

        $validLengthNumber = '+12025550199';
        $this->assertTrue($numberParser->isLengthValid($validLengthNumber));
    }

    /**
     * The Settings UI describes Minimum/Maximum Digits as "excluding country code", so
     * the check must measure the local part even when the caller already included one.
     */
    public function testLengthValidationExcludesCountryCode()
    {
        Option::updateOption('mobile_county_code', '+1');
        Option::updateOption('mobile_terms_minimum', 10);
        Option::updateOption('mobile_terms_maximum', 10);

        $numberParser = new NumberParser('');

        // 10 local digits with the country code already attached.
        $this->assertTrue($numberParser->isLengthValid('+12025550199'));

        // Same 10 local digits without a country code.
        $this->assertTrue($numberParser->isLengthValid('2025550199'));

        // 11 local digits once the country code is excluded — too long.
        $this->assertFalse($numberParser->isLengthValid('+120255501990'));
    }

    /**
     * Test country code validation.
     */
    public function testCountryCodeValidation()
    {
        $validNumber  = '+81712345678';
        $numberParser = new NumberParser($validNumber);
        $this->assertEquals($validNumber, $numberParser->getValidNumber());

        Option::updateOption('international_mobile', true);

        // Assume invalid country code
        $invalidNumber = '+99912345678';
        $numberParser  = new NumberParser($invalidNumber);
        $validNumber   = $numberParser->getValidNumber();

        $this->assertWPError($validNumber);
        $this->assertStringContainsString('invalid_country_code', $validNumber->get_error_code());
    }

    /**
     * Test detection of duplicate numbers in user meta.
     */
    public function testDuplicateNumberInUserMeta()
    {
        $duplicateNumber = '+12025551' . str_pad(++self::$counter, 3, '0', STR_PAD_LEFT);
        add_user_meta(1, Helper::getUserMobileFieldName(), $duplicateNumber);

        $isDuplicate = NumberParser::isDuplicateInUsermeta($duplicateNumber);
        $this->assertTrue($isDuplicate);
    }

    /**
     * Test that getValidNumber() properly handles Persian numerals.
     */
    public function testGetValidNumberWithPersianNumerals()
    {
        $persianNumber = '+۱۲۰۲۵۵۵۰۱۲۳'; // Persian numerals for +12025550123
        $numberParser = new NumberParser($persianNumber);

        $expected = '+12025550123';
        $this->assertEquals($expected, $numberParser->getValidNumber());
    }

    /**
     * Test that getValidNumber() properly handles Arabic numerals.
     */
    public function testGetValidNumberWithArabicNumerals()
    {
        $arabicNumber = '+١٢٠٢٥٥٥٠١٢٣'; // Arabic numerals for +12025550123
        $numberParser = new NumberParser($arabicNumber);

        $expected = '+12025550123';
        $this->assertEquals($expected, $numberParser->getValidNumber());
    }

    /**
     * Test that getValidNumber() works with mixed numerals.
     */
    public function testGetValidNumberWithMixedNumerals()
    {
        $mixedNumber = '+۱20٢٥5٥0۱۲۳'; // Mixed Persian, Arabic and English numerals
        $numberParser = new NumberParser($mixedNumber);

        $expected = '+12025550123';
        $this->assertEquals($expected, $numberParser->getValidNumber());
    }

    /**
     * Test that getValidNumber() works with non-numeral characters.
     */
    public function testGetValidNumberWithNonNumeralCharacters()
    {
        $numberWithText = 'Phone: +۱(۲۰۲)۵۵۵-۰۱۲۳';
        $numberParser = new NumberParser($numberWithText);

        $expected = '+12025550123';
        $this->assertEquals($expected, $numberParser->getValidNumber());
    }

    /**
     * Test that getValidNumber() validates length after numeral conversion.
     */
    public function testGetValidNumberLengthAfterNumeralConversion()
    {
        // This Persian number would be too short after conversion
        $shortNumber = '+۱۲۰'; // Converts to +120 (3 digits)
        $numberParser = new NumberParser($shortNumber);

        $result = $numberParser->getValidNumber();
        $this->assertWPError($result);
        $this->assertStringContainsString('invalid_length', $result->get_error_code());
    }

    /**
     * Test that getValidNumber() validates format after numeral conversion.
     */
    public function testGetValidNumberFormatAfterNumeralConversion()
    {
        // This contains non-numeric characters that should be removed
        $invalidNumber = '+۱۲۰hello۱۲۳'; // After conversion: +120hello123
        $numberParser = new NumberParser($invalidNumber);

        $result = $numberParser->getValidNumber();
        $this->assertWPError($result);
    }
}
