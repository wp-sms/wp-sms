<?php

namespace WSms\Support;

defined('ABSPATH') || exit;

class SigningKey
{
    public static function get(): string
    {
        return defined('AUTH_KEY') ? AUTH_KEY : 'wsms-fallback-key-' . ABSPATH;
    }
}
