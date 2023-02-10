<?php

namespace WP_SMS\User\MobileFieldHandler;

use WP_SMS\Helper;

class WooCommerceAddMobileFieldHandler
{
    /**
     * @var mixed|null
     */
    private $mobileField;

    public function __construct()
    {
        $this->mobileField = \WP_SMS\Helper::getUserMobileFieldName();
    }

    public function register()
    {
        // billing address in my account
        add_filter('woocommerce_billing_fields', [$this, 'registerFieldInBillingForm']);
        add_action('woocommerce_after_save_address_validation', [$this, 'validateMobileNumberCallback']);

        // billing address in admin profile
        add_filter('woocommerce_customer_meta_fields', [$this, 'registerFieldInAdminUserBillingForm'], 10, 1);

        // checkout billing address
        add_filter('woocommerce_checkout_fields', [$this, 'registerFieldInCheckoutBillingForm']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validateMobileNumberCallback'], 10, 2);
    }

    public function registerFieldInBillingForm($fields)
    {
        $fields[$this->mobileField] = $this->getField();

        return $fields;
    }

    /**
     * @return void
     */
    public function validateMobileNumberCallback()
    {
        $mobile   = Helper::sanitizeMobileNumber($_POST[$this->mobileField]);
        $validity = Helper::checkMobileNumberValidity($mobile, isset($user->ID) ? $user->ID : false);

        if (is_wp_error($validity)) {
            wc_add_notice(__('The phone number should be 11 digits and start with 1.'), 'error');
        }
    }

    public function registerFieldInAdminUserBillingForm($args)
    {
        $args['billing']['fields'][$this->mobileField] = array(
            'label'       => __('Mobile Number', 'wp-sms'),
            'description' => __('Mobile Number for getting SMS notification', 'wp-sms'),
            'class'       => 'wp-sms-input-mobile'
        );

        return $args;
    }

    public function registerFieldInCheckoutBillingForm($fields)
    {
        $fields['billing'][$this->mobileField] = $this->getField();

        return $fields;
    }

    private function getField()
    {
        return [
            'label'       => __('Mobile Number', 'wp-sms'),
            'placeholder' => __('Please enter your mobile number', 'wp-sms'),
            'required'    => true,
            'clear'       => false,
            'type'        => 'text',
            'input_class' => array('wp-sms-input-mobile')
        ];
    }
}
