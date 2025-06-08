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
     * Get a specific WP SMS option value.
     *
     * @param string $option_name
     * @param bool $pro
     * @return mixed
     */
    public static function getOption($option_name, $pro = false)
    {
        $options = self::getOptions($pro);

        $value = isset($options[$option_name]) ? $options[$option_name] : '';

        /**
         * Filter a WP SMS option before returning it.
         *
         * @param mixed $value The option value.
         * @param string $option_name The name of the option.
         * @param bool $pro Whether this is coming from the Pro options.
         */
        return apply_filters('wp_sms_get_option_value', $value, $option_name, $pro);
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
}