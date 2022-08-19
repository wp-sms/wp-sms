<?php

namespace WP_SMS;

class Helper
{
    public static function getPluginAssetUrl($assetName, $plugin = 'wp-sms')
    {
        return plugins_url($plugin) . "/assets/{$assetName}";
    }

    public static function getAssetPath($asset)
    {
        return plugin_dir_path(__FILE__) . $asset;
    }

    /**
     * @param $template
     * @param array $parameters
     * @return false|string|void
     */
    public static function loadTemplate($template, $parameters = [])
    {
        $templatePath = plugin_dir_path(__FILE__) . "templates/{$template}";

        if (file_exists($templatePath)) {
            ob_start();

            extract($parameters);
            require plugin_dir_path(__FILE__) . "templates/{$template}";

            return ob_get_clean();
        }
    }

    /**
     * @return mixed|void|null
     */
    public static function getUserMobileFieldName()
    {
        return apply_filters('wp_sms_user_mobile_field', 'mobile');
    }

    /**
     * @param $userId
     * @return mixed
     */
    public static function getUserMobileNumberByUserId($userId)
    {
        // get from the user meta
        $mobileNumber = get_user_meta($userId, self::getUserMobileFieldName(), true);

        return apply_filters('wp_sms_user_mobile_number', $mobileNumber, $userId);
    }

    /**
     * @param $roleId
     * @return array
     */
    public static function getUsersMobileNumbers($roleId = false)
    {
        $mobileFieldKey = self::getUserMobileFieldName();
        $args           = array(
            'meta_query'  => array(
                array(
                    'key'     => $mobileFieldKey,
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
            'count_total' => 'false'
        );

        if ($roleId) {
            $args['role'] = $roleId;
        }

        $users         = get_users($args);
        $mobileNumbers = [];

        foreach ($users as $user) {
            if (isset($user->$mobileFieldKey)) {
                $mobileNumbers[] = $user->$mobileFieldKey;
            }
        }

        return $mobileNumbers;
    }

    /**
     * @param $message
     * @return array|string|string[]|null
     */
    public static function makeUrlsShorter($message)
    {
        $regex = "/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

        return preg_replace_callback($regex, function ($url) {
            return wp_sms_shorturl($url[0]);
        }, $message);
    }

    public static function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get final message content by tag variables
     *
     * @param array $variables
     * @param string $content
     * @param array $args
     * @return string
     */
    public static function getOutputMessageVariables($variables, $content, $args = array())
    {
        /**
         * Filters the variables to replace in the message content
         *
         * @param array $variables Array containing message variables parsed from the argument.
         * @param string $content Default message content before replacing variables.
         * @since 5.7.6
         *
         */
        $variables = apply_filters('wp_sms_output_variables', $variables, $content, $args);

        $message = str_replace(array_keys($variables), array_values($variables), $content);

        /**
         * Filters the final message content after replacing variables
         *
         * @param string $message Message content after replacing variables.
         * @param string $content Default message content before replacing variables.
         * @param array $variables Array containing message variables parsed from the argument.
         * @since 5.7.6
         *
         *
         */
        return apply_filters('wp_sms_output_variables_message', $message, $content, $variables, $args);
    }

    /**
     * return current admin page url
     *
     * @return string
     */

    public static function getCurrentAdminPageUrl()
    {
        global $wp;

        return add_query_arg($_SERVER['QUERY_STRING'], '', home_url($wp->request) . '/wp-admin/admin.php');
    }

    /**
     * This function check the validity of users' phone numbers. If the number is not available, raise an error
     *
     * @param $mobileNumber
     * @param bool $userID
     * @param bool $isSubscriber
     * @param $groupID
     * @param $subscribeId
     *
     * @return bool|\WP_Error
     */
    public static function checkMobileNumberValidity($mobileNumber, $userID = false, $isSubscriber = false, $groupID = false, $subscribeId = false)
    {
        global $wpdb;

        // check whether international mode is enabled
        $international_mode = Option::getOption('international_mobile') ? true : false;

        // check whether the first character of mobile number is +
        $country_code = substr($mobileNumber, 0, 1) == '+' ? true : false;

        /**
         * 1. Check whether international mode is on and the number is NOT started with +
         */
        if ($international_mode and !$country_code) {
            return new \WP_Error('invalid_number', __("The mobile number doesn't contain the country code. ", 'wp-sms'));
        }

        /**
         * 2. Check whether the min and max length of the number comply
         */
        if (!$international_mode) {

            // get min length of the number if it is set
            $min_length = Option::getOption('mobile_terms_minimum');

            // get max length of the number if it is set
            $max_length = Option::getOption('mobile_terms_maximum');

            if ($max_length and strlen($mobileNumber) > $max_length) {
                return new \WP_Error('invalid_number', __("Your mobile number must have up to {$max_length} characters.", 'wp-sms'));
            }

            if ($min_length and strlen($mobileNumber) < $min_length) {
                return new \WP_Error('invalid_number', __("Your mobile number must have at least {$min_length} characters.", 'wp-sms'));
            }

        }

        /**
         * 3. Check whether number is exists in usermeta or sms_subscriber table
         */
        if ($isSubscriber) {
            $sql = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE mobile = %s", $mobileNumber);

            if ($groupID) {
                $sql .= $wpdb->prepare(" AND group_id = '%s'", $groupID);
            }

            // While updating we should query except the current one.
            if ($subscribeId) {
                $sql .= $wpdb->prepare(" AND id != '%s'", $subscribeId);
            }

            $result = $wpdb->get_row($sql);

        } else {
            $where       = '';
            $mobileField = Helper::getUserMobileFieldName();

            if ($userID) {
                $where = $wpdb->prepare('AND user_id != %s', $userID);
            }

            $sql    = $wpdb->prepare("SELECT * from {$wpdb->prefix}usermeta WHERE meta_key = %s AND meta_value = %s {$where};", $mobileField, $mobileNumber);
            $result = $wpdb->get_results($sql);
        }

        // if any result found, raise an error
        if ($result) {
            return new \WP_Error('is_duplicate', __('This mobile is already registered, please choose another one.', 'wp-sms'));
        }

        return true;
    }

    /**
     * @param $mobile
     *
     * @return string
     */
    public static function sanitizeMobileNumber($mobile)
    {
        return sanitize_text_field(trim($mobile));
    }
}
