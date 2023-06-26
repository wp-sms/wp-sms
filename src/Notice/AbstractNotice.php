<?php

namespace WP_SMS\Notice;

use WP_SMS\Option;

abstract class AbstractNotice
{

    /**
     * @var string
     */
    protected $message;
    protected $options;

    /**
     * Render callback
     *
     * @return string html
     */
    abstract public function render();

    public function __construct()
    {
        $this->options = Option::getOptions();
    }

    /**
     * Register the widget
     *
     * @return void
     */
    public function register()
    {
        add_action('wp_sms_settings_page', function () {
            call_user_func([$this, 'render']);
        });
    }
}
