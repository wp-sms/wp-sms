<?php

namespace WP_SMS\Admin\LicenseManagement;

use WP_SMS\Abstracts\MultiViewPage;
use WP_SMS\Admin\LicenseManagement\Views\TabsView;
use WP_SMS\Admin\LicenseManagement\Views\LockedMiniChartView;
use WP_SMS\Admin\LicenseManagement\Views\LockedRealTimeStatView;

class LicenseManagerPage extends MultiViewPage
{
    protected $pageSlug    = 'add-ons-1';
    protected $defaultView = 'tabs';
    protected $views       = [
        'tabs'              => TabsView::class,
        'locked-mini-chart' => LockedMiniChartView::class,
        'locked-real-time' => LockedRealTimeStatView::class
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
