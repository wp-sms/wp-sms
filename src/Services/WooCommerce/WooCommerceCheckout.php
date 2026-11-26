<?php

namespace WP_SMS\Services\WooCommerce;

use WP_SMS\Blocks\WooSmsOptInBlock;
use WP_SMS\Helper;
use WP_SMS\Option;

if (!defined('ABSPATH')) exit;

class WooCommerceCheckout
{
    const FIELD_ORDER_NOTIFICATION = 'wpsms_woocommerce_order_notification';

    public function init()
    {
        if (Option::getOption('wc_checkout_confirmation_checkbox_enabled', true)) {
            add_filter('wpsms_woocommerce_order_opt_in_notification', '__return_true');
        }

        add_action('woocommerce_init', function () {
            if (apply_filters('wpsms_woocommerce_order_opt_in_notification', false)) {
                if (Helper::isWooCheckoutBlock()) {
                    new WooSmsOptInBlock();

                    add_action('woocommerce_set_additional_field_value', [$this, 'registerStoreCheckboxBlockBasedCallback'], 10, 4);
                    return;
                }

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

        if (self::getOrderOptInStatus($order) == 'yes') {
            echo esc_html__('Enabled', 'wp-sms');
        } else {
            echo esc_html__('Disabled', 'wp-sms');
        }
    }

    /**
     * Get the SMS notification choice saved on an order.
     *
     * @param \WC_Order $order
     * @return string `yes`, `no`, or an empty string when nothing was saved.
     */
    public static function getOrderOptInStatus($order)
    {
        $status = $order->get_meta(self::FIELD_ORDER_NOTIFICATION);

        if (!$status) {
            // Earlier versions saved the classic checkout choice as post meta, which
            // the order object does not read when orders use their own tables (HPOS).
            $status = get_post_meta($order->get_id(), self::FIELD_ORDER_NOTIFICATION, true);
        }

        return (string)$status;
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
     * For Checkout:shortcode
     *
     * @param $orderId
     * @param $data
     */
    public function registerStoreCheckboxCallback($orderId, $data)
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            return;
        }

        $order->update_meta_data(self::FIELD_ORDER_NOTIFICATION, !empty($_POST[self::FIELD_ORDER_NOTIFICATION]) ? 'yes' : 'no');
        $order->save();
    }

    /**
     * @param $key
     * @param $value
     * @param $group
     * @param $order \WC_Order
     * @return void
     */
    public function registerStoreCheckboxBlockBasedCallback($key, $value, $group, $order)
    {
        if ($key == 'wpsms/opt-in') {
            $order->update_meta_data(self::FIELD_ORDER_NOTIFICATION, $value ? 'yes' : 'no');
        } elseif (!$order->get_meta(self::FIELD_ORDER_NOTIFICATION)) {
            // Another field is being saved: default to "no" without replacing the choice.
            $order->update_meta_data(self::FIELD_ORDER_NOTIFICATION, 'no');
        }
    }
}
