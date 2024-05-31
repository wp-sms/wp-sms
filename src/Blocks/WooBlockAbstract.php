<?php

namespace WP_SMS\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use WP_SMS\Helper;

class WooBlockAbstract
{


    /**
     * Whether block name
     *
     * @var $blockName
     */
    protected $blockName;

    /**
     * Block type (text, select, checkbox)
     *
     * @var $blockType
     */
    protected $blockType = "text";

    /**
     * Block Label
     *
     * @var $blockLabel
     */
    protected $blockLabel;

    /**
     * Optional Label
     *
     * @var $blockOptionalLabel
     */
    protected $blockOptionalLabel;

    /**
     * Block location (contact, address, order)
     *
     * @var $blockLocation
     */
    protected $blockLocation;

    /**
     * Block Required
     *
     * @var bool $blockRequired
     */
    protected $blockRequired = false;

    /**
     * Block attributes
     *
     * @var array $blockAttributes
     */
    protected $blockAttributes = array();

    /**
     * Options for select type field
     *
     * @var array $blockOptions
     */

    protected $blockOptions = array();

    public function __construct()
    {
        $this->registerBlock();
    }

    public function registerBlock()
    {
        // Backward compatibility for WC less than v8.9
        if (function_exists('woocommerce_register_additional_checkout_field')) {
            woocommerce_register_additional_checkout_field(
                array(
                    'id'            => $this->blockName,
                    'label'         => $this->blockLabel,
                    'type'          => $this->blockType,
                    'optionalLabel' => $this->blockOptionalLabel,
                    'location'      => $this->blockLocation,
                    'required'      => $this->blockRequired,
                    'attributes'    => $this->blockAttributes,
                    'options'       => $this->blockOptions
                )
            );
        }
    }
}