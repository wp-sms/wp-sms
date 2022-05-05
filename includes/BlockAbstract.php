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
        $blockPath = "wp-sms-blocks/{$this->blockName}";

        wp_register_script("wp-sms-blocks-{$this->blockName}-script", Helper::getPluginAssetUrl("blocks/{$this->blockName}/index.js"), array('wp-blocks', 'wp-element'));
        wp_register_style("wp-sms-blocks/{$this->blockName}-style", Helper::getPluginAssetUrl("blocks/{$this->blockName}/index.css"));

        register_block_type($blockPath, array(
            'render_callback' => [$this, 'renderCallback'],
            'editor_script'   => "wp-sms-blocks-{$this->blockName}-script",
            'editor_style'    => "wp-sms-blocks/{$this->blockName}-style",
        ));
    }

    /**
     * @param $attributes
     * @param $content
     * @param WP_Block $block
     * @return mixed
     */
    public function renderCallback($attributes, $content, WP_Block $block)
    {
        wp_enqueue_script("wp-sms-blocks-{$this->blockName}-frontend", Helper::getPluginAssetUrl("blocks/{$this->blockName}/frontend.js"), ['jquery'], $this->blockVersion, true);

        if ($this->blockName == "subscribe") {
            wp_localize_script("wp-sms-blocks-{$this->blockName}-frontend", 'wpsms_ajax_object', array(
                'rest_endpoint_url' => get_rest_url(null, 'wpsms/v1/newsletter'),
                'unknown_error'     => __('Unknown Error! Check your connection and try again.', 'wp-sms'),
                'loading_text'      => __('Loading...', 'wp-sms'),
                'subscribe_text'    => __('Subscribe', 'wp-sms'),
                'activation_text'   => __('Activation', 'wp-sms'),
            ));
        }

        wp_localize_script(
            "wp-sms-blocks-{$this->blockName}-frontend",
            'pluginAssetsUrl',
            [
                'imagesFolder' => Helper::getPluginAssetUrl("images/"),
            ]
        );
        /**
         * Enqueue the script and data
         */
        if ($this->script) {
            wp_enqueue_script("wp-sms-blocks-{$this->blockName}", Helper::getPluginAssetUrl($this->script), ['jquery'], $this->blockVersion, true);
            wp_localize_script("wp-sms-blocks-{$this->blockName}", "{$this->blockName}Object", $this->getData($attributes));
        }

        /**
         * Render the output
         */
        return $this->output($attributes);
    }
}
