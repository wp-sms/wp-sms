<?php

namespace unit;

use WP_SMS\Components\NumberParser;
use WP_SMS\Helper;
use WP_SMS\Settings\Option;
use WP_UnitTestCase;

class NumberParserTest extends WP_UnitTestCase
{
    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();
        // Initialize Faker
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Test normalized number formatting.
     */
    public function testNormalizedNumber()
    {
        $rawNumber    = $this->faker->numerify('01 2 3-###-678(910)');
        $numberParser = new NumberParser($rawNumber);

        $expectedNormalized = ltrim(preg_replace('/\D/', '', $rawNumber), '0');
        $this->assertEquals($expectedNormalized, $numberParser->getNormalizedNumber());
    }

    /**
     * Test validation of a valid numeric number.
     */
    public function testValidNumericNumber()
    {
        $validNumber  = $this->faker->numerify('+1##########'); // Generate a valid phone number
        $numberParser = new NumberParser($validNumber);

        $this->assertEquals($validNumber, $numberParser->getValidNumber());
    }

    /**
     * Test validation of an invalid non-numeric number.
     */
    public function testInvalidNonNumericNumber()
    {
        $invalidNumber = '+hello' . $this->faker->word; // Non-numeric invalid number
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
        $shortNumber  = $this->faker->numerify('+123'); // Too short
        $numberParser = new NumberParser($shortNumber);
        $validNumber  = $numberParser->getValidNumber();

        $this->assertWPError($validNumber);
        $this->assertStringContainsString('invalid_length', $validNumber->get_error_code());

        $validLengthNumber = $this->faker->numerify('+###########'); // Correct length
        $this->assertTrue($numberParser->isLengthValid($validLengthNumber));
    }

    /**
     * Test country code validation.
     */
    public function testCountryCodeValidation()
    {
        $validNumber  = $this->faker->numerify('+817########');
        $numberParser = new NumberParser($validNumber);
        $this->assertEquals($validNumber, $numberParser->getValidNumber());

        Option::updateOption('international_mobile', true);

        // Assume invalid country code
        $invalidNumber = '+999' . $this->faker->numerify('########');
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
        $duplicateNumber = $this->faker->numerify('+1##########'); // Generate a valid phone number
        add_user_meta(1, Helper::getUserMobileFieldName(), $duplicateNumber);

        $isDuplicate = NumberParser::isDuplicateInUsermeta($duplicateNumber);
        $this->assertTrue($isDuplicate);
    }
}
