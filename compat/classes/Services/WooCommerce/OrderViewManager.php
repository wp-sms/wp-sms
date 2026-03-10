<?php

namespace WP_SMS\Services\WooCommerce;

// @deprecated Legacy shim.

class OrderViewManager
{
    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
