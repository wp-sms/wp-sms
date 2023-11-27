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
        $baseConfig = [
            'render_callback' => [$this, 'renderCallback'],
        ];

        // Define additional attributes for the SendSms block.
        $sendSmsAttributes = [
            'attributes' => [
                'title'           => ['type' => 'string', 'default' => ''],
                'description'     => ['type' => 'string', 'default' => ''],
                'onlyLoggedUsers' => ['type' => 'boolean', 'default' => false],
                'userRole'        => ['type' => 'string', 'default' => 'all'],
                'maxCharacters'   => ['type' => 'number', 'default' => 60],
                'receiver'        => ['type' => 'string', 'default' => 'admin'],
                'subscriberGroup' => ['type' => 'string', 'default' => '']
            ],
        ];

        // Check if the block is the SendSms block.
        if (strpos($this->blockName, 'SendSms') !== false) {
            // Merge the base config with the SendSms-specific attributes.
            $config = array_merge($baseConfig, $sendSmsAttributes);
        } else {
            // Use the base config for blocks without specific attributes.
            $config = $baseConfig;
        }

        register_block_type($blockPath, $config);
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
            wp_localize_script($this->script, "wpSms{$this->blockName}BlockData", $this->ajaxData());
            wp_enqueue_script("wpSms{$this->blockName}BlockData");
        }

        /**
         * Render the output
         */
        return $this->output($attributes);
    }
}
