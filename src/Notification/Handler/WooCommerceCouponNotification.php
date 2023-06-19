<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WooCommerceCouponNotification extends Notification
{
    protected $coupon;

    protected $variables = [
        '%coupon_code%'   => 'getCode',
        '%coupon_amount%' => 'getAmount',
        '%coupon_name%'   => 'getName',
    ];

    public function __construct($couponId = false)
    {
        if ($couponId) {
            $this->coupon = new \WC_Coupon($couponId);
        }
    }

    public function getCode()
    {
        return $this->coupon->get_code();
    }

    public function getAmount()
    {
        return $this->coupon->get_amount();
    }

    public function getName()
    {
        return get_the_title($this->coupon->get_id());
    }
}