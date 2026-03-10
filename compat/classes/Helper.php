<?php

namespace WP_SMS;

// @deprecated Legacy shim.

class Helper
{
    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
