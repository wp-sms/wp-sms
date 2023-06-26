<?php

namespace WP_SMS\Notice;

use WP_SMS\Option;

abstract class AbstractNotice
{

    protected $notices = [];
    protected $options;

    abstract public function render();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options = Option::getOptions();
    }

    /**
     * Register
     */
    public function register()
    {
        if (
            isset($_GET['action']) && isset($_GET['name']) && isset($_GET['security'])
            && $_GET['action'] == 'wpsms-hide-notice' && wp_verify_nonce($_GET['security'], 'wp_sms_notice')
        ) {
            update_option('wpsms_hide_' . $_GET['name'] . '_notice', true);
        }

        add_action('wp_sms_settings_page', function () {
            call_user_func([$this, 'render']);
        });
    }

    /**
     * Register Notice
     */
    protected function registerNotice($notice)
    {
        $this->notices[] = $notice;
    }
}
