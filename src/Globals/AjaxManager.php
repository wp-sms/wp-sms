<?php

namespace WP_SMS\Globals;

use WP_SMS\Components\Ajax;

class AjaxManager
{
    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    /**
     * Register AJAX callbacks.
     *
     * @example Ajax::register('test', [$this, 'test'])
     */
    public function register()
    {

    }
}