<?php

namespace WP_SMS\Notification\Handler;

if (!defined('ABSPATH')) exit;

class WooCommerceAdminOrderNotification extends WooCommerceOrderNotification
{
    protected $order;

    public function __construct($orderId = false)
    {
        if ($orderId) {
            $this->order = wc_get_order($orderId);
        }
    }
}
