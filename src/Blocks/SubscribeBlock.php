<?php

namespace WP_SMS\Blocks;

use WP_SMS;

class SubscribeBlock extends BlockAbstract
{
    protected $blockName = 'Subscribe';
    protected $blockVersion = '1.0';

    protected function output($attributes)
    {
        return wp_sms_subscriber_form($attributes);
    }

    public function buildBlockAjaxData()
    {
    }

    public function buildBlockAttributes($baseConfig)
    {
        return $baseConfig;
    }
}
