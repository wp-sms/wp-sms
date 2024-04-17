<?php

namespace WP_SMS\Blocks;

class WooCommerceSmsOptInCheckoutBlock extends BlockAbstract
{
    protected $blockName = 'OrderNotification';
    protected $blockVersion = '1.0';
    protected $script = 'wp-sms-blocks-order-notification-editor-script';

    protected function output($attributes)
    {
        return \WP_SMS\Helper::loadTemplate('woo-sms-opt-checkout.php');
    }

    public function buildBlockAjaxData()
    {
    }

    public function buildBlockAttributes($config)
    {
        return $config;
    }
}