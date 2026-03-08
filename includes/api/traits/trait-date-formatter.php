<?php
namespace WP_SMS\Api\Traits;

/**
 * Date Formatter Trait
 *
 * Provides date formatting using WordPress's date_i18n() function
 * to support localized dates (e.g., Persian/Jalali calendars via plugins).
 *
 * @package WP_SMS\Api\Traits
 */
trait DateFormatter
{
    /**
     * Format a date using WordPress's date_i18n() function.
     *
     * This allows third-party plugins (like WP-Parsidate for Persian dates)
     * to hook into the date_i18n filter and transform dates.
     *
     * @param string $date       The date string to format (MySQL format expected).
     * @param bool   $includeTime Whether to include time in the output.
     * @return string Formatted date string, or empty string if invalid.
     */
    protected function formatDateI18n($date, $includeTime = true)
    {
        if (empty($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }

        $dateFormat = get_option('date_format', 'F j, Y');
        $timeFormat = get_option('time_format', 'g:i a');
        $format = $includeTime ? $dateFormat . ', ' . $timeFormat : $dateFormat;

        return date_i18n($format, $timestamp);
    }
}
