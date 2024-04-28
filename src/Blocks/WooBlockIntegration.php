<?php
namespace WP_SMS\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use WP_SMS\Helper;

class WooBlockIntegration implements IntegrationInterface {


    /**
     * Whether block name
     *
     * @var $blockName
     */
    protected $blockName;

    /**
     * Block blockVersion
     *
     * @var $blockVersion
     */
    protected $blockVersion;


    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name() {
        return $this->blockName; // Updated integration name
    }

    /**
     * When called invokes any initialization/setup for the integration.
     */
    public function initialize() {
        $this->register_block_frontend_scripts();
        $this->register_block_editor_scripts();
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return string[]
     */
    public function get_script_handles() {
        return array( "WpSmsWooBlock{$this->blockName}Frontend" ); // Updated script handle
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return string[]
     */
    public function get_editor_script_handles() {
        return array( "WpSmsWooBlock{$this->blockName}" ); // Updated script handle
    }

    /**
     * An array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data() {
        return array();
    }

    /**
     * Register scripts for date field block editor.
     *
     * @return void
     */
    public function register_block_editor_scripts() {
        $script_path = Helper::getAssetPath("blocks/{$this->blockName}/index.js");
        $script_url = Helper::getPluginAssetUrl($script_path);
        $script_asset_path = Helper::getAssetPath("blocks/{$this->blockName}/index.asset.php" );
        $script_asset = file_exists( $script_asset_path )
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version' => $this->blockVersion,
            );

        wp_register_script(
            "WpSmsWooBlock{$this->blockName}",
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
    }

    /**
     * Register scripts for frontend block.
     *
     * @return void
     */
    public function register_block_frontend_scripts() {
        $script_path = Helper::getAssetPath("blocks/{$this->blockName}/frontend.js");
        $script_url = Helper::getPluginAssetUrl($script_path);
        $script_asset_path = Helper::getAssetPath("blocks/{$this->blockName}/index.asset.php" );

        $script_asset = file_exists( $script_asset_path )
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version' => $this->blockVersion,
            );

        wp_register_script(
            "WpSmsWooBlock{$this->blockName}Frontend",
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
    }

    /**
     * Callback function to register endpoint data for blocks.
     *
     * @return array
     */
    protected function blockDataCallback() {
        return array();
    }


    /**
     * Callback function to register schema for data.
     *
     * @return array
     */
    protected function blockSchemaCallback()
    {
        return array();
    }
}