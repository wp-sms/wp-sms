<?php

namespace WP_SMS\Blocks;

class BlockAssetsManager
{
    private $blocks = [
        \WP_SMS\Blocks\SubscribeBlock::class,
        \WP_SMS\Blocks\SendSmsBlock::class
    ];

    public function init()
    {
        add_action('init', [$this, 'registerBlocks']);
        add_action('block_categories_all', [$this, 'registerPluginBlockCategory'], 10, 2);
    }

    public function registerBlocks()
    {
        if (!function_exists('register_block_type')) {
            error_log(__('WP SMS: The "register_block_type" function is not supported in this version of WordPress.', 'wp-sms'));
            return;
        }

        foreach ($this->blocks as $item) {
            if (class_exists($item)) {
                $block = new $item();
                $block->registerBlockType();
            } else {
                add_action('admin_notices', function () use ($item) {
                    // translators: %s: Class name
                    echo '<div class="notice notice-error"><p>' . sprintf(esc_html__('WP SMS: Widget encountered an error, class %s could not be loaded.', 'wp-sms'), '<b>' . esc_html($item) . '</b>') . '</p></div>';
                });
            }
        }
    }

    /**
     * @param $categories
     * @return array
     */
    public function registerPluginBlockCategory($categories)
    {
        return array_merge($categories, [[
            'slug'  => 'wp-sms-blocks',
            'title' => __('WP SMS', 'wp-sms'),
        ]]);
    }
}
