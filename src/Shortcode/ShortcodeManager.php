<?php

namespace WP_SMS\Shortcode;

class ShortcodeManager
{
    private $shortcodes = [
        \WP_SMS\Shortcode\SubscriberShortcode::class,
    ];

    public function init()
    {
        foreach ($this->shortcodes as $shortcode) {
            if (class_exists($shortcode)) {
                $newClass = new SubscriberShortcode();
                $newClass->register();
            }
        }
    }
}