<?php

namespace WP_SMS\Blocks;

use Automattic\WooCommerce\Internal\Admin\BlockTemplates\Block;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use WP_SMS\Services\WooCommerce\WooCommerceCheckout;

class WooMobileField extends WooBlockIntegration {

    protected $blockName = "MobileField";
    protected $blockVersion = '1.0';

    protected $blockData = array(
        'dataHandler' => 'mobile'
    );
}