<?php

namespace WP_SMS\Blocks;

class WooMobileField extends WooBlockAbstract
{
    protected $blockName = "wpsms/mobile";
    protected $blockLocation = "address";
    protected $blockRequired = false;
    protected $blockLabel = "Mobile Field";
    protected $blockOptionalLabel = "Mobile Field";

    protected $blockAttributes = array(
        'autocomplete'     => '',
        'aria-describedby' => 'WP SMS Opt-In',
        'aria-label'       => 'Mobile Field',
        'pattern'          => '', // A 5-character string of capital letters and numbers.
        'title'            => 'Mobile',
        'data-custom'      => 'wpsms_woocommerce_order_notification',
    );
}