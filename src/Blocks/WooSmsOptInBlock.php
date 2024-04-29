<?php

namespace WP_SMS\Blocks;

use Automattic\WooCommerce\Internal\Admin\BlockTemplates\Block;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

class WooSmsOptInBlock extends WooBlockIntegration {

    protected $blockName = "OrderNotification";
    protected $blockVersion = '1.0';


    public function blockDataCallback()
    {
        return array(
            'opt_in' => false
        );
    }

    public function blockSchemaCallback()
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