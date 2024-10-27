<?php

namespace WP_SMS;

use WC_Blocks_Utils;
use WP_Error;
use WP_SMS\Components\NumberParser;

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
        if (self::isWooCheckoutBlock()) {
            // If the new checkout block is enabled

            if ("use_phone_field_in_wc_billing" === wp_sms_get_option('add_mobile_field')) {
                return '#billing-phone';
            }

            if ("add_mobile_field_in_wc_billing" === wp_sms_get_option('add_mobile_field')) {
                return '#billing-wpsms\\/mobile,#billing-wpsms-mobile';
            }
        } else {
            // If classic checkout mode is enabled

            $mobileFieldId = self::getWooCommerceCheckoutFieldName();
            if (substr($mobileFieldId, 0, 1) != '#') {
                $mobileFieldId = "#$mobileFieldId";
            }

            return $mobileFieldId;
        }
    }

    /**
     * Get submit button element selector in the checkout page
     *
     * @return string
     */
    public static function getWooCommerceCheckoutSubmitBtn()
    {
        if (self::isWooCheckoutBlock()) {
            // If the new checkout block is enabled
            return '.wc-block-components-checkout-place-order-button';
        } else {
            // If classic checkout mode is enabled
            return '#place_order';
        }
    }

    /**
     * Checks if the checkout page is using blocks.
     *
     * Dot't forget to use `is_checkout()` together with this method.
     *
     * @return bool
     */
    public static function isWooCheckoutBlock()
    {
        if (class_exists('WooCommerce')) {
            return WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout');
        }
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
            return null;
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
     * Get users mobile number count with role details
     *
     * @return array
     */
    public static function getUsersMobileNumberCountsWithRoleDetails()
    {
        $mobileFieldKey = self::getUserMobileFieldName();
        $all_roles      = wp_roles()->role_names;

        // Initialize the roles array with role details
        $roles = [];
        foreach ($all_roles as $role_key => $role_name) {
            $roles[$role_key] = [
                'name'  => $role_name,
                'count' => 0,
                // 'numbers' => []
            ];
        }

        $total_count = 0;

        $args = array(
            'meta_query' => array(
                array(
                    'key'     => $mobileFieldKey,
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
        );

        $args  = apply_filters('wp_sms_mobile_numbers_query_args', $args);
        $users = get_users($args);

        // $mobileNumbers = [];

        foreach ($users as $user) {
            if (isset($user->$mobileFieldKey)) {
                // $mobileNumbers[] = $user->$mobileFieldKey;
                $total_count++;
                foreach ($user->roles as $role) {
                    if (isset($roles[$role])) {
                        $roles[$role]['count']++;
                        // $roles[$role]['numbers'][] = $user->$mobileFieldKey;
                    }
                }
            }
        }

        return array(
            'total' => [
                'count' => $total_count,
                // 'numbers' => array_unique($mobileNumbers),
            ],
            'roles' => $roles,
        );
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
        $numberParser = new NumberParser($mobileNumber);
        $mobileNumber = $numberParser->getValidNumber();
        if (is_wp_error($mobileNumber)) {
            return $mobileNumber;
        }

        /**
         * Check whether number is exists in usermeta or sms_subscriber table
         */
        if ($isSubscriber) {
            if ($numberParser::isDuplicateInSubscribers($mobileNumber, $groupID, $subscribeId)) {
                return new WP_Error('is_duplicate', __('This mobile is already registered, please choose another one.', 'wp-sms'));
            }
        } else {
            if ($numberParser::isDuplicateInUsermeta($mobileNumber, $userId)) {
                return new WP_Error('is_duplicate', __('This mobile is already registered, please choose another one.', 'wp-sms'));
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
        foreach (wp_sms_countries()->getCountriesMerged() as $countryCode => $countryName) {
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

    /**
     * Convert persian/hindi/arabic numbers to english
     *
     * @param $number
     *
     * @return string
     */
    public static function convertNumber($number)
    {
        return strtr($number, array('۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'));
    }
}
