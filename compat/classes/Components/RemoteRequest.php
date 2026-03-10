<?php

namespace WP_SMS\Components;

// @deprecated Legacy shim.

class RemoteRequest
{
    public function __construct($args = [])
    {
    }

    public function __call($name, $arguments)
    {
        return null;
    }
}
