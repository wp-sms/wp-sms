<?php

namespace WP_SMS\Blocks;

use WP_SMS;
use WP_SMS\Option;
use WP_SMS\Newsletter;
use WP_SMS\Helper;

class SubscribeBlock extends BlockAbstract
{
    protected $blockName = 'Subscribe';
    protected $blockVersion = '1.0';

    protected function output($attributes)
    {
        return wp_sms_subscriber_form($attributes);
    }
}
