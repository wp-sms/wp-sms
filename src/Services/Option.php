<?php

namespace WPSmsTwoWay\Services;

class Option
{
    /**
     * Plugin's options prefix
     */
    const PREFIX = 'wpsms_two_way';

    /**
     * Add a new option
     *
     * @param string $optionName
     * @param mixed $value
     * @return bool
     */
    public static function add(string $optionName, $value = null)
    {
        $optionName = self::getOptionName($optionName);
        return add_option($optionName, $value);
    }

    /**
     * Get an option
     *
     * @param string $optionName
     * @param boolean $default
     * @return mixed|false false when option does not exist
     */
    public static function get(string $optionName, $default = false)
    {
        $optionName = self::getOptionName($optionName);
        return get_option($optionName, $default);
    }

    /**
     * Update an existing option( or create a new one)
     *
     * @param string $optionName
     * @param mixed $value
     * @return bool
     */
    public static function update(string $optionName, $value)
    {
        $optionName = self::getOptionName($optionName);
        return update_option($optionName, $value);
    }

    /**
     * Get prefixed option name
     *
     * @param string $optionName
     * @return string prefixed option name
     */
    public static function getOptionName(string $optionName)
    {
        return self::PREFIX.$optionName;
    }
}
