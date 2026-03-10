<?php

namespace WP_SMS\Notification;

// @deprecated Legacy shim — prevents fatal errors in old add-ons.
// Returns a chainable no-op object so calls like
// NotificationFactory::getCustom()->registerVariables($vars)->send()
// don't fatal.

class NotificationFactory
{
    public static function __callStatic($name, $arguments)
    {
        return new Notification();
    }
}
