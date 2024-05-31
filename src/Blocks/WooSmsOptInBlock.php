<?php

namespace WP_SMS\Blocks;

class WooSmsOptInBlock extends WooBlockAbstract
{
    protected $blockName = "wpsms/opt-in";
    protected $blockLocation = "order";
    protected $blockRequired = false;
    protected $blockType = 'checkbox';
    protected $blockAttributes = array(
        'autocomplete'     => '',
        'aria-describedby' => 'SMS Opt-In',
        'aria-label'       => 'SMS Opt-In',
        'title'            => 'SMS Opt-In',
        'data-custom'      => 'optin',
    );

    public function __construct()
    {
        $this->blockLabel         = __('Status Update SMS Notifications', 'wp-sms');
        $this->blockOptionalLabel = __('I would like to get notification about any change in my order via SMS.', 'wp-sms');

        parent::__construct();
    }
}