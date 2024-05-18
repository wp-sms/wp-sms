<?php

namespace WP_SMS\Blocks;

class WooSmsOptInBlock extends WooBlockAbstract
{
    protected $blockName = "wpsms/opt-in";
    protected $blockLocation = "order";
    protected $blockRequired = false;
    protected $blockType = 'checkbox';

    public function __construct() {
        $this->blockLabel = __('I would like to get notification about any change in my order via SMS.', 'wp-sms');
        $this->blockOptionalLabel = __('I would like to get notification about any change in my order via SMS. (optional)', 'wp-sms');

        parent::__construct();
    }

    protected $blockAttributes = array(
        'autocomplete'     => '',
        'aria-describedby' => 'WP SMS Opt-In',
        'aria-label'       => 'Opt-In',
        'title'            => 'Opt-In',
        'data-custom'      => 'optin',
    );
}