<?php

namespace WP_SMS\Controller;

// @deprecated Legacy shim. Add-on controllers extend this.

abstract class AjaxControllerAbstract
{
    public function __construct()
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
