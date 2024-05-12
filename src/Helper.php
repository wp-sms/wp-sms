<?php

namespace WP_SMS;

use WC_Blocks_Utils;
use WP_Error;

/**
 * Class WP_SMS
 * @package WP_SMS
 * @description The helper that provides the useful methods for the plugin for development purposes.
 */
class Helper
{
    public static function getPluginAssetUrl($assetName, $plugin = 'wp-sms')
    {
        return plugins_url($plugin) . "/assets/{$assetName}";
    }

    public static function getAssetPath($asset)
    {
        return plugin_dir_path(dirname(__FILE__, 1)) . $asset;
    }

    /**
     * @param $template
     * @param array $parameters
     * @param $isPro
     *
     * @return false|string|void
     */
    public static function loadTemplate($template, $parameters = [], $isPro = false)
    {
        $base_path = WP_SMS_DIR;

        if ($isPro) {
            $base_path = WP_SMS_PRO_DIR;
        }

        $templatePath = $base_path . "includes/templates/{$template}";

        if (file_exists($templatePath)) {
            ob_start();

            extract($parameters);
            require $templatePath;

            return ob_get_clean();
        }
    }

    /**
     * @return mixed|void|null
     */
    public static function getUserMobileFieldName()
    {
        $mobileFieldManager = new \WP_SMS\User\MobileFieldManager();
        return $mobileFieldManager->getHandler()->getUserMobileFieldName();
    }

    /**
     * @return string
     */
    public static function getWooCommerceCheckoutFieldName()
    {
        $mobileFieldHandler = (new \WP_SMS\User\MobileFieldManager())->getHandler();
        return $mobileFieldHandler instanceof \WP_SMS\User\MobileFieldHandler\WooCommerceAddMobileFieldHandler ?
            $mobileFieldHandler->getUserMobileFieldName() :
            'billing_phone';
    }

    /**
     * Get mobile field selector in the checkout page
     * 
     * @return string
     */
    public static function getWooCommerceCheckoutMobileField()
    {
        if (self::checkoutBlockEnabled()) {
            // If the new checkout block is enabled
            return 'billing-phone';
        } else {
            // If classic checkout mode is enabled
            return self::getWooCommerceCheckoutFieldName();
        }
    }

    /**
     * Get submit button element selector in the checkout page
     * 
     * @return string
     */
    public static function getWooCommerceCheckoutSubmitBtn()
    {
        if (self::checkoutBlockEnabled()) {
            // If the new checkout block is enabled
            return '.wc-block-components-checkout-place-order-button';
        } else {
            // If classic checkout mode is enabled
            return '#place_order';
        }
    }

    /**
     * Checks if the checkout page is using blocks
     * 
     * @return bool
     */
    public static function checkoutBlockEnabled() 
    {
        return WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout');
    }

    /**
     * @param $userId
     *
     * @return mixed
     */
    public static function getUserMobileNumberByUserId($userId, $args = [])
    {
        $mobileFieldManager = new \WP_SMS\User\MobileFieldManager();
        return $mobileFieldManager->getHandler()->getMobileNumberByUserId($userId, $args);
    }

