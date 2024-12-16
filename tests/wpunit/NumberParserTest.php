<?php

use WP_SMS\Components\NumberParser;
use WP_SMS\Helper;
use WP_SMS\Option;

class NumberParserTest extends \Codeception\TestCase\WPTestCase
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

    public function testNormalizedNumber()
    {
        $testCases = [
            ['01 2 3-45sa(678)910', '12345678910'],
            ['+1-234-567-8900', '+12345678900'],
            ['00123456789', '123456789'],
            ['  +1 (234) 567-8900  ', '+12345678900'],
            ['', ''],
            ['abc123def456', '123456']
        ];

        foreach ($testCases as [$input, $expected]) {
            $numberParser = new NumberParser($input);
            $this->assertEquals($expected, $numberParser->getNormalizedNumber());
        }
    }

    public function testValidNumericNumber()
    {
        $numberParser = new NumberParser('+1111111111');
        $validNumber  = $numberParser->getValidNumber();

        $this->assertEquals($validNumber, '+1111111111');
    }

    public function testInvalidNonNumericNumber()
    {
        $numberParser = new NumberParser('+hello');
        $validNumber  = $numberParser->getValidNumber();

        $this->assertWPError($validNumber);
        $this->assertStringContainsString($validNumber->get_error_code(), 'invalid_number');
    }

    public function testNumberLength()
    {
        $numberParser = new NumberParser('+123');
        $validNumber  = $numberParser->getValidNumber();

        $this->assertWPError($validNumber);
        $this->assertStringContainsString($validNumber->get_error_code(), 'invalid_length');

        $this->assertTrue($numberParser->isLengthValid('+81757317397'));
    }

    public function testCountryCodeValidation()
    {
        $numberParser = new NumberParser('+81757317397');
        $this->assertEquals($numberParser->getValidNumber(), '+81757317397');

        Option::updateOption('international_mobile', true);

        // Assume invalid country code
        $numberParser = new NumberParser('+99957317397');
        $validNumber  = $numberParser->getValidNumber();

        $this->assertWPError($validNumber);
        $this->assertStringContainsString($validNumber->get_error_code(), 'invalid_country_code');
    }

    public function testDuplicateNumberInUserMeta()
    {
        add_user_meta(1, Helper::getUserMobileFieldName(), '+1111111111');
        $validNumber = NumberParser::isDuplicateInUsermeta('+1111111111');

        $this->assertTrue($validNumber);
    }

    public function testPrepareMobileNumberQuery()
    {
        // Test number without plus
        $result = NumberParser::prepareMobileNumberQuery('1234567890');        
        $this->assertEqualsCanonicalizing([
            '1234567890',
            '+1234567890',
            '234567890'
        ], $result);

        // Test number with plus
        $result = NumberParser::prepareMobileNumberQuery('+1234567890');
        $this->assertEqualsCanonicalizing([
            '+1234567890',
            '1234567890',
            '234567890'
        ], $result);
    }
}
