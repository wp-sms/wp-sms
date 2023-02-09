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
        // billing address
        add_filter('woocommerce_billing_fields', [$this, 'registerFieldInBillingForm']);
        add_action('woocommerce_save_account_details_errors', [$this, 'validateMobileNumberCallback']);

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
     * @param $errors \WP_Error
     * @return void
     */
    public function validateMobileNumberCallback($errors)
    {
        $mobile   = Helper::sanitizeMobileNumber($_POST[$this->mobileField]);
        $validity = Helper::checkMobileNumberValidity($mobile, isset($user->ID) ? $user->ID : false);

        if (is_wp_error($validity)) {
            $errors->add($validity->get_error_code(), $validity->get_error_message());
        }

        return $errors;
    }

    public function registerFieldInCheckoutBillingForm($fields)
    {
        $fields['billing'][$this->mobileField] = $this->getField();

        return $fields;
    }

    private function getField()
    {
        return [
            'label'       => __('Mobile', 'wp-sms'),
            'placeholder' => __('Please enter your mobile number', 'wp-sms'),
            'required'    => true,
            'clear'       => false,
            'type'        => 'text',
            'class'       => array('wp-sms-input-mobile')
        ];
    }
}
