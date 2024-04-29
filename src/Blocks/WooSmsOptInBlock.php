<?php

namespace WP_SMS\Blocks;

use Automattic\WooCommerce\Internal\Admin\BlockTemplates\Block;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use WP_SMS\Services\WooCommerce\WooCommerceCheckout;

class WooSmsOptInBlock extends WooBlockIntegration {

    protected $blockName = "SmsUpdatesOptIn";
    protected $blockVersion = '1.0';


    public function blockDataCallback()
    {
        return array(
            WooCommerceCheckout::FIELD_ORDER_NOTIFICATION => false
        );
    }

    public function blockSchemaCallback()
    {
        return array(
            WooCommerceCheckout::FIELD_ORDER_NOTIFICATION => array(
                'description' => __('Sms Order Opt-In', 'wp-sms'),
                'type'        => array('bool', 'null'),
                'readonly'    => true,
            ),
        );
    }
}