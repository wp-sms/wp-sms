<?php

namespace WP_SMS\Services\WooCommerce;

use WP_SMS\Blocks\WooSmsOptInBlock;
use WP_SMS\Helper;
use WP_SMS\Option;

class WooCommerceCheckout
{
    const FIELD_ORDER_NOTIFICATION       = 'wpsms_woocommerce_order_notification';
    const FIELD_ORDER_NOTIFICATION_BLOCK = '_wc_other/wpsms/opt-in';

    public function init()
    {
        if (Option::getOption('wc_checkout_confirmation_checkbox_enabled', true)) {
            add_filter('wpsms_woocommerce_order_opt_in_notification', '__return_true');
        }

        add_action('woocommerce_init', function () {
            if (apply_filters('wpsms_woocommerce_order_opt_in_notification', false)) {
                add_action('woocommerce_review_order_before_submit', array($this, 'registerCheckboxCallback'), 10);
                add_action('woocommerce_checkout_order_processed', array($this, 'registerStoreCheckboxCallback'), 10, 2);

                add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'registerOrderUpdateCheckbox'));

                if (Helper::isWooCheckoutBlock() && function_exists('woocommerce_register_additional_checkout_field')) {
                    new WooSmsOptInBlock();
                }
            }
        });

        add_action('woocommerce_checkout_create_order', array($this, 'registerSmsOptInOnCheckout'));
    }

    /**
     * Show user order notification status in the order page
     */
    public function registerOrderUpdateCheckbox($order)
    {
        echo sprintf("<p style='margin-bottom: 0'><strong>%s</strong></p>", esc_html__('Status Update SMS Notifications:', 'wp-sms'));

        if ($order->get_meta(self::FIELD_ORDER_NOTIFICATION) && $order->get_meta(self::FIELD_ORDER_NOTIFICATION) == 'yes') {
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
     * For Checkout:shortcode
     *
     * @param $orderId
     * @param $data
     * @todo, let's check if this `registerSmsOptInOnCheckout` is compatible in both method of checkout, let's use one of them.
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
