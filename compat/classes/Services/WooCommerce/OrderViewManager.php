<?php

namespace WP_SMS\Services\WooCommerce;

// @deprecated Legacy shim — prevents fatal errors in old add-ons.

class OrderViewManager
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
