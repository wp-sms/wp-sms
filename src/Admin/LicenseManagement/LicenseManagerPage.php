<?php

namespace WP_SMS\Admin\LicenseManagement;

use WP_SMS\Admin\LicenseManagement\Abstracts\MultiViewPage;
use WP_SMS\Admin\LicenseManagement\Views\TabsView;

class LicenseManagerPage extends MultiViewPage
{
    protected $pageSlug    = 'add-ons';
    protected $defaultView = 'tabs';
    protected $views       = [
        'tabs'              => TabsView::class,
    ];

    public function __construct()
    {
        parent::__construct();
    }

    protected function init()
    {
        $this->disableScreenOption();
    }

    public static function instance()
    {
        return new self();
    }
}
