<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WooCommerceOrderNotification extends Notification
{
    protected $order;

    protected $variables = [
        '%billing_first_name%'          => 'getFirstName',
        '%billing_last_name%'           => 'getLastName',
        '%billing_company%'             => 'getCompany',
        '%billing_address%'             => 'getAddress',
        '%order_edit_url%'              => 'getEditOrderUrl',
        '%billing_phone%'               => 'getBillingPhone',
        '%order_number%'                => 'getOrderNumber',
        '%order_total%'                 => 'getOrderTotal',
        '%order_total_currency%'        => 'getOrderCurrency',
        '%order_total_currency_symbol%' => 'getOrderCurrencySymbol',
        '%order_id%'                    => 'getOrderId',
        '%order_items%'                 => 'getOrderItems',
        '%status%'                      => 'getOrderStatus',
        '%order_meta_{key-name}%'       => 'getOrderMeta',
    ];

    public function __construct($orderId = false)
    {
        if ($orderId) {
            $this->order = wc_get_order($orderId);
        }
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

    public function getEditOrderUrl()
    {
        return wp_sms_shorturl($this->order->get_edit_order_url());
    }

    public function getBillingPhone()
    {
        return $this->order->get_billing_phone();
    }

    public function getOrderNumber()
    {
        return $this->order->get_order_number();
    }

    public function getOrderTotal()
    {
        return $this->order->get_total();
    }

    public function getOrderCurrency()
    {
        return $this->order->get_currency();
    }

    public function getOrderCurrencySymbol()
    {
        return get_woocommerce_currency_symbol($this->order->get_currency());
    }

    public function getOrderId()
    {
        return $this->order->get_id();
    }

    public function getOrderItems()
    {
        $preparedItems  = [];
        $currencySymbol = html_entity_decode(get_woocommerce_currency_symbol());

        foreach ($this->order->get_items() as $item) {
            $orderItemData   = $item->get_data();
            $preparedItems[] = "- {$orderItemData['name']} x {$orderItemData['quantity']} {$currencySymbol}{$orderItemData['total']}";
        }

        return implode('\n', $preparedItems);
    }

    public function getOrderStatus()
    {
        return wc_get_order_status_name($this->order->get_status());
    }

    public function getOrderMeta($metaKey)
    {
        return $this->order->get_meta($metaKey);
    }
}