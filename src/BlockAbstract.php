<?php

namespace WP_SMS\Blocks;

use WP_Block;
use WP_SMS\Helper;
use WP_SMS\Newsletter;

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

        // Define a base config for all blocks.
        $config = ['render_callback' => [$this, 'renderCallback']];
        if (method_exists($this, 'buildBlockAttributes')) {
            $config = $this->buildBlockAttributes($config);
        }
        register_block_type($blockPath, $config);

        /**
         * Enqueue the script and data
         */
        if ($this->script) {
            wp_localize_script($this->script, "wpSms{$this->blockName}BlockData", $this->buildBlockAjaxData());
            wp_enqueue_script("wpSms{$this->blockName}BlockData");
        }
    }

    /**
     * Render the output
     * @param $attributes
     * @param $content
     * @param WP_Block $block
     * @return mixed
     */
    public function renderCallback($attributes, $content, $block)
    {
        return $this->output($attributes);
    }
}