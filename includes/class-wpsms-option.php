<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Option
{

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

    public static function deleteOptionGroup($key, $group)
    {
        $settingName = "wpsms_{$group}";
        $options     = get_option($settingName, []);

        // Check if the key exists in the array.
        if (array_key_exists($key, $options)) {
            // Remove the key from the array.
            unset($options[$key]);

            // Write the updated array back to the database.
            update_option($settingName, $options);
        }
    }

    public static function saveOptionGroup($key, $value, $group)
    {
        $settingName = "wpsms_{$group}";
        $options     = get_option($settingName, []);

        // Backward compatibility.
        if (!is_array($options)) {
            $options = array();
        }

        // Store the value in the array.
        $options[$key] = $value;

        // Write the array to the database.
        update_option($settingName, $options);
    }

    public static function addOptionGroup($key, $value, $group)
    {
        $settingName = "wpsms_{$group}";
        $options     = get_option($settingName, []);

        // Backward compatibility.
        if (!is_array($options)) {
            $options = array();
        }

        // Store the value in the array.
        $options[$key] = $value;

        // Write the array to the database.
        add_option($settingName, $options);
    }

    public static function getOptionGroup($group, $key = null, $default = null)
    {
        $settingName = "wpsms_{$group}";
        $options     = get_option($settingName);

        if (!isset($options) || !is_array($options)) {
            $options = array();
        }

        if (is_null($key)) {
            $result = $options;
        } else {
            if (!array_key_exists($key, $options)) {
                $result = !is_null($default) ? $default : false;
            } else {
                $result = $options[$key];
            }
        }

        return apply_filters("wp_sms_option_{$settingName}", $result);
    }
}