<?php

namespace WP_SMS;


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
     * @param $userId
     *
     * @return mixed
     */
    public static function getUserMobileNumberByUserId($userId)
    {
        $mobileFieldManager = new \WP_SMS\User\MobileFieldManager();
        return $mobileFieldManager->getHandler()->getMobileNumberByUserId($userId);
    }

    /**
     * @param $number
     * @return mixed|void|null
     */
    public static function getUserByPhoneNumber($number)
    {
        if (empty($number)) {
            return;
        }

        $users = get_users([
            'meta_key'   => self::getUserMobileFieldName(),
            'meta_value' => self::prepareMobileNumberQuery($number)
        ]);

        return !empty($users) ? array_values($users)[0] : null;
    }

    /**
     * @param $roleId
     *
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
     * Get WooCommerce customers
     *
     * @return array|int
     */
    public static function getWooCommerceCustomersNumbers($roles = [])
    {
        $fieldKey = self::getUserMobileFieldName();
        $args     = array(
            'meta_query' => array(
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
            'fields'     => 'all_with_meta'
        );

        if ($roles) {
            $args['role__in'] = $roles;
        }

        $customers = get_users($args);
        $numbers   = array();

        foreach ($customers as $customer) {
            $numbers[] = $customer->$fieldKey;
        }

        return $numbers;
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
            $customerMobileNumber = self::getUserMobileNumberByUserId($userId);

            if ($customerMobileNumber) {
                return $customerMobileNumber;
            }
        }

        $mobile = get_post_meta($orderId, self::getUserMobileFieldName(), true);

        // Backward compatibility, the context of order meta is different with customer
        if (!$mobile) {
            $mobile = get_post_meta($orderId, '_' . self::getUserMobileFieldName(), true);
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
    public static function notice($message, $type = 'info', $dismiss = false, $link = '')
    {
        echo self::loadTemplate('admin/notice.php', [
            'message' => $message,
            'type'    => $type,
            'dismiss' => $dismiss,
            'link'    => $link
        ]);
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
}
