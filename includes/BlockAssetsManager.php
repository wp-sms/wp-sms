<?php

namespace WP_SMS\Blocks;

class BlockAssetsManager
{
    private $blocks = [
        \WP_SMS\Blocks\SubscribeBlock::class
    ];

    public function init()
    {
        add_action('init', [$this, 'registerBlocks']);
        add_action('block_categories_all', [$this, 'registerPluginBlockCategory'], 10, 2);
    }

    public function registerBlocks()
    {
        foreach ($this->blocks as $item) {
            if (class_exists($item)) {
                $block = new $item();
                $block->registerBlockType();
            } else {
                add_action('admin_notices', function () use ($item) {
                    echo '<div class="notice notice-error"><p>' . sprintf(__('WP SMS Widget encountered an error, class <b>%s</b> could not be loaded.', 'wp-sms'), $item) . '</p></div>';
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
        return array_merge(
            $categories,
            [
                [
                    'slug'  => 'wp-sms-blocks',
                    'title' => __('WP SMS', 'wp-sms'),
                ],
            ]
        );
    }
}
