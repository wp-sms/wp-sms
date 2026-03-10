<?php

namespace WP_SMS\User;

// @deprecated Legacy shim — prevents fatal errors in old add-ons.
// getHandler() returns a stub object so get_class() won't fatal on null.

class MobileFieldManager
{
    public function getHandler()
    {
        return new MobileFieldHandlerStub();
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

/**
 * @internal No-op stub returned by MobileFieldManager::getHandler().
 */
class MobileFieldHandlerStub
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
