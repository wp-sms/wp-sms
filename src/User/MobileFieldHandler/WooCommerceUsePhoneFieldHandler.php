<?php

namespace WP_SMS\User\MobileFieldHandler;

class WooCommerceUsePhoneFieldHandler
{
    public function register()
    {
        add_filter('woocommerce_checkout_fields', array($this, 'modifyBillingPhoneAttributes'));
        add_filter('woocommerce_admin_billing_fields', [$this, 'modifyAdminBillingPhoneAttributes']);
    }

    public function getMobileNumberByUserId($userId)
    {
        $mobileNumber = get_user_meta($userId, 'billing_phone', true);

        // backward compatibility
        if (!$mobileNumber) {
            $mobileNumber = get_user_meta($userId, '_billing_phone', true);
        }

        return apply_filters('wp_sms_user_mobile_number', $mobileNumber, $userId);
    }

    public function getUserMobileFieldName()
    {
        return apply_filters('wp_sms_user_mobile_field', '_billing_phone');
    }

    /**
     * @param $fields
     */
    public function modifyBillingPhoneAttributes($fields)
    {
        if (isset($fields['billing']['billing_phone'])) {
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
}
