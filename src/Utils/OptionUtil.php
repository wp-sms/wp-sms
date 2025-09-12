<?php

namespace WP_SMS\Utils;

use WP_SMS\User\UserHelper;

class OptionUtil
{
    /**
     * Default WP_SMS option name.
     *
     * @var string
     */
    public static $optName = 'wpsms_settings';

    /**
     * WP_SMS option name prefix.
     *
     * @var string
     */
    public static $optPrefix = 'wpsms_';

    /**
     * Get the complete option name with the WP_SMS prefix.
     *
     * @param string $name
     * @return string
     */
    public static function getOptionName($name)
    {
        return self::$optPrefix . $name;
    }

    /**
     * WP_SMS default options.
     *
     * @return array
     */
    public static function defaultOptions()
    {
        return [
            'enable_notifications'       => true,
            'default_country_code'       => '+1',
            'sms_provider'               => 'twilio',
            'api_key'                    => '',
            'api_secret'                 => '',
            'send_sms_to_admin'          => true,
            'admin_phone_number'         => '',
            'enable_otp'                 => true,
            'otp_expiry_time'            => 5, // minutes
            'otp_message_template'       => 'Your OTP is {{OTP}}',
            'user_registration_sms'      => false,
            'user_registration_template' => 'Welcome, {{USER_NAME}}!',
        ];
    }

    /**
     * Retrieve all WP_SMS options.
     *
     * @return array
     */
    public static function getOptions()
    {
        $options = get_option(self::$optName);
        return is_array($options) ? $options : [];
    }

    /**
     * Save WP_SMS options to the database.
     *
     * @param array $options
     * @return void
     */
    public static function saveOptions($options)
    {
        update_option(self::$optName, $options);
    }

    /**
     * Get a specific WP_SMS option.
     *
     * @param string $optionName
     * @param mixed $default
     * @return mixed
     */
    public static function get($optionName, $default = null)
    {
        $options = self::getOptions();

        if (!array_key_exists($optionName, $options)) {
            return isset($default) ? $default : false;
        }

        return apply_filters("wp_sms_option_{$optionName}", $options[$optionName]);
    }

    /**
     * Update a specific WP_SMS option.
     *
     * @param string $optionName
     * @param mixed $value
     * @return void
     */
    public static function update($optionName, $value)
    {
        $options              = self::getOptions();
        $options[$optionName] = $value;
        self::saveOptions($options);
    }

    /**
     * Get user meta for a specific option.
     *
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public static function getUserOption($option, $default = null)
    {
        $userId = UserHelper::getUserId();
        if (!$userId) {
            return false;
        }

        $userOptions = get_user_meta($userId, self::$optName, true) ?: [];
        return isset($userOptions[$option]) ? $userOptions[$option] : $default;
    }

    /**
     * Update user meta for a specific option.
     *
     * @param string $option
     * @param mixed $value
     * @return bool
     */
    public static function updateUserOption($option, $value)
    {
        $userId = UserHelper::getUserId();
        if (!$userId) {
            return false;
        }

        $userOptions          = get_user_meta($userId, self::$optName, true) ?: [];
        $userOptions[$option] = $value;

        return update_user_meta($userId, self::$optName, $userOptions);
    }

    /**
     * Retrieve options for a specific add-on.
     *
     * @param string $addonName
     * @return array|bool
     */
    public static function getAddonOptions($addonName = '')
    {
        $settingName = "wpsms_{$addonName}_options";

        $options = get_option($settingName);
        return is_array($options) ? $options : false;
    }

    /**
     * Get a specific option for an add-on.
     *
     * @param string $optionName
     * @param string $addonName
     * @param mixed $default
     * @return mixed
     */
    public static function getByAddon($optionName, $addonName = '', $default = null)
    {
        $settingName = "wpsms_{$addonName}_options";

        $options = get_option($settingName);
        $options = is_array($options) ? $options : [];

        return isset($options[$optionName]) ? $options[$optionName] : $default;
    }

    /**
     * Save options for a specific add-on.
     *
     * @param array $options
     * @param string $addonName
     * @return void
     */
    public static function saveByAddon($options, $addonName = '')
    {
        $settingName = "wpsms_{$addonName}_options";
        update_option($settingName, $options);
    }

    /**
     * Get options for a specific group.
     *
     * @param string $group
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function getOptionGroup($group, $key = null, $default = null)
    {
        $settingName = "wp_sms_{$group}";
        $options     = get_option($settingName, []);

        return $key ? (isset($options[$key]) ? $options[$key] : $default) : $options;
    }

    /**
     * Save options for a specific group.
     *
     * @param string $key
     * @param mixed $value
     * @param string $group
     * @return void
     */
    public static function saveOptionGroup($key, $value, $group)
    {
        $settingName = "wp_sms_{$group}";
        $options     = get_option($settingName, []);
        if (!is_array($options)) {
            $options = array();
        }
        $options[$key] = $value;

        update_option($settingName, $options);
    }

    /**
     * Delete an option from a specific group.
     *
     * @param string $key
     * @param string $group
     * @return void
     */
    public static function deleteOptionGroup($key, $group)
    {
        $settingName = "wp_sms_{$group}";
        $options     = get_option($settingName, []);

        if (isset($options[$key])) {
            unset($options[$key]);
            update_option($settingName, $options);
        }
    }

    /**
     * @param $item
     * @param $conditionKey
     * @return bool
     */
    public static function checkOptionRequire($item = array(), $conditionKey = 'require')
    {

        // Default is True
        $condition = true;

        // Check Require Params
        if (array_key_exists('require', $item)) {
            foreach ($item[$conditionKey] as $if => $value) {

                // Check Type of Condition
                if (($value === true and !OptionUtil::get($if)) || ($value === false and OptionUtil::get($if))) {
                    $condition = false;
                    break;
                }
            }
        }

        return $condition;
    }
}
