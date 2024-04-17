<?php

namespace WP_SMS\Blocks;

class OrderNotificationBlock extends BlockAbstract
{
    protected $blockName = 'OrderNotification';
    protected $blockVersion = '1.0';
    protected $script = 'wp-sms-blocks-order-notification-editor-script';

    protected function output($attributes)
    {
        return \WP_SMS\Helper::loadTemplate('order-notification.php');
    }

    public function buildBlockAjaxData()
    {
    }

    public function buildBlockAttributes($config)
    {
        return $config;
    }
}