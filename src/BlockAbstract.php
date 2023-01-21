<?php

namespace WP_SMS\Blocks;

use WP_Block;
use WP_SMS\Helper;

class BlockAbstract
{
    /**
     * Whether block name
     *
     * @var $blockName
     */
    protected $blockName;

    /**
     * Widget class name
     *
     * @var $widgetClassName
     */
    protected $widgetClassName;

    /**
     * Front-end script
     *
     * @var bool $script
     */
    protected $script = false;

    /**
     * Block blockVersion
     *
     * @var $blockVersion
     */
    protected $blockVersion;

    /**
     * Register block type
     */
    public function registerBlockType()
    {
        $blockPath = Helper::getAssetPath("assets/blocks/{$this->blockName}");

        register_block_type($blockPath, array(
            'render_callback' => [$this, 'renderCallback'],
        ));
    }

    /**
     * @param $attributes
     * @param $content
     * @param WP_Block $block
     * @return mixed
     */
    public function renderCallback($attributes, $content, $block)
    {
        /**
         * Enqueue the script and data
         */
        if ($this->script) {
            wp_enqueue_script("wp-sms-blocks-subscribe", Helper::getPluginAssetUrl($this->script), ['jquery'], $this->blockVersion, true);
        }

        wp_localize_script(
            "wp-sms-blocks-{$this->blockName}",
            'pluginAssetsUrl',
            [
                'imagesFolder' => Helper::getPluginAssetUrl("images/"),
            ]
        );

        /**
         * Render the output
         */
        return $this->output($attributes);
    }
}
