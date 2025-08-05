<?php

namespace WP_SMS\Utils;

/**
 * Date utility class for standardized date handling
 * All dates are stored and compared in UTC to avoid timezone issues
 */
class DateUtils
{
    /**
     * Get current UTC timestamp
     * 
     * @return int
     */
    public static function getCurrentUtcTimestamp()
    {
        return time();
    }

    /**
     * Get current UTC datetime string for database storage
     * 
     * @return string Format: Y-m-d H:i:s
     */
    public static function getCurrentUtcDateTime()
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Convert timestamp to UTC datetime string for database storage
     * 
     * @param int $timestamp
     * @return string Format: Y-m-d H:i:s
     */
    public static function timestampToUtcDateTime($timestamp)
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Convert UTC datetime string from database to timestamp
     * 
     * @param string $utcDateTime Format: Y-m-d H:i:s
     * @return int
     */
    public static function utcDateTimeToTimestamp($utcDateTime)
    {
        return strtotime($utcDateTime . ' UTC');
    }

    /**
     * Check if a UTC datetime string is in the future
     * 
     * @param string $utcDateTime Format: Y-m-d H:i:s
     * @return bool
     */
    public static function isFuture($utcDateTime)
    {
        $timestamp = self::utcDateTimeToTimestamp($utcDateTime);
        return $timestamp > self::getCurrentUtcTimestamp();
    }

    /**
     * Check if a UTC datetime string is in the past
     * 
     * @param string $utcDateTime Format: Y-m-d H:i:s
     * @return bool
     */
    public static function isPast($utcDateTime)
    {
        $timestamp = self::utcDateTimeToTimestamp($utcDateTime);
        return $timestamp < self::getCurrentUtcTimestamp();
    }

    /**
     * Get seconds remaining until a UTC datetime
     * 
     * @param string $utcDateTime Format: Y-m-d H:i:s
     * @return int
     */
    public static function getSecondsRemaining($utcDateTime)
    {
        $timestamp = self::utcDateTimeToTimestamp($utcDateTime);
        $current = self::getCurrentUtcTimestamp();
        return max(0, $timestamp - $current);
    }

    /**
     * Add seconds to a UTC datetime string
     * 
     * @param string $utcDateTime Format: Y-m-d H:i:s
     * @param int $seconds
     * @return string Format: Y-m-d H:i:s
     */
    public static function addSeconds($utcDateTime, $seconds)
    {
        $timestamp = self::utcDateTimeToTimestamp($utcDateTime);
        return self::timestampToUtcDateTime($timestamp + $seconds);
    }

    /**
     * Get SQL condition for unexpired records using UTC
     * 
     * @param string $columnName
     * @return string
     */
    public static function getUnexpiredSqlCondition($columnName = 'expires_at')
    {
        return "{$columnName} > UTC_TIMESTAMP()";
    }

    /**
     * Get SQL condition for expired records using UTC
     * 
     * @param string $columnName
     * @return string
     */
    public static function getExpiredSqlCondition($columnName = 'expires_at')
    {
        return "{$columnName} <= UTC_TIMESTAMP()";
    }
} 