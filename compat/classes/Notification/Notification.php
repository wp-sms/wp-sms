<?php

namespace WP_SMS\Notification;

// @deprecated Legacy shim. Add-on notification handlers extend this.

class Notification
{
    public function __construct($args = [])
    {
    }

    public function send()
    {
        return false;
    }
}