    /**
     * @param $number
     * @return \WP_User|null
     */
    public static function getUserByPhoneNumber($number)
    {
        if (empty($number)) {
            return;
        }

        $users = get_users([
            'meta_key'   => self::getUserMobileFieldName(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => self::prepareMobileNumberQuery($number) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);

        return !empty($users) ? array_values($users)[0] : null;
    }

    /**
     * @param $roleId
     * @param array $userIds
     *
     * @return array
     */
    public static function getUsersMobileNumbers($roleId = false, $userIds = array())
    {
        $mobileFieldKey = self::getUserMobileFieldName();

        $args = array(
            'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                array(
                    'key'     => $mobileFieldKey,
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
            'count_total' => false,
            'number'      => 1000
        );

        if ($roleId) {
            $args['role__in'] = $roleId;
        }

        // Add user IDs to include in the query
        if (count($userIds) > 0) {
            $args['include'] = $userIds;
        }

        $args  = apply_filters('wp_sms_mobile_numbers_query_args', $args);
        $users = get_users($args);

        $mobileNumbers = [];

        foreach ($users as $user) {
            if (isset($user->$mobileFieldKey)) {
                $mobileNumbers[] = $user->$mobileFieldKey;
            }
        }

        return array_unique($mobileNumbers);
    }

    /**
     * Get WooCommerce customers
     *
     * @return array|int
     */
    public static function getWooCommerceCustomersNumbers($roles = [])
    {
        $fieldKey = self::getUserMobileFieldName();
        $args     = array(
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'relation' => 'OR',
                array(
                    'key'     => $fieldKey,
                    'value'   => '',
                    'compare' => '!=',
                ),
                array(
                    'key'     => '_billing_phone',
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
            'fields'     => 'all_with_meta',
            'number'     => 1000
        );

        if ($roles) {
            $args['role__in'] = $roles;
        }

        $args      = apply_filters('wp_sms_wc_mobile_numbers_query_args', $args);
        $customers = get_users($args);
        $numbers   = array();

        foreach ($customers as $customer) {
            $numbers[] = $customer->$fieldKey;
        }

        // Backward compatibility with new custom WooCommerce order table.
        if (get_option('woocommerce_custom_orders_table_enabled')) {
            global $wpdb;
            $tableName           = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_addresses_table_name();
            $numbersFromNewTable = $wpdb->get_col("SELECT DISTINCT `phone` from {$tableName} where `phone` !=''");
            $numbers             = array_merge($numbers, $numbersFromNewTable);
        }

        $normalizedNumbers = [];
        foreach ($numbers as $number) {
            $normalizedNumber = self::normalizeNumber($number);
            // Use normalized number as key to avoid duplicates
            $normalizedNumbers[$normalizedNumber] = $number;
        }

        // Convert associative array back to indexed array
        $numbers = array_values($normalizedNumbers);

        return array_unique($numbers);
    }

    /**
     * Get customer mobile number by order id
     *
     * @param $orderId
     * @return string|void
     * @throws Exception
     */
    public static function getWooCommerceCustomerNumberByOrderId($orderId)
    {
        $userId = get_post_meta($orderId, '_customer_user', true);

        if ($userId) {
            $customerMobileNumber = self::getUserMobileNumberByUserId($userId, ['order_id' => $orderId]);

            if ($customerMobileNumber) {
                return $customerMobileNumber;
            }
        }

        $mobile = get_post_meta($orderId, self::getUserMobileFieldName(), true);

        // Backward compatibility, the context of order meta is different with customer
        if (!$mobile) {
            $mobile = get_post_meta($orderId, '_' . self::getUserMobileFieldName(), true);
        }

        // Backward compatibility with new custom WooCommerce order table.
        if (!$mobile && function_exists('wc_get_order')) {
            $order = \wc_get_order($orderId);

            if ($order && method_exists($order, 'get_billing_phone')) {
                $mobile = $order->get_billing_phone();
            }
        }

        return $mobile;
    }

    /**
     * Prepare a list of WP roles
     *
     * @return array
     */
    public static function getListOfRoles()
    {
        $wpsms_list_of_role = array();
        foreach (wp_roles()->role_names as $key_item => $val_item) {
            $wpsms_list_of_role[$key_item] = array(
                "name"  => $val_item,
                "count" => count(self::getUsersMobileNumbers($key_item))
            );
        }

        return $wpsms_list_of_role;
    }

    /**
     * @param $message
     *
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
     * @param bool $userId
     * @param bool $isSubscriber
     * @param $groupID
     * @param $subscribeId
     *
     * @return bool|WP_Error
     */
    public static function checkMobileNumberValidity($mobileNumber, $userId = false, $isSubscriber = false, $groupID = false, $subscribeId = false)
    {
        global $wpdb;

        $mobileNumber  = trim($mobileNumber);
        $numeric_check = apply_filters('wp_sms_mobile_number_numeric_check', true);

        if ($numeric_check && !is_numeric($mobileNumber)) {
            return new WP_Error('invalid_number', __('Invalid Mobile Number', 'wp-sms'));
        }

        // check whether international mode is enabled
        $international_mode = Option::getOption('international_mobile') ? true : false;

        // check whether the first character of mobile number is +
        $country_code = substr($mobileNumber, 0, 1) == '+' ? true : false;

        // check whether the mobile number is in international mobile only countries
        $international_mobile_only_countries = Option::getOption('international_mobile_only_countries');

        /**
         * 1. Check whether international mode is on and the number is NOT started with +
         */
        if ($international_mode and !$country_code) {
            return new WP_Error('invalid_number', __("The mobile number doesn't contain the country code.", 'wp-sms'));
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
                // translators: %s: Max length of the number
                return new WP_Error('invalid_number', sprintf(__('Your mobile number must have up to %s characters.', 'wp-sms'), $max_length));
            }

            if ($min_length and strlen($mobileNumber) < $min_length) {
                // translators: %s: Min length of the number
                return new WP_Error('invalid_number', sprintf(__('Your mobile number must have at least %s characters.', 'wp-sms'), $min_length));
            }
        }

        /**
         * 3. Check whether number is exists in usermeta or sms_subscriber table
         */
        if ($isSubscriber) {
            $sql = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE mobile = %s", $mobileNumber);

            if (isset($groupID)) {
                $sql .= $wpdb->prepare(" AND group_ID = %s", $groupID);
            }

            // While updating we should query except the current one.
            if ($subscribeId) {
                $sql .= $wpdb->prepare(" AND id != %s", $subscribeId);
            }

            $result = $wpdb->get_row($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            // If result has active status, raise an error
            if ($result && $result->status == '1') {
                return new WP_Error('is_duplicate', __('This mobile is already registered, please choose another one.', 'wp-sms'));
            }
        } else {
            $where       = '';
            $mobileField = self::getUserMobileFieldName();

            if ($userId) {
                $where = $wpdb->prepare('AND user_id != %s', $userId);
            }

            $result = $wpdb->get_results(
                $wpdb->prepare("SELECT * from {$wpdb->prefix}usermeta WHERE meta_key = %s AND meta_value = %s {$where};", $mobileField, $mobileNumber)
            );

            // If result is not empty, raise an error
            if ($result) {
                return new WP_Error('is_duplicate', __('This mobile is already registered, please choose another one.', 'wp-sms'));
            }
        }

        /**
         * 4. Check whether the number country is valid or not
         */
        if ($international_mode && $international_mobile_only_countries) {
            $countryCallingCodes = wp_json_file_decode(WP_SMS_DIR . 'assets/countries-code.json', ['associative' => true]);
            $onlyCountries       = array_filter($countryCallingCodes, function ($code) use ($international_mobile_only_countries) {
                return in_array($code, $international_mobile_only_countries);
            }, ARRAY_FILTER_USE_KEY);
            $isValid             = false;
            foreach ($onlyCountries as $code) {
                $countryLength = strlen($code);
                $prefix        = substr($mobileNumber, 0, $countryLength);
                if ($prefix === $code) {
                    $isValid = true;
                    break;
                }
            }
            if (!$isValid) {
                return new WP_Error('invalid_number', __('The mobile number is not valid for your country.', 'wp-sms'));
            }
        }

        return apply_filters('wp_sms_mobile_number_validity', true, $mobileNumber);
    }


    /**
     * @param $mobile
     *
     * @return string
     */
    public static function sanitizeMobileNumber($mobile)
    {
        return apply_filters('wp_sms_sanitize_mobile_number', sanitize_text_field(trim($mobile)));
    }

    /**
     * @return void
     */
    public static function maybeStartSession($readAndClose = true)
    {
        if (empty(session_id()) && !headers_sent()) {
            session_start(array('read_and_close' => $readAndClose));
        }
    }

    /**
     * This function adds mobile country code to the mobile number if the mobile country code option is enabled.
     *
     * @param $mobileNumber
     * @return mixed|string
     */
    public static function prepareMobileNumber($mobileNumber)
    {
        $international_mode = Option::getOption('international_mobile') ? true : false;
        $country_code       = substr($mobileNumber, 0, 1) == '+' ? true : false;

        if ($international_mode and !$country_code) {
            $mobileNumber = '+' . $mobileNumber;
        }

        return $mobileNumber;
    }

    public static function prepareMobileNumberQuery($number)
    {
        $metaValue[]    = $number;
        $numberWithPlus = '+' . $number;

        // Check if number is international format or not and add country code to meta value
        if (substr($number, 0, 1) != '+') {
            $metaValue[] = $numberWithPlus;
            $number      = $numberWithPlus;
        } else {
            $metaValue[] = ltrim($number, '+');
        }

        // Remove the country code from prefix of number +144444444 -> 44444444
        foreach (wp_sms_get_countries() as $countryCode => $countryName) {
            if (strpos($number, $countryCode) === 0) {
                $metaValue[] = substr($number, strlen($countryCode));
            }
        }

        return $metaValue;
    }

    /**
     * Show Admin Notice
     */
    public static function notice($message, $type = 'info', $dismiss = false, $link = '', $return = false)
    {
        $output = self::loadTemplate('admin/notice.php', [
            'message' => $message,
            'type'    => $type,
            'dismiss' => $dismiss,
            'link'    => $link
        ]);

        if ($return) {
            return $output;
        } else {
            echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * Add Flash Admin WordPress UI Notice (One-time)
     */
    public static function flashNotice($text, $model = 'success', $redirect = false)
    {
        update_option('wpsms_flash_message', [
            'text'  => $text,
            'model' => $model
        ]);

        if ($redirect) {
            wp_redirect($redirect);
            exit;
        }
    }

    public static function sendMail($subject, $args)
    {
        $adminEmail = get_option('admin_email');
        $message    = self::loadTemplate('email/default.php', $args);
        $headers    = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($adminEmail, $subject, $message, $headers);
    }

    public static function normalizeNumber($number)
    {
        // Remove all non-digits except leading +
        $number = preg_replace('/[^\d+]/', '', $number);

        // Get the default country code without leading + sign
        $countryCode = substr(Option::getOption('mobile_county_code'), 1);

        // Check if the number starts with + sign 
        /*if (strpos($number, '+') === 0) {
            // Remove the + sign from the beginning of each number
            $number = substr($number, 1);
        }*/

        // Check if the number starts with the default country code
        if (!empty($countryCode) && strpos($number, $countryCode) === 0) {
            // Remove the country code from the beginning of each number
            $number = substr($number, strlen($countryCode));
        }

        return $number;
    }

    public static function removeDuplicateNumbers($numbers)
    {
        $numbers = array_map('trim', $numbers);
        $numbers = array_map([__CLASS__, 'normalizeNumber'], $numbers);
        $numbers = array_unique($numbers);

        return $numbers;
    }

    /**
     * Remove certain prefixes from recipient numbers like +, or country code
     *
     * @param array $prefixes Array of prefixes to remove from numbers
     * @param array $numbers Array of numbers
     *
     * @return array
     */
    public static function removeNumbersPrefix($prefix, $numbers)
    {
        $prefixPattern = '/^(' . implode('|', array_map('preg_quote', $prefix)) . ')/';
        return array_map(function ($number) use ($prefixPattern) {
            return preg_replace($prefixPattern, '', $number, 1);
        }, $numbers);
    }
}
