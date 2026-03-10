<?php

namespace WP_SMS;

// @deprecated Legacy shim — prevents fatal errors in old add-ons.

class Version
{
    public static function pro_is_active()
    {
        return defined('WP_SMS_PREMIUM_FILE');
    }

    public static function pro_is_installed($pluginPath = '')
    {
        return false;
    }

    public static function pro_version()
    {
        return '';
    }

    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
