<?php

namespace WP_SMS\Blocks;

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

class WooBlockManager
{
    private $blocks = [
        \WP_SMS\Blocks\WooSmsOptInBlock::class,
        \WP_SMS\Blocks\WooMobileField::class
    ];

    public function __construct()
    {
        // @todo: Testing the functionality
        $this->init();
    }

    public function init()
    {
        foreach ($this->blocks as $item) {
            if (class_exists($item)) {
                new $item($this);
            } else {
                add_action('admin_notices', function () use ($item) {
                    echo '<div class="notice notice-error"><p>' . sprintf(esc_html__('WP SMS: Widget encountered an error, class %s could not be loaded.', 'wp-sms'), '<b>' . esc_html($item) . '</b>') . '</p></div>';
                });
            }
        }
    }
}
