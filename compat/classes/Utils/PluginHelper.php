<?php

namespace WP_SMS\Utils;

// @deprecated Legacy shim.

class PluginHelper
{
    public function __call($name, $arguments)
    {
        return null;
    }

    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
