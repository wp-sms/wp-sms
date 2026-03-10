<?php

namespace WP_SMS\Notification;

// @deprecated Legacy shim.

class NotificationFactory
{
    public static function __callStatic($name, $arguments)
    {
        return null;
    }

    public function __call($name, $arguments)
    {
        return null;
    }
}
