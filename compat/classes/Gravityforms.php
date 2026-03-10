<?php

namespace WP_SMS;

// @deprecated Legacy shim.

class Gravityforms
{
    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
