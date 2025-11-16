<?php

namespace WP_SMS\Settings;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Option
{
    /**
     * Array of acceptable addon names
     *
     * @var array
     */
    private static $acceptable_addons = [
        'pro',
        'two_way',
        'booking_integrations',
        'fluent_integrations',
    ];

    /**
     * Resolve the option key based on addon parameter
     *
     * @param string|null $addon
     * @return string
     */
    private static function resolveOptionKey($addon = null)
    {
        // TODO: Integrate schema/validator here for validation
        
        // Handle addon-specific option keys
        if ($addon !== null) {
            // Validate addon name
            if (!in_array($addon, self::$acceptable_addons)) {
                // TODO: Add proper error handling/logging for invalid addon names
                return 'wp_sms_settings';
            }
            return 'wp_sms_' . $addon . '_settings';
        }
        
        // Default option key for free version
        return 'wp_sms_settings';
    }

    /**
     * Get the whole Plugin Options
     *
     * @param string|null $addon
     * @param string $setting_name (deprecated - kept for backward compatibility)
     *
     * @return mixed|void
     */
    public static function getOptions($addon = null, $setting_name = 'wp_sms_settings')
    {
        $option_key = self::resolveOptionKey($addon);

        // TODO: Integrate schema/validator here for validation
        return get_option($option_key, array());
    }

    /**
     * Get the only Option that we want
     *
     * @param string $option_name
     * @param string|null $addon
     *
     * @return string
     */
    public static function getOption($option_name, $addon = null)
    {
        $options = self::getOptions($addon, 'wp_sms_settings');

        // TODO: Integrate schema/validator here for validation
        return isset($options[$option_name]) ? $options[$option_name] : '';
    }

    /**
     * Add an option
     *
     * @param string $option_name
     * @param mixed $option_value
     * @param string|null $addon
     */
    public static function addOption($option_name, $option_value, $addon = null)
    {
        $option_key = self::resolveOptionKey($addon);
        
        // TODO: Integrate schema/validator here for validation
        add_option($option_key, $option_value);
    }

    /**
     * Update Option
     *
     * @param string $key
     * @param mixed $value
     * @param string|null $addon
     */
    public static function updateOption($key, $value, $addon = null)
    {
        $option_key = self::resolveOptionKey($addon);
        $options = self::getOptions($addon, 'wp_sms_settings');
        $options[$key] = $value;

        // TODO: Integrate schema/validator here for validation
        update_option($option_key, $options);
    }


    public static function deleteOptionGroup($key, $group)
    {
        $settingName = "wp_sms_{$group}";
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
        $settingName = "wp_sms_{$group}";
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
        $settingName = "wp_sms_{$group}";
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
        $settingName = "wp_sms_{$group}";
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