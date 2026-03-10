<?php

namespace WP_SMS;

// @deprecated Legacy shim.

class SmsOtp
{
    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
