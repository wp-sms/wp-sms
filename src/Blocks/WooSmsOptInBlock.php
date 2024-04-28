<?php

namespace WP_SMS\Blocks;

class WooSmsOptInBlock extends WooBlockIntegration
{
    protected $blockName = "OrderNotification";
    protected $blockVersion = '1.0';

    protected function blockDataCallback()
    {
        return array(
            'opt_in' => false
        );
    }

    protected function blockSchemaCallback()
    {
        return array(
            'opt_in' => array(
                'description' => __('Sms Order Opt-In', 'wp-sms'),
                'type'        => array('bool', 'null'),
                'readonly'    => true,
            ),
        );
    }
}