<?php

namespace WP_SMS\User;

// @deprecated Legacy shim.

class MobileFieldManager
{
    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
