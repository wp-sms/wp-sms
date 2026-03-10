<?php

namespace WP_SMS;

// @deprecated Legacy shim.

class Gravityforms
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
