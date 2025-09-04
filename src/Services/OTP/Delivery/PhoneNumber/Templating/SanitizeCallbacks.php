<?php

namespace WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating;

use WP_SMS\Utils\Sanitizer as UtilSanitizer;

class SanitizeCallbacks
{
    private const MAX_LEN = 2000;

    /**
     * @param $value
     * @return string
     */
    public static function body($value): string
    {
        $value = is_string($value) ? $value : '';
        $value = UtilSanitizer::sanitizeBody($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, self::MAX_LEN);
        }
        return substr($value, 0, self::MAX_LEN);
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
