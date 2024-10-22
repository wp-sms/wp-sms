<?php

use WP_SMS\Components\NumberParser;
use WP_SMS\Helper;

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
        $numberParser = new NumberParser('01 2 3-45sa(678)910');

        $this->assertEquals($numberParser->getNormalizedNumber(), '12345678910');
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
}
