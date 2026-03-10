<?php

namespace WP_SMS;

// @deprecated Legacy shim — prevents fatal errors in old add-ons.

class Option
{
    public static function getOption($key = '', $default = false)
    {
        return $default;
    }

    public static function getOptions($isPro = false)
    {
        return [];
    }

    public static function updateOption($key, $value = '', $isPro = false)
    {
    }

    public static function deleteOption($key, $isPro = false)
    {
    }

    public static function get($tokenName)
    {
        return null;
    }

    public static function add($tokenName, $value)
    {
    }

    public static function update($tokenName, $value)
    {
    }

    public static function setAll($options)
    {
    }

    public static function reset()
    {
    }

    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
