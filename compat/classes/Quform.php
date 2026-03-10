<?php

namespace WP_SMS;

// @deprecated Legacy shim.

class Quform
{
    public static function get_fields($formId = null)
    {
        return [];
    }

    public function __call($name, $arguments)
    {
        return null;
    }

    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
