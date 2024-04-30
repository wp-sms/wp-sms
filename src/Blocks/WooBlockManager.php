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
        //add_action('woocommerce_blocks_loaded', [$this, 'init']);
        $this->init();
    }

    public function init()
    {
        foreach ($this->blocks as $item) {
            if (class_exists($item)) {
                $this->registerBlockActions($item);
            } else {
                add_action('admin_notices', function () use ($item) {
                    echo '<div class="notice notice-error"><p>' . sprintf(esc_html__('WP SMS: Widget encountered an error, class %s could not be loaded.', 'wp-sms'), '<b>' . esc_html($item) . '</b>') . '</p></div>';
                });
            }
        }
    }

    /**
     * Register block actions.
     */
    public function registerBlockActions($item)
    {
            add_action('woocommerce_blocks_checkout_block_registration', function ($integration_registry) use ($item) {
                $this->registerBlock($item, $integration_registry);
            });

            if (function_exists('woocommerce_store_api_register_endpoint_data')) {
                $this->registerStoreApiEndpoint($item);
            }
    }

    /**
     * Register a block with the integration registry.
     */
    protected function registerBlock($item, $integration_registry)
    {
        $block = new $item();
        $integration_registry->register($block);
    }

    /**
     * Register endpoint data for the WooCommerce Store API.
     */
    protected function registerStoreApiEndpoint($item)
    {
        $block = new $item();
        woocommerce_store_api_register_endpoint_data([
            'endpoint' => CheckoutSchema::IDENTIFIER,
            'namespace' => "wp-sms",
            'data_callback' => [$block, 'blockDataCallback'],
            'schema_callback' => [$block, 'blockSchemaCallback'],
            'schema_type' => ARRAY_A,
        ]);
    }
}
