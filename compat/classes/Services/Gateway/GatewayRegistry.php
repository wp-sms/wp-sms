<?php

namespace WP_SMS\Services\Gateway;

// @deprecated Legacy shim.

class GatewayRegistry
{
    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
