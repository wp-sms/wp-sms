<?php

namespace WSms\Support;

defined('ABSPATH') || exit;

class DateFormatter
{
    public static function siteFormat(): string
    {
        return get_option('date_format') . ' ' . get_option('time_format');
    }

    public static function formatTimestamp(string $datetime): string
    {
        if ($datetime === '') {
            return '';
        }

        return wp_date(self::siteFormat(), strtotime($datetime));
    }
}
