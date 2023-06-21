<?php

namespace WP_SMS\Services\WooCommerce;

class WooCommerceCheckout
{
    public const FIELD_ORDER_NOTIFICATION = 'wpsms_woocommerce_order_notification';

    public function init()
    {
        add_action('init', function () {
            if (apply_filters('wpsms_woocommerce_order_opt_in_notification', false)) {
                add_action('woocommerce_review_order_before_submit', array($this, 'registerCheckboxCallback'), 10);
                add_action('woocommerce_checkout_update_order_meta', array($this, 'registerStoreCheckboxCallback'), 10, 2);
            }
        });

        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'registerOrderUpdateCheckbox'));
    }

    /**
     * Show user order notification status in the order page
     */
    public function registerOrderUpdateCheckbox($order)
    {
        $order_status = 'This user has disabled the order status notification.';
        if ($order->get_meta('wpsms_woocommerce_order_notification')) {
            $order_status =  'This user has enabled the order status notification.';
        }
        print "<h4 style='margin-top: 220px' class='wpsms_order_notification_status'>{$order_status}</h4>";
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
            'label'       => __('I would like to get notification about any change in my order via SMS.', 'wp-sms-woocommerce-pro')
        ], 1);
    }

    /**
     * @param $orderId
     * @param $data
     */
    public function registerStoreCheckboxCallback($orderId, $data)
    {
        if (isset($_POST[self::FIELD_ORDER_NOTIFICATION])) {
            update_post_meta($orderId, self::FIELD_ORDER_NOTIFICATION, sanitize_text_field($_POST[self::FIELD_ORDER_NOTIFICATION]));
        }
    }
}
