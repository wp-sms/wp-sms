<?php

namespace WP_SMS\Notice;

// @deprecated Legacy shim.

class NoticeManager
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __call($name, $arguments)
    {
        return null;
    }
}
