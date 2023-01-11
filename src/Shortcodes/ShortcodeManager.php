<?php

namespace WP_SMS\Shortcode;

class ShortcodeManager
{
    public function init()
    {
        $this->registerShortcode();
    }

    public function registerShortcode()
    {
        SubscriptionShortcode::boot();
    }
}