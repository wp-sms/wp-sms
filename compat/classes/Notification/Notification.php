<?php

namespace WP_SMS\Notification;

// @deprecated Legacy shim — chainable no-op.
// Supports calls like: $notification->registerVariables($vars)->send()

class Notification
{
    public function __construct($args = [])
    {
    }

    public function send()
    {
        return false;
    }

    public function printVariables()
    {
        return $this;
    }

    public function registerVariables($vars = [])
    {
        return $this;
    }

    public function getVariables()
    {
        return [];
    }

    /**
     * Catch-all for any other chained method calls.
     */
    public function __call($name, $arguments)
    {
        return $this;
    }
}
