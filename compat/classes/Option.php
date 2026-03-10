<?php

namespace WP_SMS;

// @deprecated Legacy shim.

class Option
{
    public static function getOption($key = '', $default = false)
    {
        return $default;
    }

    public static function getOptions()
    {
        return [];
    }
}
