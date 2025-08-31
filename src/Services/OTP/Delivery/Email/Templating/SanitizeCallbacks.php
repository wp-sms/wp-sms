<?php

namespace WP_SMS\Services\OTP\Delivery\Email\Templating;

use WP_SMS\Utils\Sanitizer as UtilSanitizer;

class SanitizeCallbacks
{
    /**
     *
     */
    private const MAX_LEN = 5000;

    /**
     * @param $value
     * @return string
     */
    public static function subject($value): string
    {
        $value = is_string($value) ? $value : '';
        $value = UtilSanitizer::sanitizeSubject($value);
        return function_exists('mb_substr') ? mb_substr($value, 0, self::MAX_LEN) : substr($value, 0, self::MAX_LEN);
    }

    /**
     * @param $value
     * @return string
     */
    public static function body($value): string
    {
        $value = is_string($value) ? $value : '';
        $value = UtilSanitizer::sanitizeBody($value);
        return function_exists('mb_substr') ? mb_substr($value, 0, self::MAX_LEN) : substr($value, 0, self::MAX_LEN);
    }

    /**
     * @param $value
     * @return bool
     */
    public static function revert($value): bool
    {
        return (bool)$value;
    }
}
