<?php

namespace WP_SMS\Admin\LicenseManagement\Abstracts;

use WP_SMS\Components\Singleton;
use WP_SMS\Menus;

abstract class BasePage extends Singleton
{
    protected $pageSlug;

    public function __construct()
    {
            $this->init();
    }

    protected function init()
    {
    }

    protected function disableScreenOption()
    {
        add_filter('screen_options_show_screen', '__return_false');
    }
}