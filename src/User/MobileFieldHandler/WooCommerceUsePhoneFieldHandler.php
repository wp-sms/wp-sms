<?php

namespace WP_SMS\User\MobileFieldHandler;

class WooCommerceUsePhoneFieldHandler
{
    public function register()
    {
        add_filter('woocommerce_checkout_fields', array($this, 'modifyBillingPhoneAttributes'));
        add_filter('woocommerce_admin_billing_fields', [$this, 'modifyAdminBillingPhoneAttributes']);
    }

    /**
     * @param $fields
     */
    public function modifyBillingPhoneAttributes($fields)
    {
        $fields['billing']['billing_phone']['class'] = 'wp-sms-input-mobile';

        return $fields;
    }

    public function modifyAdminBillingPhoneAttributes($fields)
    {
        $fields['phone']['class'] = 'wp-sms-input-mobile';

        return $fields;
    }
}
