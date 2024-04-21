<?php

namespace WP_SMS\Services\WooCommerce;

class WooCommerceCheckout
{
    const FIELD_ORDER_NOTIFICATION = 'wpsms_woocommerce_order_notification';

    public function init()
    {
        add_action('woocommerce_init', function () {
            if (apply_filters('wpsms_woocommerce_order_opt_in_notification', false)) {
                add_action('woocommerce_review_order_before_submit', array($this, 'registerCheckboxCallback'), 10);
                add_action('woocommerce_checkout_order_processed', array($this, 'registerStoreCheckboxCallback'), 10, 2);

                add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'registerOrderUpdateCheckbox'));
            }
        });
    }

    /**
     * Show user order notification status in the order page
     */
    public function registerOrderUpdateCheckbox($order)
    {
        echo sprintf("<p style='margin-bottom: 0'><strong>%s</strong></p>", esc_html__('Status Update SMS Notifications:', 'wp-sms'));
        if ($order->get_meta('wpsms_woocommerce_order_notification') && $order->get_meta('wpsms_woocommerce_order_notification') == 'yes') {
            echo esc_html__('Enabled', 'wp-sms');
        } else {
            echo esc_html__('Disabled', 'wp-sms');
        }
    }

    /**
     * Add WooCommerce additional Checkbox checkout field
     */
    public function registerCheckboxCallback()
    {
        woocommerce_form_field(self::FIELD_ORDER_NOTIFICATION, [
            'type'        => 'checkbox',
            'class'       => ['form-row wpsmswoopro-checkbox'],
            'label_class' => ['woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'],
            'input_class' => ['woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'],
            'required'    => false,
            'label'       => __('I would like to get notification about any change in my order via SMS.', 'wp-sms')
        ], 1);
    }

    /**
     * @param $orderId
     * @param $data
     */
    public function registerStoreCheckboxCallback($orderId, $data)
    {
        if (isset($_POST[self::FIELD_ORDER_NOTIFICATION]) && $_POST[self::FIELD_ORDER_NOTIFICATION]) {
            update_post_meta($orderId, self::FIELD_ORDER_NOTIFICATION, 'yes');
        } else {
            update_post_meta($orderId, self::FIELD_ORDER_NOTIFICATION, 'no');
        }
    }
}
