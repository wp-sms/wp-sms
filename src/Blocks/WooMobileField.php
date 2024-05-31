<?php

namespace WP_SMS\Blocks;

class WooMobileField extends WooBlockAbstract
{
    protected $blockName = 'wpsms/mobile';
    protected $blockLocation = 'address';
    protected $blockRequired = false;
    protected $blockAttributes = array(
        'autocomplete'     => '',
        'aria-describedby' => 'Mobile Field',
        'aria-label'       => 'Mobile Field',
        'title'            => 'Mobile Field',
        'data-custom'      => 'wpsms_woocommerce_order_notification',
    );

    public function __construct()
    {
        $this->blockLabel         = __('Mobile Number', 'wp-sms');
        $this->blockOptionalLabel = __('Mobile Number', 'wp-sms');

        parent::__construct();
    }
}