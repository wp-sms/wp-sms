<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WooCommerceProductNotification extends Notification
{
    protected $product;

    protected $variables = [
        '%product_title%' => 'getProductTitle',
    ];

    public function __construct($productId = false)
    {
        if ($productId) {
            $this->product = wc_get_product($productId);
        }
    }

    public function getProductTitle()
    {
        return $this->product->get_title();
    }
}