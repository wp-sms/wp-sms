<?php

namespace WP_SMS\Blocks;

use Automattic\WooCommerce\Internal\Admin\BlockTemplates\Block;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use WP_SMS\Services\WooCommerce\WooCommerceCheckout;

class WooSmsOptInBlock extends WooBlockIntegration {

    protected $blockName = "SmsUpdatesOptIn";
    protected $blockVersion = '1.0';

    protected $blockData = array(
        'dataHandler' => ''
    );

    public function __construct()
    {
        try {
            $this->blockData['dataHandler'] = WooCommerceCheckout::FIELD_ORDER_NOTIFICATION;
        } catch (\Throwable $e) {
            error_log('Error accessing constant: ' . $e->getMessage());
        }

        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'updateBlockOrderMetaSmsOptIn' ), 10, 2 );
        add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'displaySmsOptInOnAdminOrderDetail' ) );
        add_action( 'woocommerce_order_details_after_order_table_items', array( $this, 'displaySmsOptInOnThankyouPage' ) );
    }


    public function blockDataCallback()
    {
        return array(
            WooCommerceCheckout::FIELD_ORDER_NOTIFICATION => false
        );
    }

    public function blockSchemaCallback()
    {
        return array(
            WooCommerceCheckout::FIELD_ORDER_NOTIFICATION => array(
                'description' => __('Sms Order Opt-In', 'wp-sms'),
                'type'        => array('bool', 'null'),
                'readonly'    => true,
            ),
        );
    }

    public function updateBlockOrderMetaSmsOptIn( $order, $request ) {
        $data = isset( $request['extensions']['wp-sms'] ) ? $request['extensions']['wp-sms'] : array();
        // Update the order meta with the delivery date from the request
        if ( isset( $data[WooCommerceCheckout::FIELD_ORDER_NOTIFICATION] ) ) {
            $order->update_meta_data( 'Order Notification', $data[WooCommerceCheckout::FIELD_ORDER_NOTIFICATION] );
            $order->save(); // Save the order to persist changes
        }
    }
    public function displaySmsOptInOnAdminOrderDetail( $order ) {
        $delivery_date = $order->get_meta( 'Order Notification', true );
        if ( $delivery_date ) {
            echo '<div class="delivery-date">';
            echo '<p><strong>' . esc_html__( 'Order Notification:', 'wp-sms' ) . '</strong> ' . esc_html( $delivery_date ) . '</p>';
            echo '</div>';
        }
    }
    public function displaySmsOptInOnThankyouPage( $order_id ) {

        $order = wc_get_order( $order_id );
        $delivery_date = $order->get_meta( 'Order Notification', true );
        if ( $delivery_date ) {
            echo '<p>' . esc_html__( 'Order Notification:', 'wp-sms' ) . ' ' . esc_html( $delivery_date ) . '</p>';
        }
    }
}