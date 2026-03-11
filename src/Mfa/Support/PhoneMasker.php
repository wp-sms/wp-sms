<?php

namespace WSms\Mfa\Support;

class PhoneMasker
{
    /**
     * Mask a phone number for display.
     *
     * +12025551234 → +12*****1234
     */
    public static function mask(string $phone): string
    {
        $len = mb_strlen($phone);

        if ($len <= 4) {
            return $phone;
        }

        // Keep first 3 chars (e.g. "+12") and last 4 digits visible.
        $visibleStart = 3;
        $visibleEnd = 4;
        $maskLen = $len - $visibleStart - $visibleEnd;

        if ($maskLen <= 0) {
            return $phone;
        }

        return mb_substr($phone, 0, $visibleStart)
            . str_repeat('*', $maskLen)
            . mb_substr($phone, -$visibleEnd);
    }
}
