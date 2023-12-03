<?php

namespace WP_SMS\Blocks;

use WP_Block;
use WP_SMS\Helper;

abstract class BlockAbstract
{
    /**
     * Whether block name
     *
     * @var $blockName
     */
    protected $blockName;

    /**
     * Front-end script
     *
     * @var string $script
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
        $baseConfig = ['render_callback' => [$this, 'renderCallback']];
        $config     = $this->buildBlockAttributes($baseConfig);

        register_block_type($blockPath, $config);

        /**
         * Enqueue the script and data
         */
        if ($this->script) {
            wp_enqueue_script("wpSms{$this->blockName}BlockData");
            wp_localize_script($this->script, "wpSms{$this->blockName}BlockData", $this->buildBlockAjaxData());
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

    /**
     * Build the Ajax data for the block.
     *
     * This method should be implemented in the child class.
     *
     * @return array An array containing the Ajax data for the block.
     */
    abstract public function buildBlockAjaxData();

    /**
     * Build the block attributes
     *
     * This method must be implemented by the child classes to build and return the block attributes.
     *
     * @return array The block attributes
     */
    abstract function buildBlockAttributes($config);
}