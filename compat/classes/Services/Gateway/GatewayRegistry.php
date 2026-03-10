<?php

namespace WP_SMS\Services\Gateway;

// @deprecated Legacy shim — prevents fatal errors in old add-ons.

class GatewayRegistry
{
    public static function getGateways()
    {
        return [];
    }

    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
