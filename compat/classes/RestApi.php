<?php

namespace WP_SMS;

// @deprecated Legacy shim — extended by wp-sms-pro REST API classes.

class RestApi
{
    public $tb_prefix;

    public function __construct()
    {
        global $wpdb;
        $this->tb_prefix = isset($wpdb->prefix) ? $wpdb->prefix : '';
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
