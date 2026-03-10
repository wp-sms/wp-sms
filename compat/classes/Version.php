<?php

namespace WP_SMS;

// @deprecated Legacy shim.

class Version
{
    public static function pro_is_active()
    {
        return defined('WP_SMS_PREMIUM_FILE');
    }

    public static function pro_version()
    {
        return '';
    }
}
