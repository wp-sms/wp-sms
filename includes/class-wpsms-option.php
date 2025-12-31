<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Option
{
    /**
     * Option group for plugin settings.
     *
     * @var string
     */
    public const SETTINGS_GROUP = 'wpsms_settings';

    /**
     * Option group for pro settings.
     *
     * @var string
     */
    public const PRO_GROUP = 'wps_pp_settings';

    /**
     * Option group for database settings.
     *
     * @var string
     */
    public const DB_GROUP = 'wp_sms_db';

    /**
     * Get the whole Plugin Options
     *
     * @param string $setting_name
     * @param bool $pro
     *
     * @return mixed|void
     */
    public static function getOptions($pro = false, $setting_name = 'wpsms_settings')
    {
        if ($pro) {
            $setting_name = 'wps_pp_settings';
        }

        return get_option($setting_name, array());
    }


    /**
     * Get the only Option that we want
     *
     * @param $option_name
     * @param bool $pro
     *
     * @return string
     */
    public static function getOption($option_name, $pro = false)
    {
        $options = self::getOptions($pro);

        return isset($options[$option_name]) ? $options[$option_name] : '';
    }

    /**
     * Add an option
     *
     * @param $option_name
     * @param $option_value
     */
    public static function addOption($option_name, $option_value)
    {
        add_option($option_name, $option_value);
    }

    /**
     * Update Option
     *
     * @param $key
     * @param $value
     * @param bool $pro
     */
    public static function updateOption($key, $value, $pro = false)
    {
        if ($pro) {
            $setting_name = 'wps_pp_settings';
        } else {
            $setting_name = 'wpsms_settings';
        }

        $options       = self::getOptions($pro);
        $options[$key] = $value;

        update_option($setting_name, $options);
    }

    /**
     * Get all options from a specific group.
     *
     * @param string $group The option group name.
     * @return array
     */
    public static function getByGroup($group)
    {
        return get_option($group, []);
    }

    /**
     * Get a single option from a specific group.
     *
     * @param string $key     The option key.
     * @param string $group   The option group name.
     * @param mixed  $default Default value if not found.
     * @return mixed
     */
    public static function getFromGroup($key, $group, $default = null)
    {
        $options = self::getByGroup($group);

        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Update an option in a specific group.
     *
     * @param string $key   The option key.
     * @param mixed  $value The value to set.
     * @param string $group The option group name.
     * @return bool
     */
    public static function updateInGroup($key, $value, $group)
    {
        $options       = self::getByGroup($group);
        $options[$key] = $value;

        return update_option($group, $options);
    }

    /**
     * Delete an option from a specific group.
     *
     * @param string $key   The option key.
     * @param string $group The option group name.
     * @return bool
     */
    public static function deleteFromGroup($key, $group)
    {
        $options = self::getByGroup($group);

        if (isset($options[$key])) {
            unset($options[$key]);
            return update_option($group, $options);
        }

        return true;
    }

    /**
     * Delete an entire option group.
     *
     * @param string $group The option group name.
     * @return bool
     */
    public static function deleteGroup($group)
    {
        return delete_option($group);
    }
}