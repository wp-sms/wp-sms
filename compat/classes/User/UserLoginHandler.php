<?php

namespace WP_SMS\User;

// @deprecated Legacy shim.

class UserLoginHandler
{
    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
