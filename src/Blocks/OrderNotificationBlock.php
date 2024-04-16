<?php

namespace WP_SMS\Blocks;

class OrderNotificationBlock extends BlockAbstract
{
    protected $blockName = 'OrderNotification';
    protected $blockVersion = '1.0';
    protected $script = 'wp-sms-blocks-order-notification-editor-script';

    protected function output($attributes)
    {
        return wp_sms_subscriber_form($attributes);
    }

    public function buildBlockAjaxData()
    {
    }

    public function buildBlockAttributes($config)
    {
        return $config;
    }
}