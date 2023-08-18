<?php

namespace WP_SMS\User\MobileFieldHandler;

use WP_SMS\Option;

class WooCommerceUsePhoneFieldHandler
{
    public function register()
    {
        add_filter('woocommerce_checkout_fields', array($this, 'modifyBillingPhoneAttributes'));
        add_filter('woocommerce_admin_billing_fields', [$this, 'modifyAdminBillingPhoneAttributes']);
        add_filter('woocommerce_customer_meta_fields', [$this, 'modifyAdminCustomerMetaBillingPhoneAttributes']);
    }

    public function getMobileNumberByUserId($userId)
    {
        $mobileNumber = get_user_meta($userId, $this->getUserMobileFieldName(), true);

        // backward compatibility
        if (!$mobileNumber) {
            $mobileNumber = get_user_meta($userId, '_billing_phone', true);
        }

        // backward compatibility
        if (!$mobileNumber) {
            $customerSessionData = WC()->session->get('customer');

            if (isset($customerSessionData['phone'])) {
                $mobileNumber = $customerSessionData['phone'];
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
            $fields['phone']['class'][] = 'wp-sms-input-mobile';
        }

        return $fields;
    }

    public function modifyAdminCustomerMetaBillingPhoneAttributes($fields)
    {
        if (isset($fields['billing']['fields'])) {
            $fields['billing']['fields']['billing_phone']['class'] = 'wp-sms-input-mobile';
        }

        return $fields;
    }
}
