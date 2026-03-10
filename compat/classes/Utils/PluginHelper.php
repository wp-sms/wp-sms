<?php

namespace WP_SMS\Utils;

// @deprecated Legacy shim.

class PluginHelper
{
    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
