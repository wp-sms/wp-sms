<?php

namespace unit;

use WP_SMS\Components\DateTime;
use WP_UnitTestCase;

class DateTimeTest extends WP_UnitTestCase
{
    /**
     * Test get method returns formatted date.
     */
    public function testGetReturnsFormattedDate()
    {
        $result = DateTime::get('2024-01-15', 'Y-m-d');
        $this->assertEquals('2024-01-15', $result);
    }

    /**
     * Test get method with different format.
     */
    public function testGetWithDifferentFormat()
    {
        $result = DateTime::get('2024-01-15', 'd/m/Y');
        $this->assertEquals('15/01/2024', $result);
    }

    /**
     * Test get method with timestamp.
     */
    public function testGetWithTimestamp()
    {
        $timestamp = strtotime('2024-06-20');
        $result    = DateTime::get($timestamp, 'Y-m-d');
        $this->assertEquals('2024-06-20', $result);
    }

    /**
     * Test get method defaults to now.
     */
    public function testGetDefaultsToNow()
    {
        $result   = DateTime::get();
        $expected = date('Y-m-d');
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getStartOfWeek returns name by default.
     */
    public function testGetStartOfWeekReturnsName()
    {
        $result = DateTime::getStartOfWeek();
        $validDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $this->assertContains($result, $validDays);
    }

    /**
     * Test getStartOfWeek returns number.
     */
    public function testGetStartOfWeekReturnsNumber()
    {
        $result = DateTime::getStartOfWeek('number');
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThanOrEqual(6, $result);
    }

    /**
     * Test getStartOfWeek returns both.
     */
    public function testGetStartOfWeekReturnsBoth()
    {
        $result = DateTime::getStartOfWeek('both');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('number', $result);
        $this->assertArrayHasKey('name', $result);
    }

    /**
     * Test getDateFormat returns string.
     */
    public function testGetDateFormatReturnsString()
    {
        $result = DateTime::getDateFormat();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test getTimeFormat returns string.
     */
    public function testGetTimeFormatReturnsString()
    {
        $result = DateTime::getTimeFormat();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test getTimezone returns DateTimeZone.
     */
    public function testGetTimezoneReturnsDateTimeZone()
    {
        $result = DateTime::getTimezone();
        $this->assertInstanceOf(\DateTimeZone::class, $result);
    }

    /**
     * Test getDateTimeFormat combines date and time.
     */
    public function testGetDateTimeFormatCombinesDateAndTime()
    {
        $dateFormat = DateTime::getDateFormat();
        $timeFormat = DateTime::getTimeFormat();
        $result     = DateTime::getDateTimeFormat();

        $this->assertStringContainsString($dateFormat, $result);
        $this->assertStringContainsString($timeFormat, $result);
    }

    /**
     * Test getDateTimeFormat with custom separator.
     */
    public function testGetDateTimeFormatWithCustomSeparator()
    {
        $result = DateTime::getDateTimeFormat(' | ');
        $this->assertStringContainsString(' | ', $result);
    }

    /**
     * Test subtract subtracts days correctly.
     */
    public function testSubtractSubtractsDaysCorrectly()
    {
        $result = DateTime::subtract('2024-01-15', 5);
        $this->assertEquals('2024-01-10', $result);
    }

    /**
     * Test subtract with different format.
     */
    public function testSubtractWithDifferentFormat()
    {
        $result = DateTime::subtract('2024-01-15', 5, 'd/m/Y');
        $this->assertEquals('10/01/2024', $result);
    }

    /**
     * Test subtract with large number of days.
     */
    public function testSubtractWithLargeNumberOfDays()
    {
        $result = DateTime::subtract('2024-01-15', 30);
        $this->assertEquals('2023-12-16', $result);
    }

    /**
     * Test format with basic date string.
     */
    public function testFormatWithBasicDateString()
    {
        $result = DateTime::format('2024-06-15', ['date_format' => 'Y-m-d']);
        $this->assertEquals('2024-06-15', $result);
    }

    /**
     * Test format with timestamp.
     */
    public function testFormatWithTimestamp()
    {
        $timestamp = strtotime('2024-06-15 10:30:00');
        $result    = DateTime::format($timestamp, ['date_format' => 'Y-m-d']);
        $this->assertEquals('2024-06-15', $result);
    }

    /**
     * Test format includes time when specified.
     */
    public function testFormatIncludesTimeWhenSpecified()
    {
        $result = DateTime::format('2024-06-15 14:30:00', [
            'include_time' => true,
            'date_format'  => 'Y-m-d',
            'time_format'  => 'H:i'
        ]);
        $this->assertStringContainsString('14:30', $result);
    }

    /**
     * Test format excludes year when specified.
     */
    public function testFormatExcludesYearWhenSpecified()
    {
        $result = DateTime::format('2024-06-15', [
            'exclude_year' => true,
            'date_format'  => 'F j, Y'
        ]);
        $this->assertStringNotContainsString('2024', $result);
    }

    /**
     * Test format uses short month when specified.
     */
    public function testFormatUsesShortMonthWhenSpecified()
    {
        $result = DateTime::format('2024-06-15', [
            'short_month' => true,
            'date_format' => 'F j, Y'
        ]);
        $this->assertStringContainsString('Jun', $result);
        $this->assertStringNotContainsString('June', $result);
    }

    /**
     * Test isValidDate returns true for valid date.
     */
    public function testIsValidDateReturnsTrueForValidDate()
    {
        $this->assertTrue(DateTime::isValidDate('2024-01-15'));
        $this->assertTrue(DateTime::isValidDate('2024-12-31'));
        $this->assertTrue(DateTime::isValidDate('2024-06-30'));
    }

    /**
     * Test isValidDate returns false for invalid date.
     */
    public function testIsValidDateReturnsFalseForInvalidDate()
    {
        $this->assertFalse(DateTime::isValidDate('2024-13-01')); // Invalid month
        $this->assertFalse(DateTime::isValidDate('invalid'));
        $this->assertFalse(DateTime::isValidDate(''));
        $this->assertFalse(DateTime::isValidDate('2024-00-15')); // Invalid month (00)
    }

    /**
     * Test isValidDate returns false for wrong format.
     */
    public function testIsValidDateReturnsFalseForWrongFormat()
    {
        $this->assertFalse(DateTime::isValidDate('15-01-2024')); // d-m-Y format
        $this->assertFalse(DateTime::isValidDate('01/15/2024')); // m/d/Y format
        $this->assertFalse(DateTime::isValidDate('2024/01/15')); // Y/m/d format
    }

    /**
     * Test isTodayOrFutureDate returns true for today.
     */
    public function testIsTodayOrFutureDateReturnsTrueForToday()
    {
        $today = date('Y-m-d');
        $this->assertTrue(DateTime::isTodayOrFutureDate($today));
    }

    /**
     * Test isTodayOrFutureDate returns true for future date.
     */
    public function testIsTodayOrFutureDateReturnsTrueForFutureDate()
    {
        $futureDate = date('Y-m-d', strtotime('+1 week'));
        $this->assertTrue(DateTime::isTodayOrFutureDate($futureDate));
    }

    /**
     * Test isTodayOrFutureDate returns false for past date.
     */
    public function testIsTodayOrFutureDateReturnsFalseForPastDate()
    {
        $pastDate = date('Y-m-d', strtotime('-1 week'));
        $this->assertFalse(DateTime::isTodayOrFutureDate($pastDate));
    }

    /**
     * Test isTodayOrFutureDate returns false for empty date.
     */
    public function testIsTodayOrFutureDateReturnsFalseForEmptyDate()
    {
        $this->assertFalse(DateTime::isTodayOrFutureDate(''));
        $this->assertFalse(DateTime::isTodayOrFutureDate(null));
    }

    /**
     * Test isTodayOrFutureDate returns false for invalid date.
     */
    public function testIsTodayOrFutureDateReturnsFalseForInvalidDate()
    {
        $this->assertFalse(DateTime::isTodayOrFutureDate('invalid-date'));
    }

    /**
     * Test default date format constant.
     */
    public function testDefaultDateFormatConstant()
    {
        $this->assertEquals('Y-m-d', DateTime::$defaultDateFormat);
    }

    /**
     * Test default time format constant.
     */
    public function testDefaultTimeFormatConstant()
    {
        $this->assertEquals('g:i a', DateTime::$defaultTimeFormat);
    }
}
