<?php

namespace unit;

use WP_SMS\Helper;
use WP_UnitTestCase;

class HelperTest extends WP_UnitTestCase
{
    /**
     * Test isJson returns true for valid JSON string.
     */
    public function testIsJsonReturnsTrueForValidJson()
    {
        $this->assertTrue(Helper::isJson('{"name":"John","age":30}'));
    }

    /**
     * Test isJson returns true for valid JSON array.
     */
    public function testIsJsonReturnsTrueForValidJsonArray()
    {
        $this->assertTrue(Helper::isJson('[1,2,3]'));
    }

    /**
     * Test isJson returns true for empty JSON object.
     */
    public function testIsJsonReturnsTrueForEmptyJsonObject()
    {
        $this->assertTrue(Helper::isJson('{}'));
    }

    /**
     * Test isJson returns true for empty JSON array.
     */
    public function testIsJsonReturnsTrueForEmptyJsonArray()
    {
        $this->assertTrue(Helper::isJson('[]'));
    }

    /**
     * Test isJson returns false for invalid JSON.
     */
    public function testIsJsonReturnsFalseForInvalidJson()
    {
        $this->assertFalse(Helper::isJson('{name:John}'));
    }

    /**
     * Test isJson returns false for plain string.
     */
    public function testIsJsonReturnsFalseForPlainString()
    {
        $this->assertFalse(Helper::isJson('Hello World'));
    }

    /**
     * Test isJson returns true for JSON null.
     */
    public function testIsJsonReturnsTrueForJsonNull()
    {
        $this->assertTrue(Helper::isJson('null'));
    }

    /**
     * Test isJson returns true for JSON boolean.
     */
    public function testIsJsonReturnsTrueForJsonBoolean()
    {
        $this->assertTrue(Helper::isJson('true'));
        $this->assertTrue(Helper::isJson('false'));
    }

    /**
     * Test sanitizeMobileNumber trims whitespace.
     */
    public function testSanitizeMobileNumberTrimsWhitespace()
    {
        $result = Helper::sanitizeMobileNumber('  +1234567890  ');
        $this->assertEquals('+1234567890', $result);
    }

    /**
     * Test sanitizeMobileNumber handles normal number.
     */
    public function testSanitizeMobileNumberHandlesNormalNumber()
    {
        $result = Helper::sanitizeMobileNumber('+12025551234');
        $this->assertEquals('+12025551234', $result);
    }

    /**
     * Test normalizeNumber removes non-digit characters.
     */
    public function testNormalizeNumberRemovesNonDigits()
    {
        $result = Helper::normalizeNumber('+1-234-567-890');
        $this->assertStringNotContainsString('-', $result);
    }

    /**
     * Test normalizeNumber keeps plus sign.
     */
    public function testNormalizeNumberKeepsPlusSign()
    {
        $result = Helper::normalizeNumber('+1234567890');
        $this->assertStringStartsWith('+', $result);
    }

    /**
     * Test removeDuplicateNumbers removes duplicates.
     */
    public function testRemoveDuplicateNumbersRemovesDuplicates()
    {
        $numbers = ['+1234567890', '+1234567890', '+0987654321'];
        $result  = Helper::removeDuplicateNumbers($numbers);

        $this->assertCount(2, $result);
    }

    /**
     * Test removeDuplicateNumbers trims numbers.
     */
    public function testRemoveDuplicateNumbersTrimsNumbers()
    {
        $numbers = [' +1234567890 ', '+1234567890'];
        $result  = Helper::removeDuplicateNumbers($numbers);

        $this->assertCount(1, $result);
    }

    /**
     * Test removeNumbersPrefix removes single prefix.
     */
    public function testRemoveNumbersPrefixRemovesSinglePrefix()
    {
        $numbers = ['+1234567890', '+0987654321'];
        $result  = Helper::removeNumbersPrefix(['+'], $numbers);

        $this->assertEquals('1234567890', $result[0]);
        $this->assertEquals('0987654321', $result[1]);
    }

    /**
     * Test removeNumbersPrefix removes multiple prefixes.
     */
    public function testRemoveNumbersPrefixRemovesMultiplePrefixes()
    {
        $numbers = ['+12025551234', '0012025551234', '2025551234'];
        $result  = Helper::removeNumbersPrefix(['+1', '001'], $numbers);

        $this->assertEquals('2025551234', $result[0]);
        $this->assertEquals('2025551234', $result[1]);
        $this->assertEquals('2025551234', $result[2]);
    }

    /**
     * Test removeNumbersPrefix handles empty prefix array.
     */
    public function testRemoveNumbersPrefixHandlesEmptyPrefixArray()
    {
        $numbers = ['+1234567890'];
        $result  = Helper::removeNumbersPrefix([], $numbers);

        $this->assertEquals('+1234567890', $result[0]);
    }

    /**
     * Test checkMobileNumberValidity with valid number.
     */
    public function testCheckMobileNumberValidityWithValidNumber()
    {
        $result = Helper::checkMobileNumberValidity('+12025551234');

        // Should return true or WP_Error if duplicate
        $this->assertTrue($result === true || is_wp_error($result));
    }

    /**
     * Test checkMobileNumberValidity with invalid number.
     */
    public function testCheckMobileNumberValidityWithInvalidNumber()
    {
        $result = Helper::checkMobileNumberValidity('abc');

        $this->assertWPError($result);
    }

    /**
     * Test checkMobileNumberValidity with short number.
     */
    public function testCheckMobileNumberValidityWithShortNumber()
    {
        $result = Helper::checkMobileNumberValidity('+123');

        $this->assertWPError($result);
    }

    /**
     * Test prepareMobileNumberQuery returns array.
     */
    public function testPrepareMobileNumberQueryReturnsArray()
    {
        $result = Helper::prepareMobileNumberQuery('+12025551234');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test prepareMobileNumberQuery includes normalized number.
     */
    public function testPrepareMobileNumberQueryIncludesNormalizedNumber()
    {
        $result = Helper::prepareMobileNumberQuery('+1-202-555-1234');

        // Should include the normalized version
        $this->assertTrue(
            in_array('12025551234', $result) ||
            in_array('+12025551234', $result)
        );
    }

    /**
     * Test prepareMobileNumberQuery removes duplicates.
     */
    public function testPrepareMobileNumberQueryRemovesDuplicates()
    {
        $result = Helper::prepareMobileNumberQuery('+12025551234');

        $this->assertEquals(count($result), count(array_unique($result)));
    }
}
