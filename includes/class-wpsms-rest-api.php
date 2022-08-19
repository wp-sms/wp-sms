<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class RestApi
{
    protected $sms;
    protected $option;
    protected $db;
    protected $tb_prefix;
    protected $namespace;
    protected $options;

    public function __construct()
    {
        global $sms, $wpdb;

        $this->sms       = $sms;
        $this->options   = Option::getOptions();
        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;
        $this->namespace = 'wpsms';
    }

    /**
     * Handle Response
     *
     * @param $message
     * @param int $status
     * @param array $data
     * @return \WP_REST_Response
     */
    public static function response($message, $status = 200, $data = [])
    {
        if ($status == 200) {
            $output = array(
                'message' => $message,
                'error'   => array(),
                'data'    => $data
            );
        } else {
            $output = array(
                'error' => array(
                    'code'    => $status,
                    'message' => $message
                ),
            );
        }

        return new \WP_REST_Response($output, $status);
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

    /**
     * Subscribe User
     *
     * @param $name
     * @param $mobile
     * @param null $group
     *
     * @return array|string
     */
    public static function subscribe($name, $mobile, $group = false)
    {
        global $sms;

        if (empty($name) or empty($mobile)) {
            return new \WP_Error('subscribe', __('Name and Mobile Number are required!', 'wp-sms'));
        }

        if (Option::getOption('newsletter_form_groups')) {
            if (!$group) {
                return new \WP_Error('subscribe', __('Please select the group!', 'wp-sms'));
            }

            if (!Newsletter::getGroup($group)) {
                return new \WP_Error('subscribe', __('Group ID not valid!', 'wp-sms'));
            }
        }

        $gateway_name = Option::getOption('gateway_name');
        if (Option::getOption('newsletter_form_verify') and $gateway_name) {
            // Check gateway setting
            if (!$gateway_name) {
                // Return response
                return new \WP_Error('subscribe', __('Service provider is not available for send activate key to your mobile. Please contact with site.', 'wp-sms'));
            }

            $key = rand(1000, 9999);

            // Add subscribe to database
            $result = Newsletter::addSubscriber($name, $mobile, $group, '0', $key);

            if ($result['result'] == 'error') {
                // Return response
                return new \WP_Error('subscribe', $result['message']);
            } else {

                $sms->to  = array($mobile);
                $sms->msg = __('Your activation code', 'wp-sms') . ': ' . $key;
                $sms->SendSMS();
            }

            // Return response
            return __('To activate your subscription, the activation has been sent to your number.', 'wp-sms');

        } else {

            // Add subscribe to database
            $result = Newsletter::addSubscriber($name, $mobile, $group, '1');

            if ($result['result'] == 'error') {
                // Return response
                return new \WP_Error('subscribe', $result['message']);
            } else {

                // Send welcome message
                if (Option::getOption('newsletter_form_welcome')) {
                    $template_vars = array(
                        '%subscribe_name%'   => $name,
                        '%subscribe_mobile%' => $mobile,
                    );
                    $text          = Option::getOption('newsletter_form_welcome_text');
                    $message       = str_replace(array_keys($template_vars), array_values($template_vars), $text);

                    /**
                     * Filter te welcome SMS message
                     */
                    $message = apply_filters('wpsms_welcome_sms_message', $message, $mobile);

                    $sms->to  = array($mobile);
                    $sms->msg = $message;
                    $sms->SendSMS();
                }
            }

            return __('Your mobile number has been successfully subscribed.', 'wp-sms');
        }
    }

    /**
     * Unsubscribe user
     *
     * @param $name
     * @param $mobile
     * @param null $group
     *
     * @return array|string
     */
    public static function unSubscribe($name, $mobile, $group = false)
    {
        if (empty($name) or empty($mobile)) {
            return new \WP_Error('unsubscribe', __('Name and Mobile Number are required!', 'wp-sms'));
        }

        if ($group) {
            $check_group = Newsletter::getGroup($group);

            if (!isset($check_group) and empty($check_group)) {
                return new \WP_Error('unsubscribe', __('The group number is not valid!', 'wp-sms'));
            }
        }
        
        // Delete subscriber
        $result = Newsletter::deleteSubscriberByNumber($mobile, $group);

        // Check result
        if ($result['result'] == 'error') {
            // Return response
            return new \WP_Error('unsubscribe', $result['message']);
        }

        return $result['message'];
    }

    /**
     * Verify Subscriber
     *
     * @param $name
     * @param $mobile
     * @param $activation
     * @param $groupId
     * @return array|string
     */
    public static function verifySubscriber($name, $mobile, $activation, $groupId = 0)
    {
        global $sms, $wpdb;

        if (empty($name) or empty($mobile) or empty($activation)) {
            return new \WP_Error('unsubscribe', __('The required parameters must be valued!', 'wp-sms'));
        }

        // Check the mobile number is string or integer
        if (strpos($mobile, '+') !== false) {
            $db_prepare = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = %s AND `status` = %d AND group_ID = %d", $mobile, 0, $groupId);
        } else {
            $db_prepare = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = %d AND `status` = %d AND group_ID = %d", $mobile, 0, $groupId);
        }

        $check_mobile = $wpdb->get_row($db_prepare);

        if ($check_mobile) {

            if ($activation != $check_mobile->activate_key) {
                // Return response
                return new \WP_Error('verify_subscriber', __('Activation code is wrong!', 'wp-sms'));
            }

            // Check the mobile number is string or integer
            if (strpos($mobile, '+') !== false) {
                $result = $wpdb->update("{$wpdb->prefix}sms_subscribes", array('status' => '1'), array('mobile' => $mobile, 'group_ID' => $groupId), array('%d', '%d'), array('%s'));
            } else {
                $result = $wpdb->update("{$wpdb->prefix}sms_subscribes", array('status' => '1'), array('mobile' => $mobile, 'group_ID' => $groupId), array('%d', '%d'), array('%d'));
            }

            if ($result) {
                // Send welcome message
                if (Option::getOption('newsletter_form_welcome')) {
                    $template_vars = array(
                        '%subscribe_name%'   => $name,
                        '%subscribe_mobile%' => $mobile,
                    );
                    $text          = Option::getOption('newsletter_form_welcome_text');
                    $message       = str_replace(array_keys($template_vars), array_values($template_vars), $text);

                    $sms->to  = array($mobile);
                    $sms->msg = $message;
                    $sms->SendSMS();
                }

                // Return response
                return __('Your subscription done successfully!', 'wp-sms');
            }
        }

        return new \WP_Error('verify_subscriber', __('Not found the number!', 'wp-sms'));
    }
}

new RestApi();