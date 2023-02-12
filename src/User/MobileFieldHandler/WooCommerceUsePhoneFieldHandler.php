<?php

namespace WP_SMS\User\MobileFieldHandler;

class WooCommerceUsePhoneFieldHandler
{
    public function register()
    {
        //add_filter('woocommerce_checkout_fields', array($this, 'edit_billing_phone'));
    }

     /**
     * @param $fields
     *
     * @return mixed
     */
    public function edit_billing_phone($fields)
    {
        $fields['billing']['billing_phone']['id'] = 'wp-sms-input-mobile';

        return $fields;
    }
}
