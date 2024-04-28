<?php

namespace WP_SMS\Blocks;

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

class WooBlockManager
{
    private $blocks = [
        \WP_SMS\Blocks\WooSmsOptInBlock::class
    ];

    public function init()
    {
        foreach ($this->blocks as $item) {
            if (class_exists($item)) {
                add_action(
                    'woocommerce_blocks_loaded',
                    function() use ($item) {
                        add_action(
                            'woocommerce_blocks_checkout_block_registration',
                            function( $integration_registry ) use ($item) {
                                $block = new $item();
                                $block->initialize();
                                $integration_registry->register( $block );
                            }
                        );

                        if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
                            woocommerce_store_api_register_endpoint_data(
                                array(
                                    'endpoint' => CheckoutSchema::IDENTIFIER,
                                    'namespace' => "wp-sms",
                                    'data_callback' => array($item, 'blockDataCallback'),
                                    'schema_callback' =>  array($item, 'blockSchemaCallback'),
                                    'schema_type' => ARRAY_A,
                                )
                            );
                        }
                    }
                );
            } else {
                add_action('admin_notices', function () use ($item) {
                    // translators: %s: Class name
                    echo '<div class="notice notice-error"><p>' . sprintf(esc_html__('WP SMS: Widget encountered an error, class %s could not be loaded.', 'wp-sms'), '<b>' . esc_html($item) . '</b>') . '</p></div>';
                });
            }
        }
    }


}