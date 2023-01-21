<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WooCommerceProductNotification extends Notification
{
    protected $product;

    protected $variables = [
        '%product_id%'             => 'getId',
        '%product_title%'          => 'getTitle',
        '%product_url%'            => 'getUrl',
        '%product_date%'           => 'getDate',
        '%product_price%'          => 'getPrice',
        '%product_sale_price%'     => 'getSalePrice',
        '%product_description%'    => 'getDescription',
        '%product_height%'         => 'getHeight',
        '%product_weight%'         => 'getWeight',
        '%product_width%'          => 'getWidth',
        '%product_stock_quantity%' => 'getStockQuantity',
        '%product_availability%'   => 'getAvailability',
    ];

    public function __construct($productId = false)
    {
        if ($productId) {
            $this->product = wc_get_product($productId);
        }
    }

    public function getTitle()
    {
        return $this->product->get_title();
    }

    public function getUrl()
    {
        return wp_sms_shorturl($this->product->get_permalink());
    }

    public function getDate()
    {
        return $this->product->get_date_created();
    }

    public function getPrice()
    {
        return $this->product->get_regular_price();
    }

    public function getSalePrice()
    {
        return $this->product->get_sale_price();
    }

    public function getDescription()
    {
        return $this->product->get_description();
    }

    public function getId()
    {
        return $this->product->get_id();
    }

    public function getHeight()
    {
        return $this->product->get_height();
    }

    public function getWeight()
    {
        return $this->product->get_weight();
    }

    public function getWidth()
    {
        return $this->product->get_width();
    }

    public function getStockQuantity()
    {
        return $this->product->get_stock_quantity();
    }

    public function getAvailability()
    {
        return $this->product->get_availability();
    }
}