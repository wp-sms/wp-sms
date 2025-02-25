<?php

namespace WP_SMS\Notification\Handler;

use WC_Order;
use WC_Order_Item;
use WP_SMS\Notification\Notification;
use WP_SMS\Services\WooCommerce\WooCommerceCheckout;

class WooCommerceOrderNotification extends Notification
{
    protected $order;

    protected $variables = [
        '%billing_first_name%'          => 'getFirstName',
        '%billing_last_name%'           => 'getLastName',
        '%billing_company%'             => 'getCompany',
        '%billing_address%'             => 'getAddress',
        '%billing_postcode%'            => 'getPostCode',
        '%payment_method%'              => 'getPaymentMethod',
        '%order_edit_url%'              => 'getEditOrderUrl',
        '%billing_phone%'               => 'getBillingPhone',
        '%billing_email%'               => 'getBillingEmail',
        '%order_number%'                => 'getNumber',
        '%order_total%'                 => 'getTotal',
        '%order_total_currency%'        => 'getCurrency',
        '%order_total_currency_symbol%' => 'getCurrencySymbol',
        '%order_pay_url%'               => 'getPayUrl',
        '%order_view_url%'              => 'getViewUrl',
        '%order_cancel_url%'            => 'getCancelUrl',
        '%order_received_url%'          => 'getReceivedUrl',
        '%order_id%'                    => 'getId',
        '%order_items%'                 => 'getItems',
        '%status%'                      => 'getStatus',
        '%shipping_method%'             => 'getShippingMethod',
        '%order_meta_{key-name}%'       => 'getMeta',
        '%order_item_meta_{key-name}%'  => 'getItemMeta',
    ];

    public function __construct($orderId = false)
    {
        if ($orderId) {
            $this->order = wc_get_order($orderId);
            $optInStatus = $this->order->get_meta(WooCommerceCheckout::FIELD_ORDER_NOTIFICATION);

            if ($optInStatus and $optInStatus == 'no') {
                $this->optIn = false;
            }
        }
    }

    protected function success($to)
    {
        $this->order->add_order_note(
        // translators: %s: Phone numbers
            sprintf(__('Successfully send SMS notification to %s', 'wp-sms'), implode(',', $to))
        );
    }

    protected function failed($to, $response)
    {
        $this->order->add_order_note(
        // translators: %1$s: Phone number, %2$s: Error message
            sprintf(__('Failed to send SMS notification to %1$s. Error: %2$s', 'wp-sms'), implode(',', $to), $response->get_error_message())
        );
    }

    public function getFirstName()
    {
        return $this->order->get_billing_first_name();
    }

    public function getLastName()
    {
        return $this->order->get_billing_last_name();
    }

    public function getCompany()
    {
        return $this->order->get_billing_company();
    }

    public function getAddress()
    {
        return $this->order->get_billing_address_1();
    }

    public function getPostCode()
    {
        return $this->order->get_billing_postcode();
    }

    public function getPaymentMethod()
    {
        return $this->order->get_payment_method_title();
    }

    public function getEditOrderUrl()
    {
        return wp_sms_shorturl($this->order->get_edit_order_url());
    }

    public function getBillingPhone()
    {
        return $this->order->get_billing_phone();
    }

    public function getBillingEmail()
    {
        return $this->order->get_billing_email();
    }

    public function getNumber()
    {
        return $this->order->get_order_number();
    }

    public function getTotal()
    {
        return $this->order->get_total();
    }

    public function getCurrency()
    {
        return $this->order->get_currency();
    }

    public function getCurrencySymbol()
    {
        return get_woocommerce_currency_symbol($this->order->get_currency());
    }

    public function getPayUrl()
    {
        return wp_sms_shorturl($this->order->get_checkout_payment_url());
    }

    public function getViewUrl()
    {
        return wp_sms_shorturl($this->order->get_view_order_url());
    }

    public function getCancelUrl()
    {
        return wp_sms_shorturl($this->order->get_cancel_order_url());
    }

    public function getReceivedUrl()
    {
        return wp_sms_shorturl($this->order->get_checkout_order_received_url());
    }

    public function getId()
    {
        return $this->order->get_id();
    }

    public function getItems()
    {
        $preparedItems  = [];
        $currencySymbol = html_entity_decode(get_woocommerce_currency_symbol());

        foreach ($this->order->get_items() as $item) {
            $orderItemData = $item->get_data();

            // Prepare the default item string
            $itemString = "- {$orderItemData['name']} x {$orderItemData['quantity']} {$currencySymbol}{$orderItemData['total']}";

            /**
             * Filter each order item string before adding to the prepared items.
             *
             * @param string $itemString The prepared string for the order item.
             * @param array $orderItemData The raw data of the order item.
             * @param WC_Order_Item $item The WooCommerce order item object.
             * @param WC_Order $order The current order object.
             */
            $itemString = apply_filters('wp_sms_notification_woocommerce_order_item', $itemString, $orderItemData, $item, $this->order);

            $preparedItems[] = $itemString;
        }

        return implode(PHP_EOL, $preparedItems);
    }

    public function getStatus()
    {
        return wc_get_order_status_name($this->order->get_status());
    }

    public function getShippingMethod()
    {
        return $this->order->get_shipping_method();
    }

    public function getMeta($metaKey)
    {
        $metaValue = $this->order->get_meta($metaKey);
        return apply_filters("wp_sms_notification_woocommerce_order_meta_key_{$metaKey}", $this->processMetaValue($metaValue));
    }

    /**
     * Get order item meta value
     *
     * @param string $metaKey
     * @return string
     */
    public function getItemMeta($metaKey)
    {
        $itemMetaValues = [];

        foreach ($this->order->get_items() as $item) {
            /** @var \WC_Product $product */
            $product = $item->get_product();
            $isVariation = $product->is_type('variation');
            $metaValue = null;

            if ($isVariation) {
                $metaValue = $product->get_meta($metaKey);

                // Backward compatibility.
                if (!$metaValue) {
                    $metaValue = get_post_meta($product->get_id(), $metaKey, true);
                }
            }

            if (!$metaValue) {
                $metaValue = $item->get_meta($metaKey);

                // Backward compatibility.
                if (!$metaValue) {
                    $metaValue = get_post_meta($item->get_product_id(), $metaKey, true);
                }
            }

            if ($metaValue) {
                $itemMetaValues[] = $this->processMetaValue($metaValue);
            }
        }

        return apply_filters("wp_sms_notification_woocommerce_order_item_meta_key_{$metaKey}", implode(', ', $itemMetaValues));
    }

    /**
     * Process meta value to handle arrays and other data types
     *
     * @param mixed $metaValue
     * @return string
     */
    private function processMetaValue($metaValue)
    {
        if (is_array($metaValue)) {
            return implode(', ', $metaValue);
        }

        return (string)$metaValue;
    }
}
