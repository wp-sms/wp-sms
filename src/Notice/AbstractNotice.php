<?php

namespace WP_SMS\Notice;

use WP_SMS\Option;

abstract class AbstractNotice
{
    protected $notices = [];
    protected $options;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options = Option::getOptions();
    }

    /**
     * This method is responsible to dismiss the notice and update it on option.
     *
     * @return void
     */
    public function action()
    {
        if (isset($_GET['wpsms_dismiss_notice']) && wp_verify_nonce('')) {

            // todo
            update_option('wpsms_notices', $this->notices);
        }
    }

    /**
     * Register Notice
     */
    protected function registerNotice($notice, $dismiss = false, $url = false)
    {
        $this->notices[] = [
            'notice'  => $notice,
            'dismiss' => $dismiss,
            'url'     => $url
        ];
    }
}
