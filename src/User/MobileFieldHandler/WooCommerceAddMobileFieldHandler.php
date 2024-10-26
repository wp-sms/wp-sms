<?php

namespace WP_SMS\User\MobileFieldHandler;

use WP_SMS\Blocks\WooMobileField;
use WP_SMS\Components\NumberParser;
use WP_SMS\Helper;
use WP_SMS\Option;

class WooCommerceAddMobileFieldHandler extends AbstractFieldHandler
{
    public function register()
    {
        if (Helper::isWooCheckoutBlock()) {
            new WooMobileField();

            add_action('woocommerce_validate_additional_field', [$this, 'validateMobileNumberInCheckoutBlockBasedCallback'], 10, 3);
            add_action('woocommerce_set_additional_field_value', [$this, 'updateMobileNumberAfterPlaceTheOrderBlockBased'], 10, 4);
        }

        // billing address in my account
        add_filter('woocommerce_billing_fields', [$this, 'registerFieldInBillingForm']);
        add_action('woocommerce_after_save_address_validation', [$this, 'validateMobileNumberCallback']);

        // billing address in admin profile
        add_filter('woocommerce_customer_meta_fields', [$this, 'registerFieldInAdminUserBillingForm'], 10, 1);

        // checkout billing address
        add_filter('woocommerce_checkout_fields', [$this, 'registerFieldInCheckoutBillingForm']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validateMobileNumberInCheckoutCallback'], 10, 2);
        add_action('woocommerce_checkout_order_processed', array($this, 'updateMobileNumberAfterPlaceTheOrder'), 10, 3);

        // Phone number validation
        add_action('update_user_metadata', array($this, 'profilePhoneValidation'), 10, 5);

        // admin order billing address
        add_filter('woocommerce_admin_billing_fields', [$this, 'registerFieldInAdminOrderBillingForm']);
        add_action('woocommerce_process_shop_order_meta', array($this, 'updateCustomerMobileNumberAfterUpdateTheOrder'), 10, 2);

        //add_action('wp_enqueue_scripts', [$this, 'checkoutMobileFieldInlineStyle']);
    }

    public function getMobileNumberByUserId($userId)
    {
        $mobileNumber = get_user_meta($userId, $this->getUserMobileFieldName(), true);
        return apply_filters('wp_sms_user_mobile_number', $mobileNumber, $userId);
    }

    public function getUserMobileFieldName()
    {
        return apply_filters('wp_sms_user_mobile_field', 'mobile');
    }

    public function registerFieldInBillingForm($fields)
    {
        $fields[$this->getUserMobileFieldName()] = $this->getField();

        return $fields;
    }

    /**
     * @return void
     */
    public function validateMobileNumberCallback()
    {
        $mobile = Helper::sanitizeMobileNumber($_POST[$this->getUserMobileFieldName()]);

        $numberParser = new NumberParser($mobile);
        $mobile       = $numberParser->getValidNumber();
        if (is_wp_error($mobile)) {
            wc_add_notice($mobile->get_error_message(), 'error');
        }
    }

    /**
     * @param $errors \WP_Error
     * @return void
     */
    public function validateMobileNumberInCheckoutCallback($data, $errors)
    {
        $this->handleValidateError($errors, $_POST[$this->getUserMobileFieldName()]);
    }

    /**
     * @param $errors \WP_Error
     * @return void
     */
    private function handleValidateError(&$errors, $mobileNumber)
    {
        if (Option::getOption('optional_mobile_field') != 'optional' && !$mobileNumber) {
            $errors->add('mobile_number_error', __('<strong>ERROR</strong>: You must enter the mobile number.', 'wp-sms'));
        }

        $mobile = Helper::sanitizeMobileNumber($mobileNumber);

        if (!empty($mobile)) {
            $validity = Helper::checkMobileNumberValidity($mobile, get_current_user_id());

            if (is_wp_error($validity)) {
                $errors->add($validity->get_error_code(), $validity->get_error_message());
            }
        }
    }

    /**
     * @param $orderId
     * @param $postedData
     * @param $order \WC_Order
     * @return void
     */
    public function updateMobileNumberAfterPlaceTheOrder($orderId, $postedData, $order)
    {
        $userMobile = isset($postedData[$this->getUserMobileFieldName()]) ? Helper::sanitizeMobileNumber($postedData[$this->getUserMobileFieldName()]) : '';

        if ($userMobile) {
            $this->updateMobileNumber($orderId, $userMobile);
        }
    }

    public function registerFieldInAdminUserBillingForm($args)
    {
        $args['billing']['fields'][$this->getUserMobileFieldName()] = array(
            'label'       => __('Mobile Number', 'wp-sms'),
            'description' => __('Mobile Number for getting SMS notification', 'wp-sms'),
            'class'       => 'wp-sms-input-mobile regular-text ltr'
        );

        return $args;
    }

    public function registerFieldInCheckoutBillingForm($fields)
    {
        $fields['billing'][$this->getUserMobileFieldName()] = $this->getField();

        return $fields;
    }

    private function getField()
    {
        return [
            'label'       => __('Mobile Number', 'wp-sms'),
            'required'    => !(Option::getOption('optional_mobile_field') == 'optional'),
            'clear'       => false,
            'type'        => 'tel',
            'input_class' => array('wp-sms-input-mobile'),
        ];
    }

    public function registerFieldInAdminOrderBillingForm($billingFields)
    {
        // Backward compatibility
        if (empty($_GET['post'])) {
            return;
        }

        $orderId = sanitize_text_field($_GET['post']);
        $mobile  = get_post_meta($orderId, $this->getUserMobileFieldName(), true);

        $billingFields[$this->getUserMobileFieldName()] = [
            'label' => esc_html__('Mobile Number', 'wp-sms'),
            'class' => 'wp-sms-input-mobile',
            'value' => $mobile
        ];

        return $billingFields;
    }

    public function updateCustomerMobileNumberAfterUpdateTheOrder($orderId, $data)
    {
        if (isset($_POST['_billing_mobile'])) {
            $this->updateMobileNumber($orderId, $_POST['_billing_mobile']);
        }
    }

    public function checkoutMobileFieldInlineStyle()
    {
        // Check if the current page is the checkout page
        if (is_checkout()) {
            $customCss = "
            #mobile_field .iti--show-flags {
            width: 100% !important;
            }
            #mobile_field .iti__flag-container {
            top : 4px !important;
            }";

            // Add custom CSS codes inline to 'woocommerce-layout' stylesheet
            wp_add_inline_style('woocommerce-layout', $customCss);
        }
    }

    /**
     * @param $orderId
     * @param $mobileNumber
     * @return void
     */
    private function updateMobileNumber($orderId, $mobileNumber)
    {
        $mobileNumber = Helper::sanitizeMobileNumber($mobileNumber);
        $mobileNumber = str_replace(' ', '', $mobileNumber);

        // Update in order meta
        update_post_meta($orderId, $this->getUserMobileFieldName(), $mobileNumber);

        // Update in user meta
        $userId = get_post_meta($orderId, '_customer_user', true);

        if ($userId and $userId != 0) {
            update_user_meta($userId, $this->getUserMobileFieldName(), $mobileNumber);
        }
    }

    /**
     * @param $key
     * @param $value
     * @param $group
     * @param $order \WC_Order
     * @return void
     */
    public function updateMobileNumberAfterPlaceTheOrderBlockBased($key, $value, $group, $order)
    {
        if ($key == 'wpsms/mobile') {
            $userMobile = Helper::sanitizeMobileNumber($value);

            if ($userMobile) {
                $this->updateMobileNumber($order->get_id(), $userMobile);
            }
        }
    }

    /**
     * @param \WP_Error $errors
     * @param $fieldKey
     * @param $fieldValue
     * @return void
     */
    public function validateMobileNumberInCheckoutBlockBasedCallback(\WP_Error $errors, $fieldKey, $fieldValue)
    {
        if ('wpsms/mobile' === $fieldKey) {
            $this->handleValidateError($errors, $fieldValue);
        }
    }
}
