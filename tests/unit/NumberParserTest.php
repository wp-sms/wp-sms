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
        $persianNumber = '+۹۸۹۱۲۳۴۵۶۷۸۹'; // Persian numerals for +989123456789
        $numberParser = new NumberParser($persianNumber);

        $expected = '+989123456789';
        $this->assertEquals($expected, $numberParser->getValidNumber());
    }

    /**
     * Test that getValidNumber() properly handles Arabic numerals.
     */
    public function testGetValidNumberWithArabicNumerals()
    {
        $arabicNumber = '+٩٨٩١٢٣٤٥٦٧٨٩'; // Arabic numerals for +989123456789
        $numberParser = new NumberParser($arabicNumber);

        $expected = '+989123456789';
        $this->assertEquals($expected, $numberParser->getValidNumber());
    }

    /**
     * Test that getValidNumber() works with mixed numerals.
     */
    public function testGetValidNumberWithMixedNumerals()
    {
        $mixedNumber = '+۹۸9١٢3٤56۷89'; // Mixed Persian, Arabic and English numerals
        $numberParser = new NumberParser($mixedNumber);

        $expected = '+989123456789';
        $this->assertEquals($expected, $numberParser->getValidNumber());
    }

    /**
     * Test that getValidNumber() works with non-numeral characters.
     */
    public function testGetValidNumberWithNonNumeralCharacters()
    {
        $numberWithText = 'Phone: +۹۸(۹۱۲)۳۴۵-۶۷۸۹';
        $numberParser = new NumberParser($numberWithText);

        $expected = '+989123456789';
        $this->assertEquals($expected, $numberParser->getValidNumber());
    }

    /**
     * Test that getValidNumber() validates length after numeral conversion.
     */
    public function testGetValidNumberLengthAfterNumeralConversion()
    {
        // This Persian number would be too short after conversion
        $shortNumber = '+۹۸۹۱۲'; // Converts to +98912 (5 digits)
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
        $invalidNumber = '+۹۸۹hello۱۲۳'; // After conversion: +989hello123
        $numberParser = new NumberParser($invalidNumber);

        $result = $numberParser->getValidNumber();
        $this->assertWPError($result);
    }
}
