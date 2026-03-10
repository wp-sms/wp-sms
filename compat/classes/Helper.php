<?php

namespace WP_SMS;

// @deprecated Legacy shim — prevents fatal errors in old add-ons.

class Helper
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
