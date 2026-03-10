<?php

namespace WP_SMS\Admin\LicenseManagement;

// @deprecated Legacy shim.

class ApiCommunicator
{
    public function __construct($args = [])
    {
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
