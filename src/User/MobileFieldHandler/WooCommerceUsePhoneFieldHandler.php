<?php

namespace WP_SMS\User\MobileFieldHandler;

use WP_SMS\Option;
use WP_SMS\Helper;

class WooCommerceUsePhoneFieldHandler extends AbstractFieldHandler
{
    public function register()
    {
        add_filter('woocommerce_checkout_fields', array($this, 'modifyBillingPhoneAttributes'));
        add_filter('woocommerce_admin_billing_fields', [$this, 'modifyAdminBillingPhoneAttributes']);
        add_filter('woocommerce_customer_meta_fields', [$this, 'modifyAdminCustomerMetaBillingPhoneAttributes']);

        add_action('user_profile_update_errors', array($this, 'adminRegistrationErrors'), 10, 3);
    }

    public function getMobileNumberByUserId($userId, $args = [])
    {
        $mobileNumber = get_user_meta($userId, $this->getUserMobileFieldName(), true);

        // backward compatibility
        if (!$mobileNumber) {
            $mobileNumber = get_user_meta($userId, '_billing_phone', true);
        }

        // backward compatibility
        if (!$mobileNumber && isset(WC()->session)) {
            $customerSessionData = WC()->session->get('customer');

            if (isset($customerSessionData['phone'])) {
                $mobileNumber = $customerSessionData['phone'];
            }
        }

        // Backward compatibility with new custom WooCommerce order table.
        if (!$mobileNumber and isset($args['order_id'])) {
            $order = wc_get_order($args['order_id']);

            if ($order && method_exists($order, 'get_billing_phone')) {
                $mobileNumber = $order->get_billing_phone();
            }
        }

        return apply_filters('wp_sms_user_mobile_number', $mobileNumber, $userId);
    }

    public function getUserMobileFieldName()
    {
        return apply_filters('wp_sms_user_mobile_field', 'billing_phone');
    }

    /**
     * @param $fields
     */
    public function modifyBillingPhoneAttributes($fields)
    {
        if (isset($fields['billing']['billing_phone'])) {
            if (Option::getOption('optional_mobile_field') === 'optional') {
                $fields['billing']['billing_phone']['required'] = false;
            }

            $fields['billing']['billing_phone']['class'][] = 'wp-sms-input-mobile';
        }

        return $fields;
    }

    public function modifyAdminBillingPhoneAttributes($fields)
    {
        if (isset($fields['phone']['class'])) {
            $fields['phone']['class'][] = 'wp-sms-input-mobile ltr';
        }

        return $fields;
    }

    public function modifyAdminCustomerMetaBillingPhoneAttributes($fields)
    {
        if (isset($fields['billing']['fields'])) {
            $fields['billing']['fields']['billing_phone']['class'] = 'wp-sms-input-mobile ltr';
        }

        return $fields;
    }

    /**
     * Handle the mobile field update errors
     *
     * @param $errors
     * @param $update
     * @param $user
     *
     * @return void|\WP_Error
    */
    public function adminRegistrationErrors($errors, $update, $user)
    {
        $phoneNumber = isset($_POST[$this->getUserMobileFieldName()]) ? $_POST[$this->getUserMobileFieldName()] : null;

        // Check if the phone is not empty
        if (Option::getOption('optional_mobile_field') !== 'optional' && empty($phoneNumber)) {
            $errors->add('mobile_number_error', __('<strong>ERROR</strong>: You must enter the mobile number.', 'wp-sms'));
        }

        // Validate phone number
        if ($phoneNumber) {
            $mobile   = Helper::sanitizeMobileNumber($phoneNumber);
            $validity = Helper::checkMobileNumberValidity($mobile, isset($user->ID) ? $user->ID : false);

            if (is_wp_error($validity)) {
                $errors->add($validity->get_error_code(), $validity->get_error_message());
            }
        }

        // If mobile is invalid, prevent it from being saved
        if ($errors->has_errors()) {
            update_user_meta($user->ID, $this->getUserMobileFieldName(), '');
        }

        return $errors;
    }
}
