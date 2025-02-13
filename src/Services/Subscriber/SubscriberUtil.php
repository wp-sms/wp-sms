<?php

namespace WP_SMS\Services\Subscriber;

use WP_SMS\Helper;
use WP_SMS\Newsletter;
use WP_SMS\Option;

/**
 * @todo this old-level class should be refactored, have to keep it for now but let's get rid of it in the future
 */
class SubscriberUtil
{
    /**
     * Subscribe User
     *
     * @param $name
     * @param $mobile
     * @param bool $group
     * @param array $customFields
     * @return array|string
     */
    public static function subscribe($name, $mobile, $group = false, $customFields = array())
    {
        if (empty($name) or empty($mobile)) {
            return new \WP_Error('subscribe', esc_html__('Name and Mobile Number are required!', 'wp-sms'));
        }

        $mobile = Helper::convertNumber($mobile);

        // Delete inactive subscribes with this number
        Newsletter::deleteInactiveSubscribersByMobile($mobile);

        $groupIds = wp_unslash($group);

        if (!is_null($groupIds))
            $groupIds = json_decode($groupIds);

        if (!is_array($groupIds))
            $groupIds = array($groupIds);

        $groupIds = array_map('intval', $groupIds);

        $gateway_name = Option::getOption('gateway_name');

        if (Option::getOption('newsletter_form_verify') and $gateway_name) {
            // Check gateway setting
            if (!$gateway_name) {
                // Return response
                return new \WP_Error('subscribe', esc_html__('Service provider is not available for send activate key to your mobile. Please contact with site.', 'wp-sms'));
            }

            $key = wp_rand(1000, 9999);

            foreach ($groupIds as $groupId) {
                // Add subscribe to database
                $result = Newsletter::addSubscriber($name, $mobile, $groupId, '0', $key, $customFields);
                if ($result['result'] == 'error') {
                    // Return response
                    return new \WP_Error('subscribe', $result['message']);
                }
            }

            // translators: %s: Activation code
            wp_sms_send($mobile, sprintf(esc_html__('Your activation code: %s', 'wp-sms'), $key));

            // Return response
            return esc_html__('To activate your subscription, the activation has been sent to your number.', 'wp-sms');
        } else {
            foreach ($groupIds as $groupId) {
                // Add subscribe to database
                $result = Newsletter::addSubscriber($name, $mobile, $groupId, '1', null, $customFields);
                if ($result['result'] == 'error') {
                    // Return response
                    return new \WP_Error('subscribe', $result['message']);
                }
            }

            // Return response
            return esc_html__('Your mobile number has been successfully subscribed.', 'wp-sms');
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
            return new \WP_Error('unsubscribe', esc_html__('Name and Mobile Number are required!', 'wp-sms'));
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
        global $wpdb;

        if (empty($name) or empty($mobile) or empty($activation)) {
            return new \WP_Error('unsubscribe', esc_html__('The required parameters must be valued!', 'wp-sms'));
        }

        $db_prepare = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = %s AND `status` = %d", $mobile, 0);

        $groupId = json_decode(stripslashes($groupId), true);

        if (is_array($groupId)) {
            $groupId = $groupId[0];
        }

        $updateCondition = array('mobile' => $mobile);
        if ($groupId and $groupId !== 0) {
            $db_prepare                  .= $wpdb->prepare(" AND group_ID = %d", $groupId);
            $updateCondition['group_ID'] = $groupId;
        }
        $check_mobile = $wpdb->get_row($db_prepare); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ($check_mobile) {

            if ($activation != $check_mobile->activate_key) {
                // Return response
                return new \WP_Error('verify_subscriber', esc_html__('Activation code is wrong!', 'wp-sms'));
            }

            // Check the mobile number is string or integer
            if (strpos($mobile, '+') !== false) {
                $result = $wpdb->update("{$wpdb->prefix}sms_subscribes", array('status' => '1'), $updateCondition, array('%d', '%d'), array('%s'));
            } else {
                $result = $wpdb->update("{$wpdb->prefix}sms_subscribes", array('status' => '1'), $updateCondition, array('%d', '%d'), array('%d'));
            }

            if ($result) {
                do_action('wp_sms_verify_subscriber', $name, $mobile, 1, $check_mobile->ID);

                // Return response
                return esc_html__('Your subscription done successfully!', 'wp-sms');
            }
        }

        return new \WP_Error('verify_subscriber', esc_html__('Not found the number!', 'wp-sms'));
    }
}
